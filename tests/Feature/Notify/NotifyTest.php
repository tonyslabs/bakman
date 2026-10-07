<?php

namespace Tests\Feature\Notify;

use App\Jobs\RunBackupJob;
use App\Models\BackupJob;
use App\Models\BackupJobRun;
use App\Services\BackupService;
use App\Services\Notify\JobAlerts;
use App\Services\Notify\TaskDigest;
use App\Services\Tasks\TaskRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class NotifyTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 07:30', 'America/Managua'));
        $this->dir = sys_get_temp_dir().'/notify-'.uniqid();
        mkdir($this->dir);
        config([
            'tasks.path' => $this->dir, 'tasks.file_uid' => null, 'backups.timezone' => 'America/Managua',
            'notify.ntfy.url' => 'http://ntfy:80', 'notify.ntfy.token' => 'tk_test', 'notify.app_url' => 'http://100.76.255.29:8091',
        ]);
        $this->app->instance(TaskRepository::class, new TaskRepository($this->dir));
        Http::fake(['ntfy:80*' => Http::response(['id' => 'x'])]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sent(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]->data())->all();
    }

    public function test_morning_digest_lists_overdue_and_today_once_per_day(): void
    {
        $repo = app(TaskRepository::class);
        $repo->create('Atrasada', ['estado' => 'pendiente', 'vence' => '2026-10-01', 'prioridad' => 'alta', 'area' => 'up']);
        $repo->create('Pagar internet', ['estado' => 'pendiente', 'vence' => '2026-10-06', 'repite' => 'mensual:6', 'area' => 'finanzas']);
        $repo->create('Cerrada', ['estado' => 'hecha', 'vence' => '2026-10-06']);
        $repo->create('Pospuesta', ['estado' => 'pendiente', 'vence' => '2026-10-06', 'inicio' => '2026-10-10']);

        $text = app(TaskDigest::class)->send('manana');

        $this->assertStringContainsString('⚠️ Vencidas (1)', $text);
        $this->assertStringContainsString('• 🔴 Atrasada (1 oct.) · Up Digital', $text);
        $this->assertStringContainsString('📌 Hoy (1)', $text);
        $this->assertStringContainsString('• Pagar internet ↻ · Finanzas', $text);
        $this->assertStringNotContainsString('Cerrada', $text);
        $this->assertStringNotContainsString('Pospuesta', $text);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer tk_test')
            && $r['topic'] === 'bakman-tareas' && $r['priority'] === 4
            && $r['click'] === 'http://100.76.255.29:8091/tareas/agenda');

        $this->assertNull(app(TaskDigest::class)->send('manana'), 'una vez por día');
        $this->assertNotNull(app(TaskDigest::class)->send('manana', force: true));
    }

    public function test_evening_reminder_only_counts_today_and_skips_when_empty(): void
    {
        $repo = app(TaskRepository::class);
        $repo->create('Atrasada', ['estado' => 'pendiente', 'vence' => '2026-10-01']);
        $this->assertNull(app(TaskDigest::class)->send('tarde'));

        $repo->create('Para hoy', ['estado' => 'en-curso', 'vence' => '2026-10-06']);
        $text = app(TaskDigest::class)->send('tarde');
        $this->assertStringStartsWith('Te quedan 1 para hoy', $text);
        $this->assertStringNotContainsString('Atrasada', $text);
    }

    public function test_nothing_is_sent_when_ntfy_is_not_configured(): void
    {
        config(['notify.ntfy.token' => null]);
        app(TaskRepository::class)->create('Para hoy', ['estado' => 'pendiente', 'vence' => '2026-10-06']);

        app(TaskDigest::class)->send('manana');
        Http::assertNothingSent();
    }

    public function test_failures_alert_first_then_every_n_and_recovery(): void
    {
        config(['notify.jobs.recordar_cada' => 3]);
        $job = BackupJob::create(['name' => 'betel-sync', 'type' => 'script', 'command' => 'false', 'schedule_cron' => '0 * * * *', 'retention_count' => 5, 'enabled' => true]);
        $alerts = app(JobAlerts::class);
        $run = fn ($status) => BackupJobRun::create(['backup_job_id' => $job->id, 'status' => $status, 'started_at' => now(), 'finished_at' => now(), 'duration_seconds' => 4, 'error_message' => $status === 'failed' ? 'SKU duplicado' : null]);

        foreach (range(1, 4) as $i) {
            $alerts->failed($job, $run('failed'));
        }
        $alerts->succeeded($job, $run('success'));
        $alerts->succeeded($job, $run('success'));

        $titles = array_column($this->sent(), 'title');
        $this->assertSame(['Falló betel-sync', 'betel-sync sigue fallando (3 seguidas)', 'betel-sync volvió a funcionar'], $titles);
        $this->assertSame('bakman-jobs', $this->sent()[0]['topic']);
        $this->assertSame('SKU duplicado', $this->sent()[0]['message']);
    }

    public function test_backup_job_failure_triggers_alert(): void
    {
        $job = BackupJob::create(['name' => 'dvprod', 'type' => 'database', 'schedule_cron' => '0 * * * *', 'retention_count' => 5, 'enabled' => true]);
        $service = Mockery::mock(BackupService::class);
        $service->shouldReceive('runDatabaseBackup')->andThrow(new \RuntimeException('mysqldump: acceso denegado'));

        (new RunBackupJob($job))->handle($service);

        $this->assertSame('Falló dvprod', $this->sent()[0]['title']);
        $this->assertSame('mysqldump: acceso denegado', $this->sent()[0]['message']);
    }

    public function test_watch_alerts_stuck_runs_once(): void
    {
        $job = BackupJob::create(['name' => 'lento', 'type' => 'file', 'schedule_cron' => '0 3 * * *', 'retention_count' => 5, 'enabled' => true]);
        BackupJobRun::create(['backup_job_id' => $job->id, 'status' => 'running', 'started_at' => now()->subHours(3)]);

        $this->assertGreaterThanOrEqual(1, app(JobAlerts::class)->watch());
        $count = count($this->sent());
        app(JobAlerts::class)->watch();

        $this->assertCount($count, $this->sent(), 'no repite el mismo aviso');
        $this->assertContains('lento parece colgado', array_column($this->sent(), 'title'));
    }

    public function test_commands(): void
    {
        $this->artisan('avisos:probar')->assertSuccessful();
        Http::assertSent(fn (Request $r) => $r['title'] === 'Prueba de bakman');
        $this->artisan('tareas:avisar manana')->expectsOutput('Nada que avisar (o ya se avisó hoy).');
        $this->artisan('tareas:avisar otra')->expectsOutput('Tipo inválido: manana o tarde.');
        $this->artisan('jobs:vigilar')->assertSuccessful();
    }
}
