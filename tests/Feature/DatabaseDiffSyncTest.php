<?php

namespace Tests\Feature;

use App\Jobs\RunDatabaseDiffSync;
use App\Models\DatabaseConnection;
use App\Models\DatabaseDiffSync;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DatabaseDiffSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_queues_a_sync_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $a = DatabaseConnection::create(['name' => 'conn-a', 'host' => 'a', 'port' => 3306, 'username' => 'root', 'password' => 'x']);
        $b = DatabaseConnection::create(['name' => 'conn-b', 'host' => 'b', 'port' => 3306, 'username' => 'root', 'password' => 'x']);

        $response = $this->actingAs($user)->post(route('database-diff-syncs.store'), [
            'connection_a_id' => $a->id,
            'db_a' => 'db_a',
            'connection_b_id' => $b->id,
            'db_b' => 'db_b',
            'section' => 'tables',
            'source_side' => 'a',
        ]);

        $response->assertRedirect(route('database-diff-syncs.index'));
        $this->assertDatabaseHas('database_diff_syncs', [
            'connection_a_id' => $a->id,
            'connection_b_id' => $b->id,
            'section' => 'tables',
            'source_side' => 'a',
            'status' => 'pending',
        ]);
        Queue::assertPushed(RunDatabaseDiffSync::class);
    }

    public function test_store_requires_valid_section_and_source_side(): void
    {
        $user = User::factory()->create();
        $a = DatabaseConnection::create(['name' => 'conn-a', 'host' => 'a', 'port' => 3306, 'username' => 'root', 'password' => 'x']);
        $b = DatabaseConnection::create(['name' => 'conn-b', 'host' => 'b', 'port' => 3306, 'username' => 'root', 'password' => 'x']);

        $response = $this->actingAs($user)->post(route('database-diff-syncs.store'), [
            'connection_a_id' => $a->id,
            'db_a' => 'db_a',
            'connection_b_id' => $b->id,
            'db_b' => 'db_b',
            'section' => 'bogus',
            'source_side' => 'c',
        ]);

        $response->assertSessionHasErrors(['section', 'source_side']);
    }

    public function test_index_lists_syncs(): void
    {
        $user = User::factory()->create();
        $a = DatabaseConnection::create(['name' => 'conn-a', 'host' => 'a', 'port' => 3306, 'username' => 'root', 'password' => 'x']);
        $b = DatabaseConnection::create(['name' => 'conn-b', 'host' => 'b', 'port' => 3306, 'username' => 'root', 'password' => 'x']);

        DatabaseDiffSync::create([
            'connection_a_id' => $a->id, 'db_a' => 'db_a',
            'connection_b_id' => $b->id, 'db_b' => 'db_b',
            'section' => 'tables', 'source_side' => 'a', 'status' => 'success',
            'summary' => ['CREATE TABLE foo'],
        ]);

        $response = $this->actingAs($user)->get(route('database-diff-syncs.index'));

        $response->assertOk()->assertSee('conn-a')->assertSee('conn-b')->assertSee('CREATE TABLE foo');
    }
}
