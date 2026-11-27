<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_sends_guests_to_login_and_users_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect();
        $this->followingRedirects()->get('/')->assertOk()->assertSee('Log in', false);

        $this->actingAs(User::factory()->create())->followingRedirects()->get('/')->assertOk()->assertSee('Dashboard');
    }
}
