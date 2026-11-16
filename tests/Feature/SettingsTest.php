<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function save(array $settings, array $extra = [])
    {
        return $this->actingAs(User::factory()->create())->put(route('settings.update'), ['settings' => $settings] + $extra);
    }

    public function test_guest_cannot_open_settings(): void
    {
        $this->get(route('settings.edit'))->assertRedirect(route('login'));
    }

    public function test_saved_values_override_config_and_secrets_are_encrypted(): void
    {
        $this->save([
            'portainer_url' => 'http://portainer.test:9000',
            'portainer_token' => 'ptr_secreto',
            'lab_sections' => "10.0.0.1 = Homelab\n10.0.0.2 = Servidor",
            'lab_https_ports' => '443, 9443',
        ])->assertRedirect(route('settings.edit'));

        $this->assertNotSame('ptr_secreto', Setting::find('portainer.token')->value);
        $this->assertTrue(Setting::find('portainer.token')->encrypted);

        app(AppSettings::class)->apply();
        $this->assertSame('http://portainer.test:9000', config('lab.portainer.url'));
        $this->assertSame('ptr_secreto', config('lab.portainer.token'));
        $this->assertSame(['10.0.0.1' => 'Homelab', '10.0.0.2' => 'Servidor'], config('lab.sections_by_host'));
        $this->assertSame([443, 9443], config('lab.https_ports'));
    }

    public function test_empty_text_goes_back_to_default_and_empty_secret_keeps_the_stored_one(): void
    {
        $this->save(['portainer_url' => 'http://x', 'portainer_token' => 'ptr_1']);
        $this->save(['portainer_url' => '', 'portainer_token' => '']);

        $this->assertNull(Setting::find('portainer.url'));
        $this->assertNotNull(Setting::find('portainer.token'));

        $this->save(['portainer_token' => ''], ['clear' => ['portainer_token' => '1']]);
        $this->assertNull(Setting::find('portainer.token'));
    }

    public function test_form_never_prints_the_secret(): void
    {
        $this->save(['portainer_token' => 'ptr_secreto']);

        $this->actingAs(User::factory()->create())->get(route('settings.edit'))
            ->assertOk()->assertDontSee('ptr_secreto')->assertSee('Quitar el token guardado');
    }

    public function test_validates_integers_and_timezone(): void
    {
        $this->save(['jobs_timeout' => 'abc'])->assertSessionHasErrors('settings.jobs_timeout');
        $this->save(['general_timezone' => 'Marte/Olympus'])->assertSessionHasErrors('settings.general_timezone');
    }

    public function test_unreadable_secret_after_app_key_change_falls_back_silently(): void
    {
        Setting::create(['key' => 'ntfy.token', 'value' => 'no-es-un-cifrado-valido', 'encrypted' => true]);
        config(['notify.ntfy.token' => 'del-env']);

        app(AppSettings::class)->apply();

        $this->assertSame('del-env', config('notify.ntfy.token'));
    }
}
