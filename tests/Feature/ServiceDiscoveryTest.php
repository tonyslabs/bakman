<?php

namespace Tests\Feature;

use App\Models\LabProject;
use App\Models\User;
use App\Services\ServiceDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServiceDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['lab.portainer.url' => 'http://portainer.test', 'lab.portainer.token' => 'ptr_x', 'lab.local_host' => '100.76.255.29']);
    }

    private function fakePortainer(array $homelabContainers, array $piContainers = [], int $piStatus = 1): void
    {
        Http::fake([
            'portainer.test/api/endpoints' => Http::response([
                ['Id' => 1, 'Name' => 'local', 'URL' => 'unix:///var/run/docker.sock', 'Status' => 1],
                ['Id' => 2, 'Name' => 'dariuspi', 'URL' => 'tcp://100.99.131.25:9001', 'Status' => $piStatus],
            ]),
            'portainer.test/api/endpoints/1/docker/containers/json*' => Http::response($homelabContainers),
            'portainer.test/api/endpoints/2/docker/containers/json*' => Http::response($piContainers),
        ]);
    }

    private function container(string $name, string $state = 'running', string $status = 'Up 3 hours', array $ports = [], ?string $service = null): array
    {
        return [
            'Names' => ['/'.$name], 'Image' => "img/{$name}:latest", 'State' => $state, 'Status' => $status,
            'Ports' => $ports, 'Labels' => $service ? ['com.docker.compose.service' => $service] : [],
        ];
    }

    public function test_creates_cards_per_machine_with_link_or_status_only(): void
    {
        $this->fakePortainer([
            $this->container('vikunja-vikunja-1', ports: [['IP' => '100.76.255.29', 'PrivatePort' => 3456, 'PublicPort' => 3456, 'Type' => 'tcp']], service: 'vikunja'),
            $this->container('prometheus', ports: [['IP' => '127.0.0.1', 'PrivatePort' => 9090, 'PublicPort' => 9091, 'Type' => 'tcp']]),
            $this->container('portainer', ports: [
                ['IP' => '100.76.255.29', 'PrivatePort' => 9443, 'PublicPort' => 9443, 'Type' => 'tcp'],
                ['IP' => '100.76.255.29', 'PrivatePort' => 9000, 'PublicPort' => 9000, 'Type' => 'tcp'],
            ]),
        ], [$this->container('node-exporter', status: 'Up 1 day (healthy)', ports: [['IP' => '0.0.0.0', 'PrivatePort' => 9100, 'PublicPort' => 9100, 'Type' => 'tcp']])]);

        $stats = app(ServiceDiscovery::class)->sync();

        $this->assertSame(['created' => 4, 'updated' => 0, 'removed' => 0, 'endpoints' => 2], $stats);
        $this->assertDatabaseHas('lab_projects', ['section' => 'Homelab', 'container' => 'vikunja-vikunja-1', 'name' => 'Vikunja', 'port' => 3456, 'monitor_only' => false, 'discovered' => true]);
        // Solo escucha en loopback: no enlazable, pero se ve su estado.
        $this->assertDatabaseHas('lab_projects', ['section' => 'Homelab', 'container' => 'prometheus', 'port' => null, 'monitor_only' => true]);
        // Publica HTTP y HTTPS: enlaza al HTTP.
        $this->assertDatabaseHas('lab_projects', ['container' => 'portainer', 'scheme' => 'http', 'port' => 9000]);
        $this->assertDatabaseHas('lab_projects', ['section' => 'Raspberry Pi', 'container' => 'node-exporter', 'host' => '100.99.131.25', 'container_health' => 'healthy']);
    }

    public function test_databases_are_status_only_and_get_proper_names(): void
    {
        $this->fakePortainer([
            $this->container('mariadb', ports: [['IP' => '100.76.255.29', 'PrivatePort' => 3306, 'PublicPort' => 3306, 'Type' => 'tcp']]),
            $this->container('redis_service', ports: [['IP' => '0.0.0.0', 'PrivatePort' => 6379, 'PublicPort' => 4227, 'Type' => 'tcp']]),
        ]);

        app(ServiceDiscovery::class)->sync();

        $this->assertDatabaseHas('lab_projects', ['container' => 'mariadb', 'name' => 'MariaDB', 'monitor_only' => true, 'port' => null]);
        $this->assertDatabaseHas('lab_projects', ['container' => 'redis_service', 'name' => 'Redis', 'monitor_only' => true]);
    }

    public function test_updates_existing_manual_card_instead_of_duplicating(): void
    {
        $manual = LabProject::create(['module' => 'homelab', 'section' => 'Homelab', 'name' => 'Grafana', 'scheme' => 'https', 'host' => 'dashboard.tonydev.cloud', 'container' => 'grafana']);
        $this->fakePortainer([$this->container('grafana', 'exited', 'Exited (1) 2 minutes ago')]);

        app(ServiceDiscovery::class)->sync();

        $this->assertSame(1, LabProject::count());
        $manual->refresh();
        $this->assertSame('exited', $manual->container_state);
        $this->assertSame('dashboard.tonydev.cloud', $manual->host);
        $this->assertFalse($manual->discovered);
    }

    public function test_marks_missing_containers_but_not_when_agent_is_down(): void
    {
        LabProject::create(['module' => 'homelab', 'section' => 'Homelab', 'name' => 'Viejo', 'scheme' => 'http', 'host' => 'x', 'container' => 'viejo', 'container_state' => 'running']);
        LabProject::create(['module' => 'homelab', 'section' => 'Raspberry Pi', 'name' => 'cAdvisor', 'scheme' => 'http', 'host' => 'x', 'container' => 'cadvisor', 'container_state' => 'running']);
        $this->fakePortainer([], [], piStatus: 2);

        $stats = app(ServiceDiscovery::class)->sync();

        $this->assertSame(1, $stats['removed']);
        $this->assertSame('removed', LabProject::where('container', 'viejo')->value('container_state'));
        // El agente de la Pi no responde: sus tarjetas no se tocan.
        $this->assertSame('running', LabProject::where('container', 'cadvisor')->value('container_state'));
    }

    public function test_hidden_cards_stay_hidden_after_sync_and_view_filters_them(): void
    {
        $this->fakePortainer([$this->container('uptime_kuma', ports: [['IP' => '100.76.255.29', 'PrivatePort' => 3001, 'PublicPort' => 3001, 'Type' => 'tcp']])]);
        app(ServiceDiscovery::class)->sync();
        $card = LabProject::firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('lab.projects.toggle-hidden', $card));
        app(ServiceDiscovery::class)->sync();

        $this->assertTrue($card->fresh()->hidden);
        $this->flushSession(); // el flash "Uptime Kuma oculto." también contiene el nombre
        $this->actingAs($user)->get(route('lab.show', 'homelab'))->assertDontSee('Uptime Kuma')->assertSee('Ver 1 ocultos');
        $this->actingAs($user)->get(route('lab.show', ['module' => 'homelab', 'ocultos' => 1]))->assertSee('Uptime Kuma');
    }

    public function test_sync_button_and_container_state_on_card(): void
    {
        $this->fakePortainer([$this->container('mariadb', 'restarting', 'Restarting (1) 5 seconds ago')]);

        $this->actingAs(User::factory()->create())->post(route('lab.discover'))->assertRedirect(route('lab.show', 'homelab'));
        $this->actingAs(User::factory()->create())->get(route('lab.show', 'homelab'))->assertSee('reiniciando en bucle')->assertSee('· auto');
    }
}
