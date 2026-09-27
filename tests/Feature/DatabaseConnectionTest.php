<?php

namespace Tests\Feature;

use App\Models\DatabaseConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_connections(): void
    {
        $user = User::factory()->create();
        DatabaseConnection::create([
            'name' => 'homelab-mariadb',
            'host' => 'mariadb',
            'port' => 3306,
            'username' => 'root',
            'password' => 'secret',
        ]);

        $response = $this->actingAs($user)->get(route('database-connections.index'));

        $response->assertOk()->assertSee('homelab-mariadb');
    }

    public function test_can_create_connection(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('database-connections.store'), [
            'name' => 'vps-mariadb',
            'host' => '100.84.80.22',
            'port' => 4226,
            'username' => 'root',
            'password' => 'secret-de-prueba',
        ]);

        $response->assertRedirect(route('database-connections.index'));
        $this->assertDatabaseHas('database_connections', ['name' => 'vps-mariadb', 'host' => '100.84.80.22']);

        $connection = DatabaseConnection::where('name', 'vps-mariadb')->firstOrFail();
        $this->assertSame('secret-de-prueba', $connection->password);
    }

    public function test_updating_without_password_keeps_existing_password(): void
    {
        $user = User::factory()->create();
        $connection = DatabaseConnection::create([
            'name' => 'keep-pass',
            'host' => 'mariadb',
            'port' => 3306,
            'username' => 'root',
            'password' => 'original',
        ]);

        $this->actingAs($user)->put(route('database-connections.update', $connection), [
            'name' => 'keep-pass',
            'host' => 'mariadb',
            'port' => 3306,
            'username' => 'root',
            'password' => '',
        ]);

        $this->assertSame('original', $connection->fresh()->password);
    }

    public function test_can_delete_connection(): void
    {
        $user = User::factory()->create();
        $connection = DatabaseConnection::create([
            'name' => 'to-delete',
            'host' => 'mariadb',
            'port' => 3306,
            'username' => 'root',
            'password' => 'secret',
        ]);

        $this->actingAs($user)->delete(route('database-connections.destroy', $connection));

        $this->assertDatabaseMissing('database_connections', ['id' => $connection->id]);
    }

    public function test_databases_endpoint_returns_error_for_unreachable_connection(): void
    {
        $user = User::factory()->create();
        $connection = DatabaseConnection::create([
            'name' => 'unreachable',
            'host' => 'nope.invalid',
            'port' => 3306,
            'username' => 'root',
            'password' => 'x',
        ]);

        $response = $this->actingAs($user)->getJson(route('database-connections.databases', $connection));

        $response->assertStatus(422)->assertJson(['success' => false]);
    }
}
