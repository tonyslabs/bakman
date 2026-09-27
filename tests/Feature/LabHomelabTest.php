<?php

namespace Tests\Feature;

use App\Models\LabProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LabHomelabTest extends TestCase
{
    use RefreshDatabase;

    public function test_homelab_groups_cards_by_section(): void
    {
        LabProject::create(['module' => 'homelab', 'section' => 'Raspberry Pi', 'name' => 'cAdvisor', 'scheme' => 'http', 'host' => '100.99.131.25', 'port' => 8082]);
        LabProject::create(['module' => 'homelab', 'section' => 'Homelab', 'name' => 'Nextcloud', 'scheme' => 'http', 'host' => '100.76.255.29', 'port' => 8090]);

        $this->actingAs(User::factory()->create())->get(route('lab.show', 'homelab'))
            ->assertOk()
            ->assertSeeInOrder(['Raspberry Pi', 'cAdvisor', 'Homelab', 'Nextcloud']);
    }

    public function test_can_save_section(): void
    {
        $this->actingAs(User::factory()->create())->post(route('lab.projects.store', 'homelab'), [
            'section' => 'VPS', 'name' => 'Divia', 'scheme' => 'https', 'host' => 'divia.tonydev.cloud',
        ])->assertRedirect(route('lab.show', 'homelab'));

        $this->assertDatabaseHas('lab_projects', ['name' => 'Divia', 'section' => 'VPS']);
    }

    public function test_monitor_only_card_is_not_a_link_and_loopback_is_not_checked(): void
    {
        $agent = LabProject::create(['module' => 'homelab', 'section' => 'VPS', 'name' => 'Portainer Agent', 'scheme' => 'https', 'host' => '100.84.80.22', 'port' => 9001, 'path' => '/ping', 'monitor_only' => true]);
        $local = LabProject::create(['module' => 'develop', 'name' => 'Divia local', 'scheme' => 'http', 'host' => '127.0.0.1', 'port' => 8000]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('lab.show', 'homelab'))
            ->assertOk()->assertSee('solo estado')->assertDontSee('href="https://100.84.80.22:9001/ping"', false);
        $this->actingAs($user)->get(route('lab.show', 'develop'))->assertSee('en tu equipo')->assertSee("mode: 'no-cors'", false);
        $this->actingAs($user)->getJson(route('lab.projects.ping', $local))->assertJson(['up' => null]);
    }

    public function test_ping_to_itself_is_up_without_http(): void
    {
        config(['app.url' => 'http://100.76.255.29:8091']);
        Http::fake();
        $self = LabProject::create(['module' => 'homelab', 'name' => 'Backend Manager', 'scheme' => 'http', 'host' => '100.76.255.29', 'port' => 8091]);

        $this->actingAs(User::factory()->create())->getJson(route('lab.projects.ping', $self))->assertJson(['up' => true]);
        Http::assertNothingSent();
    }

    public function test_ping_reports_up_for_any_http_answer_and_down_on_errors(): void
    {
        $user = User::factory()->create();
        $up = LabProject::create(['module' => 'homelab', 'name' => 'A', 'scheme' => 'http', 'host' => 'up.test']);
        $login = LabProject::create(['module' => 'homelab', 'name' => 'B', 'scheme' => 'http', 'host' => 'login.test']);
        $down = LabProject::create(['module' => 'homelab', 'name' => 'C', 'scheme' => 'http', 'host' => 'down.test']);

        Http::fake([
            'up.test*' => Http::response('ok', 200),
            'login.test*' => Http::response('', 302),
            'down.test*' => Http::response('', 502),
        ]);

        $this->actingAs($user)->getJson(route('lab.projects.ping', $up))->assertJson(['up' => true, 'status' => 200]);
        $this->actingAs($user)->getJson(route('lab.projects.ping', $login))->assertJson(['up' => true, 'status' => 302]);
        $this->actingAs($user)->getJson(route('lab.projects.ping', $down))->assertJson(['up' => false, 'status' => 502]);
    }
}
