<?php

namespace Tests\Feature;

use App\Exceptions\ScriptFailedException;
use App\Jobs\RunBackupJob;
use App\Models\BackupJob;
use App\Models\BackupJobRun;
use App\Models\Target;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ScriptJobTest extends TestCase
{
    use RefreshDatabase;

    private function target(): Target
    {
        return Target::create(['name' => 'homelab', 'hostname' => '100.76.255.29', 'ssh_port' => 22, 'ssh_user' => 'darius']);
    }

    public function test_can_create_script_job(): void
    {
        $target = $this->target();

        $this->actingAs(User::factory()->create())->post(route('backup-jobs.store'), [
            'name' => 'betel-sync',
            'type' => 'script',
            'target_id' => $target->id,
            'command' => 'cd /home/darius/scripts/betel && .venv/bin/python sync_woocommerce.py',
            'timeout_seconds' => 1800,
            'schedule_cron' => '0 */6 * * *',
            'enabled' => '1',
        ])->assertRedirect(route('backup-jobs.index'));

        $this->assertDatabaseHas('backup_jobs', ['name' => 'betel-sync', 'type' => 'script', 'timeout_seconds' => 1800]);
    }

    public function test_script_job_requires_target_command_and_sane_timeout(): void
    {
        $this->actingAs(User::factory()->create())->post(route('backup-jobs.store'), [
            'name' => 'x', 'type' => 'script', 'schedule_cron' => '* * * * *', 'timeout_seconds' => 99999,
        ])->assertSessionHasErrors(['target_id', 'command', 'timeout_seconds']);
    }

    public function test_script_logs_live_under_logs_root_by_target(): void
    {
        config(['backups.logs_path' => '/data/logs']);
        $job = BackupJob::create([
            'name' => 'betel-sync', 'type' => 'script', 'target_id' => $this->target()->id,
            'command' => 'true', 'schedule_cron' => '* * * * *', 'enabled' => true,
        ]);

        $this->assertSame('/data/logs/homelab/betel-sync', app(BackupService::class)->jobDirectory($job));
    }

    public function test_failed_script_keeps_its_log_on_the_run(): void
    {
        $root = sys_get_temp_dir().'/logs-'.uniqid();
        config(['backups.logs_path' => $root]);
        $job = BackupJob::create([
            'name' => 'betel-sync', 'type' => 'script', 'target_id' => $this->target()->id,
            'command' => 'false', 'schedule_cron' => '* * * * *', 'enabled' => true,
        ]);
        $log = $root.'/homelab/betel-sync/betel-sync_2026-09-26_210000.log';
        File::ensureDirectoryExists(dirname($log));
        file_put_contents($log, "boom\n");

        $service = new class extends BackupService {
            public string $log;

            public function runScript(BackupJob $job): string
            {
                throw new ScriptFailedException('El script salió con código 1: boom', $this->log);
            }
        };
        $service->log = $log;

        (new RunBackupJob($job))->handle($service);

        $run = BackupJobRun::firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertSame('homelab/betel-sync/betel-sync_2026-09-26_210000.log', $run->output_path);
        $this->assertStringContainsString('boom', $run->error_message);

        File::deleteDirectory($root);
    }

    public function test_interrupted_job_closes_its_open_run(): void
    {
        $job = BackupJob::create([
            'name' => 'betel-sync', 'type' => 'script', 'target_id' => $this->target()->id,
            'command' => 'true', 'schedule_cron' => '* * * * *', 'enabled' => true,
        ]);
        BackupJobRun::create(['backup_job_id' => $job->id, 'status' => 'running', 'started_at' => now()->subMinutes(5)]);
        BackupJobRun::create(['backup_job_id' => $job->id, 'status' => 'success', 'started_at' => now()->subHour()]);

        (new RunBackupJob($job))->failed(new \Illuminate\Queue\MaxAttemptsExceededException('x'));

        $this->assertSame(0, BackupJobRun::where('status', 'running')->count());
        $this->assertStringContainsString('Interrumpido', BackupJobRun::where('status', 'failed')->value('error_message'));
        $this->assertSame(1, BackupJobRun::where('status', 'success')->count());
    }
}
