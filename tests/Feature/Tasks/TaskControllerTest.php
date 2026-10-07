<?php

namespace Tests\Feature\Tasks;

use App\Models\User;
use App\Services\Tasks\TaskRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private TaskRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/tasks-http-'.uniqid();
        mkdir($this->dir);
        config(['tasks.path' => $this->dir, 'tasks.file_uid' => null]);
        $this->repo = new TaskRepository($this->dir);
        $this->app->instance(TaskRepository::class, $this->repo);
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_every_view_renders_with_tasks(): void
    {
        $this->repo->create('Alta vencida', ['estado' => 'pendiente', 'prioridad' => 'alta', 'area' => 'up', 'vence' => '2020-01-01', 'proyecto' => '[[Bakman Mobile]]']);
        $this->repo->create('En inbox');
        $this->repo->create('Ya hecha', ['estado' => 'hecha']);

        $this->get('/tareas')->assertOk()->assertSee('data-col="pendiente"', false)->assertSee('Ya hecha');
        $this->get('/tareas/lista')->assertOk()->assertSee('Alta vencida')->assertSee('Bakman Mobile')->assertDontSee('Ya hecha');
        $this->get('/tareas/tablero')->assertOk()->assertSee('Alta vencida')->assertSee('Ya hecha')->assertSee('data-col="en-curso"', false);
        $this->get('/tareas/inbox')->assertOk()->assertSee('En inbox')->assertDontSee('Alta vencida');
        $this->get('/tareas/hechas')->assertOk()->assertSee('Ya hecha')->assertDontSee('En inbox');
        $this->get('/tareas/lista?area=personal')->assertOk()->assertSee('En inbox')->assertDontSee('Alta vencida');
        $this->get('/tareas/lista?q=vencida')->assertOk()->assertSee('Alta vencida')->assertDontSee('En inbox');
        $this->get('/tareas/otra')->assertClientError();
    }

    public function test_quick_add_creates_the_note(): void
    {
        $this->post('/tareas', ['titulo' => 'Comprar disco: 2TB', 'estado' => 'inbox', 'area' => 'personal', 'proyecto' => 'Backend Manager (homelab)'])
            ->assertRedirect();

        $task = $this->repo->find('Comprar disco 2TB');
        $this->assertSame('inbox', $task['estado']);
        $this->assertSame('[[Backend Manager (homelab)]]', $task['proyecto']);
    }

    public function test_estado_endpoint_changes_only_the_frontmatter(): void
    {
        $task = $this->repo->create('Mover', ['estado' => 'pendiente'], "Cuerpo que no se toca\n");

        $this->patchJson('/tareas/'.rawurlencode('Mover').'/estado', ['estado' => 'hecha'])
            ->assertOk()->assertJsonPath('data.estado', 'hecha');

        $this->assertStringContainsString("Cuerpo que no se toca\n", file_get_contents($this->dir.'/Mover.md'));
        $this->assertSame(TaskRepository::today(), $this->repo->find('Mover')['completada']);
        $this->patchJson('/tareas/Mover/estado', ['estado' => 'nada'])->assertUnprocessable();
        $this->patchJson('/tareas/No%20existe/estado', ['estado' => 'hecha'])->assertNotFound();
    }

    public function test_reordenar_moves_and_orders_the_column(): void
    {
        $this->repo->create('A', ['estado' => 'pendiente']);
        $this->repo->create('B', ['estado' => 'en-curso']);

        $this->postJson('/tareas/reordenar', ['estado' => 'en-curso', 'ids' => ['A', 'B']])->assertOk();

        $this->assertSame(['en-curso', 10], [$this->repo->find('A')['estado'], $this->repo->find('A')['orden']]);
        $this->assertSame(20, $this->repo->find('B')['orden']);
    }

    public function test_edit_and_update_with_rename_and_conflict(): void
    {
        $task = $this->repo->create('Original', ['estado' => 'pendiente'], "Notas\n");
        $this->get('/tareas/Original/editar')->assertOk()->assertSee('Notas');

        $this->put('/tareas/Original', [
            'titulo' => 'Renombrada', 'estado' => 'en-curso', 'prioridad' => 'alta', 'area' => 'up',
            'cuerpo' => "Notas nuevas\r\n", 'hash' => $task['hash'], 'volver' => 'tablero',
        ])->assertRedirect('/tareas/tablero');

        $this->assertNull($this->repo->find('Original'));
        $renamed = $this->repo->find('Renombrada');
        $this->assertSame(['en-curso', 'alta', 'up', 'Notas nuevas'], [$renamed['estado'], $renamed['prioridad'], $renamed['area'], $renamed['cuerpo']]);

        // Alguien edita la nota en Obsidian entre que se abre el formulario y se guarda.
        file_put_contents($this->dir.'/Renombrada.md', file_get_contents($this->dir.'/Renombrada.md')."Línea agregada en Obsidian\n");
        $this->from('/tareas/Renombrada/editar')->put('/tareas/Renombrada', [
            'titulo' => 'Renombrada', 'estado' => 'en-curso', 'cuerpo' => 'Pisaría', 'hash' => $renamed['hash'],
        ])->assertRedirect('/tareas/Renombrada/editar')->assertSessionHasErrors('cuerpo');
        $this->assertStringContainsString('Línea agregada en Obsidian', file_get_contents($this->dir.'/Renombrada.md'));
    }

    public function test_destroy_and_path_traversal(): void
    {
        $this->repo->create('Borrar');
        $this->delete('/tareas/Borrar')->assertRedirect('/tareas/tablero');
        $this->assertFileDoesNotExist($this->dir.'/Borrar.md');

        $this->get('/tareas/..%2F..%2Fetc%2Fpasswd/editar')->assertNotFound();
        $this->get('/tareas/.hidden/editar')->assertNotFound();
    }

    public function test_guests_are_redirected(): void
    {
        auth()->logout();
        $this->get('/tareas')->assertRedirect('/login');
    }
}
