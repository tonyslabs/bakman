<?php

namespace Tests\Feature;

use App\Models\BackupJob;
use App\Models\BackupJobRun;
use App\Models\DatabaseConnection;
use App\Models\User;
use App\Services\JobSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['backups.browse_root' => sys_get_temp_dir(), 'backups.path' => '/nonexistent', 'backups.logs_path' => '/nonexistent']);
    }

    private function dbJob(array $attrs = []): BackupJob
    {
        $connection = DatabaseConnection::create(['name' => 'VPS', 'host' => 'x', 'port' => 3306, 'username' => 'root', 'password' => 'x']);

        return BackupJob::create(array_merge([
            'name' => 'dvprod', 'type' => 'database', 'database_connection_id' => $connection->id,
            'db_name' => 'dvprod', 'schedule_cron' => '0 */3 * * *', 'enabled' => true,
        ], $attrs));
    }

    public function test_dashboard_summarises_and_flags_failed_job(): void
    {
        $job = $this->dbJob();
        BackupJobRun::create(['backup_job_id' => $job->id, 'status' => 'success', 'started_at' => now()->subHours(3), 'size_bytes' => 15_000_000]);
        BackupJobRun::create(['backup_job_id' => $job->id, 'status' => 'failed', 'started_at' => now()->subMinutes(5), 'error_message' => 'mysqldump falló: Access denied']);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 requiere atención')
            ->assertSee('mysqldump falló: Access denied')
            ->assertSee('14.3 MB')
            ->assertSee('Próximas ejecuciones');
    }

    public function test_dashboard_all_good_state(): void
    {
        $job = $this->dbJob();
        BackupJobRun::create(['backup_job_id' => $job->id, 'status' => 'success', 'started_at' => now()]);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Todo en orden');
    }

    public function test_monitor_lists_jobs_with_status(): void
    {
        $job = $this->dbJob();
        BackupJobRun::create(['backup_job_id' => $job->id, 'status' => 'success', 'started_at' => now()]);

        $this->actingAs(User::factory()->create())->get(route('monitor'))
            ->assertOk()
            ->assertSee('dvprod')
            ->assertSee('éxito')
            ->assertSee('Ejecutar');
    }

    public function test_run_now_returns_to_previous_page(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $job = $this->dbJob();

        $this->actingAs(User::factory()->create())
            ->from(route('monitor'))
            ->post(route('backup-jobs.run', $job))
            ->assertRedirect(route('monitor'));
    }

    public function test_overdue_when_no_run_since_last_due_time(): void
    {
        config(['backups.timezone' => 'UTC']);
        $job = $this->dbJob(['schedule_cron' => '0 */3 * * *']);
        $job->created_at = Carbon::parse('2026-09-26 00:00:00');
        $schedule = new JobSchedule();

        $lastRun = new BackupJobRun(['status' => 'success', 'started_at' => Carbon::parse('2026-09-26 03:00:30')]);

        // A las 06:10 todavía está dentro del margen de la ejecución de las 06:00.
        $this->assertFalse($schedule->isOverdue($job, $lastRun, Carbon::parse('2026-09-26 06:10:00')));
        // A las 06:20 ya pasó el margen y no hubo ejecución desde las 03:00.
        $this->assertTrue($schedule->isOverdue($job, $lastRun, Carbon::parse('2026-09-26 06:20:00')));
        $this->assertFalse($schedule->isOverdue($job->fill(['enabled' => false]), $lastRun, Carbon::parse('2026-09-26 06:20:00')));
    }

    public function test_multiple_expressions_give_exact_40_minute_rhythm(): void
    {
        config(['backups.timezone' => 'UTC']);
        $job = new BackupJob(['schedule_cron' => '0 */2 * * * | 40 */2 * * * | 20 1-23/2 * * *']);

        $times = array_map(fn ($d) => $d->format('H:i'), (new JobSchedule())->upcoming($job, 5, Carbon::parse('2026-09-26 23:59:00')));

        $this->assertSame(['00:00', '00:40', '01:20', '02:00', '02:40'], $times);
    }
}
