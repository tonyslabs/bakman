<?php

namespace Tests\Feature\Api;

use App\Jobs\RunBackupJob;
use App\Models\BackupJob;
use App\Models\BackupJobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private function scriptJob(array $attributes = []): BackupJob
    {
        return BackupJob::create($attributes + [
            'name' => 'betel-sync',
            'type' => 'script',
            'command' => 'echo hola',
            'schedule_cron' => '0 */2 * * *|40 */2 * * *',
            'retention_count' => 10,
            'enabled' => true,
        ]);
    }

    public function test_login_returns_token(): void
    {
        $user = User::factory()->create(['password' => 'secreto-123']);

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'secreto-123',
            'device_name' => 'pixel',
        ])->assertCreated()->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'email']]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rejects_bad_password(): void
    {
        $user = User::factory()->create(['password' => 'secreto-123']);

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'otra',
            'device_name' => 'pixel',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_endpoints_require_token(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/jobs')->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('pixel')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_dashboard_lists_failed_job_as_attention(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $job = $this->scriptJob();
        BackupJobRun::create([
            'backup_job_id' => $job->id, 'status' => 'failed',
            'started_at' => now(), 'finished_at' => now(), 'error_message' => 'Read-only file system',
        ]);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.status', 'attention')
            ->assertJsonPath('data.attention.0.kind', 'failed')
            ->assertJsonPath('data.attention.0.text', 'Read-only file system')
            ->assertJsonPath('data.runs_24h.failed', 1)
            ->assertJsonStructure(['data' => ['jobs', 'storage', 'upcoming', 'recent', 'inventory']]);
    }

    public function test_jobs_index_and_show(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $job = $this->scriptJob();

        $this->getJson('/api/v1/jobs')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'betel-sync')
            ->assertJsonPath('data.0.schedule', ['0 */2 * * *', '40 */2 * * *']);

        $this->getJson("/api/v1/jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.command', 'echo hola')
            ->assertJsonCount(5, 'data.upcoming');
    }

    public function test_job_runs_are_paginated(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $job = $this->scriptJob();
        foreach (range(1, 25) as $i) {
            BackupJobRun::create(['backup_job_id' => $job->id, 'status' => 'success', 'started_at' => now()]);
        }

        $this->getJson("/api/v1/jobs/{$job->id}/runs")
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 25);
    }

    public function test_run_now_dispatches_and_blocks_double_run(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());
        $job = $this->scriptJob();

        $this->postJson("/api/v1/jobs/{$job->id}/run")->assertStatus(202);
        Queue::assertPushed(RunBackupJob::class);

        BackupJobRun::create(['backup_job_id' => $job->id, 'status' => 'running', 'started_at' => now()]);
        $this->postJson("/api/v1/jobs/{$job->id}/run")->assertStatus(409);
        Queue::assertPushed(RunBackupJob::class, 1);
    }

    public function test_run_log_returns_tail_and_blocks_traversal(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $logs = sys_get_temp_dir().'/bakman-logs-'.uniqid();
        mkdir("$logs/homelab/betel", 0777, true);
        file_put_contents("$logs/homelab/betel/run.log", str_repeat('x', 2000)."\nFIN\n");
        file_put_contents(dirname($logs).'/secreto.txt', 'no');
        config(['backups.logs_path' => $logs]);

        $job = $this->scriptJob();
        $run = BackupJobRun::create([
            'backup_job_id' => $job->id, 'status' => 'success',
            'started_at' => now(), 'output_path' => 'homelab/betel/run.log',
        ]);

        $this->getJson("/api/v1/runs/{$run->id}/log?bytes=1024")
            ->assertOk()
            ->assertJsonPath('data.truncated', true)
            ->assertJsonPath('data.size_bytes', 2005);

        $run->update(['output_path' => '../secreto.txt']);
        $this->getJson("/api/v1/runs/{$run->id}/log")->assertNotFound();
    }
}
