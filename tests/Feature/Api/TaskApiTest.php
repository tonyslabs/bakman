<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Tasks\TaskRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private TaskRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 15:00', 'America/Managua'));
        $this->dir = sys_get_temp_dir().'/tasks-api-'.uniqid();
        mkdir($this->dir);
        config(['tasks.path' => $this->dir, 'tasks.file_uid' => null, 'backups.timezone' => 'America/Managua']);
        $this->repo = new TaskRepository($this->dir);
        $this->app->instance(TaskRepository::class, $this->repo);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/v1/tasks')->assertUnauthorized();
        $this->getJson('/api/v1/tasks/config')->assertUnauthorized();
    }

    public function test_config_and_listing(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->repo->create('Del trabajo', ['estado' => 'pendiente', 'area' => 'up', 'vence' => '2026-10-06']);
        $this->repo->create('Personal', ['estado' => 'en-curso', 'area' => 'casa']);

        $this->getJson('/api/v1/tasks/config')->assertOk()
            ->assertJsonPath('data.estados.en-curso', 'En curso')
            ->assertJsonPath('data.secciones.0.key', 'trabajo')
            ->assertJsonPath('data.posponer.1.preset', '+1 day');

        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('meta.vista', 'tablero')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/tasks?seccion=trabajo')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.titulo', 'Del trabajo');
        $this->getJson('/api/v1/tasks?vista=agenda')->assertOk()
            ->assertJsonPath('meta.agenda.0.key', 'hoy')->assertJsonPath('meta.agenda.0.ids', ['Del trabajo']);
        $this->getJson('/api/v1/tasks?vista=nada')->assertNotFound();
    }

    public function test_actions(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/tasks', ['rapida' => 1, 'titulo' => 'Pagar luz #finanzas !alta *mensual:20', 'estado' => 'pendiente'])
            ->assertCreated()->assertJsonPath('data.area', 'finanzas')->assertJsonPath('data.vence', '2026-10-20');

        $id = rawurlencode('Pagar luz');
        $this->getJson("/api/v1/tasks/{$id}")->assertOk()->assertJsonPath('data.repite_label', 'Cada mes, día 20');
        $this->patchJson("/api/v1/tasks/{$id}/estado", ['estado' => 'hecha'])->assertOk()
            ->assertJsonPath('data.recurrio', '2026-11-20')->assertJsonPath('data.estado', 'pendiente');
        $this->patchJson("/api/v1/tasks/{$id}/fecha", ['preset' => '+1 day'])->assertOk()->assertJsonPath('data.vence', '2026-10-07');

        $task = $this->repo->find('Pagar luz');
        $this->putJson("/api/v1/tasks/{$id}", [
            'titulo' => 'Pagar la luz', 'estado' => 'en-curso', 'prioridad' => 'baja', 'area' => 'casa',
            'repite' => 'cada:2s', 'repite_desde' => 'completada', 'etiquetas' => 'servicios',
            'cuerpo' => "- [ ] pagar\n", 'hash' => $task['hash'],
        ])->assertOk()->assertJsonPath('data.titulo', 'Pagar la luz')->assertJsonPath('data.repite', 'cada:2s')
            ->assertJsonPath('data.etiquetas', ['servicios'])->assertJsonPath('data.progreso', [0, 1]);

        $new = rawurlencode('Pagar la luz');
        $this->patchJson("/api/v1/tasks/{$new}/subtareas/0")->assertOk()->assertJsonPath('data.progreso', [1, 1]);

        // Conflicto: la nota cambió desde que se cargó.
        $this->putJson("/api/v1/tasks/{$new}", ['titulo' => 'Pagar la luz', 'estado' => 'en-curso', 'cuerpo' => 'otro', 'hash' => $task['hash']])
            ->assertStatus(409);

        $this->postJson('/api/v1/tasks/reordenar', ['estado' => 'bloqueada', 'ids' => ['Pagar la luz']])->assertOk()->assertJsonPath('recurrieron', []);
        $this->deleteJson("/api/v1/tasks/{$new}")->assertNoContent();
        $this->getJson("/api/v1/tasks/{$new}")->assertNotFound();
    }
}
