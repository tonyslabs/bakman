<?php

namespace Tests\Feature;

use App\Jobs\RunDatabaseMigration;
use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DatabaseMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_pending_migration_and_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $source = DatabaseConnection::create(['name' => 'src', 'host' => 'a', 'port' => 3306, 'username' => 'root', 'password' => 'x']);
        $target = DatabaseConnection::create(['name' => 'dst', 'host' => 'b', 'port' => 3306, 'username' => 'root', 'password' => 'x']);

        $response = $this->actingAs($user)->post(route('database-migrations.store'), [
            'source_connection_id' => $source->id,
            'source_db_name' => 'app_db',
            'target_connection_id' => $target->id,
            'target_db_name' => 'app_db_copy',
            'tables_text' => "users\norders",
        ]);

        $response->assertRedirect(route('database-migrations.index'));

        $this->assertDatabaseHas('database_migrations', [
            'source_db_name' => 'app_db',
            'target_db_name' => 'app_db_copy',
            'status' => 'pending',
        ]);

        Queue::assertPushed(RunDatabaseMigration::class);
    }

    public function test_store_requires_valid_connections(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('database-migrations.store'), [
            'source_connection_id' => 999,
            'source_db_name' => 'app_db',
            'target_connection_id' => 999,
            'target_db_name' => 'app_db_copy',
        ]);

        $response->assertSessionHasErrors(['source_connection_id', 'target_connection_id']);
    }
}
