<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** bakman es un panel de administración: no hay registro público, el único usuario sale del primer arranque. */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(404);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }
}
