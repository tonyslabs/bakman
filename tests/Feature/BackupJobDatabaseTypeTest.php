<?php

namespace Tests\Feature;

use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupJobDatabaseTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_database_backup_job_with_table_selection(): void
    {
        $user = User::factory()->create();
        $connection = DatabaseConnection::create([
            'name' => 'homelab-mariadb', 'host' => 'mariadb', 'port' => 3306,
            'username' => 'root', 'password' => 'x',
        ]);

        $response = $this->actingAs($user)->post(route('backup-jobs.store'), [
            'name' => 'nightly-db',
            'type' => 'database',
            'database_connection_id' => $connection->id,
            'db_name' => 'backend_manager',
            'tables_text' => "users\ntargets",
            'schema_only' => '1',
            'schedule_cron' => '0 3 * * *',
            'enabled' => '1',
        ]);

        $response->assertRedirect(route('backup-jobs.index'));

        $this->assertDatabaseHas('backup_jobs', [
            'name' => 'nightly-db',
            'type' => 'database',
            'database_connection_id' => $connection->id,
            'db_name' => 'backend_manager',
            'schema_only' => true,
        ]);

        $job = \App\Models\BackupJob::where('name', 'nightly-db')->firstOrFail();
        $this->assertSame(['users', 'targets'], $job->tables);
    }

    public function test_database_job_requires_connection(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('backup-jobs.store'), [
            'name' => 'no-connection',
            'type' => 'database',
            'db_name' => 'whatever',
            'schedule_cron' => '0 3 * * *',
        ]);

        $response->assertSessionHasErrors(['database_connection_id']);
    }
}
