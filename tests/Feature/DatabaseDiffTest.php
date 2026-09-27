<?php

namespace Tests\Feature;

use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseDiffTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_lists_connections(): void
    {
        $user = User::factory()->create();
        DatabaseConnection::create(['name' => 'conn-a', 'host' => 'a', 'port' => 3306, 'username' => 'root', 'password' => 'x']);
        DatabaseConnection::create(['name' => 'conn-b', 'host' => 'b', 'port' => 3306, 'username' => 'root', 'password' => 'x']);

        $response = $this->actingAs($user)->get(route('database-diff.create'));

        $response->assertOk()->assertSee('conn-a')->assertSee('conn-b');
    }

    public function test_compare_requires_valid_connection_ids(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('database-diff.compare'), [
            'connection_a_id' => 999,
            'db_a' => 'x',
            'connection_b_id' => 999,
            'db_b' => 'y',
            'sections' => ['tables'],
        ]);

        $response->assertSessionHasErrors(['connection_a_id', 'connection_b_id']);
    }

    public function test_compare_requires_database_names(): void
    {
        $user = User::factory()->create();
        $a = DatabaseConnection::create(['name' => 'conn-a', 'host' => 'a', 'port' => 3306, 'username' => 'root', 'password' => 'x']);
        $b = DatabaseConnection::create(['name' => 'conn-b', 'host' => 'b', 'port' => 3306, 'username' => 'root', 'password' => 'x']);

        $response = $this->actingAs($user)->post(route('database-diff.compare'), [
            'connection_a_id' => $a->id,
            'connection_b_id' => $b->id,
            'sections' => ['tables'],
        ]);

        $response->assertSessionHasErrors(['db_a', 'db_b']);
    }

    public function test_compare_requires_at_least_one_section(): void
    {
        $user = User::factory()->create();
        $a = DatabaseConnection::create(['name' => 'conn-a', 'host' => 'a', 'port' => 3306, 'username' => 'root', 'password' => 'x']);
        $b = DatabaseConnection::create(['name' => 'conn-b', 'host' => 'b', 'port' => 3306, 'username' => 'root', 'password' => 'x']);

        $response = $this->actingAs($user)->post(route('database-diff.compare'), [
            'connection_a_id' => $a->id,
            'db_a' => 'db_a',
            'connection_b_id' => $b->id,
            'db_b' => 'db_b',
        ]);

        $response->assertSessionHasErrors(['sections']);
    }
}
