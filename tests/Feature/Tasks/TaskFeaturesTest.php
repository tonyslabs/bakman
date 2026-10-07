<?php

namespace Tests\Feature\Tasks;

use App\Models\User;
use App\Services\Tasks\TaskRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Secciones, recurrencias, subtareas, posponer, agenda y captura rápida. Hoy = martes 2026-10-06 (Managua). */
class TaskFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private TaskRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-06 15:00', 'America/Managua'));
        $this->dir = sys_get_temp_dir().'/tasks-feat-'.uniqid();
        mkdir($this->dir);
        config(['tasks.path' => $this->dir, 'tasks.file_uid' => null, 'backups.timezone' => 'America/Managua']);
        $this->repo = new TaskRepository($this->dir);
        $this->app->instance(TaskRepository::class, $this->repo);
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function note(string $id): string
    {
        return file_get_contents($this->dir."/{$id}.md");
    }

    public function test_today_uses_managua_even_late_at_night_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 22:30', 'America/Managua')); // 04:30 UTC del 7
        $this->assertSame('2026-10-06', TaskRepository::today());
    }

    public function test_completing_a_recurring_task_reschedules_it(): void
    {
        $this->repo->create('Pagar internet', ['estado' => 'pendiente', 'vence' => '2026-10-05', 'repite' => 'mensual:5'],
            "Contexto\n\n- [x] entrar al portal\n- [x] pagar\n");

        $t = $this->repo->move('Pagar internet', 'hecha');

        $this->assertSame('2026-11-05', $t['recurrio']);
        $this->assertSame(['pendiente', '2026-11-05', null, 1, '2026-10-06'], [$t['estado'], $t['vence'], $t['completada'], $t['veces'], $t['ultima']]);
        $this->assertSame([0, 2], $t['progreso'], 'las subtareas se desmarcan');
        $this->assertStringContainsString("## Registro\n\n- ✓ 2026-10-06\n", $this->note('Pagar internet'));
        $this->assertStringContainsString("Contexto\n\n- [ ] entrar al portal", $this->note('Pagar internet'));

        // Segunda vez: la nueva línea va arriba en el registro.
        $t = $this->repo->move('Pagar internet', 'hecha');
        $this->assertSame([2, '2026-12-05'], [$t['veces'], $t['vence']]);
        $this->assertSame(2, substr_count($this->note('Pagar internet'), '- ✓ 2026-10-06'));
    }

    public function test_recurrence_from_completion_date_and_until_limit(): void
    {
        $this->repo->create('Cambiar filtro', ['estado' => 'pendiente', 'vence' => '2026-09-01', 'repite' => 'cada:3d', 'repite_desde' => 'completada']);
        $this->assertSame('2026-10-09', $this->repo->move('Cambiar filtro', 'hecha')['vence']);

        $this->repo->create('Curso', ['estado' => 'pendiente', 'vence' => '2026-10-06', 'repite' => 'semanal', 'repite_hasta' => '2026-10-10']);
        $t = $this->repo->move('Curso', 'hecha');
        $this->assertNull($t['recurrio']);
        $this->assertSame(['hecha', '2026-10-06'], [$t['estado'], $t['completada']], 'pasada la fecha límite se cierra');
    }

    public function test_dragging_a_recurring_card_to_done_reports_it(): void
    {
        $this->repo->create('Reporte semanal', ['estado' => 'pendiente', 'vence' => '2026-10-05', 'repite' => 'semanal:lun']);

        $this->postJson('/tareas/reordenar', ['estado' => 'hecha', 'ids' => ['Reporte semanal']])
            ->assertOk()->assertJsonCount(1, 'recurrieron');
        $this->assertSame(['pendiente', '2026-10-12'], [$this->repo->find('Reporte semanal')['estado'], $this->repo->find('Reporte semanal')['vence']]);
    }

    public function test_toggle_subtask_changes_only_that_line_and_ignores_registro(): void
    {
        $this->repo->create('Con pasos', [], "- [ ] uno\n* [ ] dos\n\n".TaskRepository::REGISTRO."\n\n- [ ] no es subtarea\n");

        $this->patchJson('/tareas/Con%20pasos/subtareas/1', ['hecha' => true])->assertOk()->assertJsonPath('data.progreso', [1, 2]);
        $this->assertStringContainsString("- [ ] uno\n* [x] dos\n", $this->note('Con pasos'));
        $this->patchJson('/tareas/Con%20pasos/subtareas/2')->assertNotFound();
        $this->patchJson('/tareas/Con%20pasos/subtareas/1')->assertOk()->assertJsonPath('data.progreso', [0, 2]);
    }

    public function test_postpone_presets_and_dates(): void
    {
        $this->repo->create('Llamar', ['estado' => 'pendiente']);

        $this->patchJson('/tareas/Llamar/fecha', ['preset' => '+1 day'])->assertOk()->assertJsonPath('data.vence', '2026-10-07');
        $this->patchJson('/tareas/Llamar/fecha', ['preset' => 'next monday'])->assertOk()->assertJsonPath('data.vence', '2026-10-12');
        $this->patchJson('/tareas/Llamar/fecha', ['vence' => null])->assertOk()->assertJsonPath('data.vence', null);
        $this->patchJson('/tareas/Llamar/fecha', ['preset' => 'rm -rf'])->assertUnprocessable();
    }

    public function test_sections_filter_and_snoozed_tasks(): void
    {
        $this->repo->create('Del trabajo', ['estado' => 'pendiente', 'area' => 'campos']);
        $this->repo->create('Del homelab', ['estado' => 'pendiente', 'area' => 'homelab']);
        $this->repo->create('Para después', ['estado' => 'pendiente', 'area' => 'casa', 'inicio' => '2026-11-01']);

        $this->get('/tareas/lista?seccion=trabajo')->assertOk()->assertSee('Del trabajo')->assertDontSee('Del homelab');
        $this->get('/tareas/lista?seccion=personal')->assertOk()->assertSee('Homelab e infra')->assertDontSee('Del trabajo')->assertDontSee('Para después');
        $this->get('/tareas/lista?seccion=personal&pospuestas=1')->assertOk()->assertSee('Para después');
        $this->get('/tareas/lista?seccion=inventada')->assertOk()->assertSee('Del trabajo');
    }

    public function test_agenda_and_recurrentes_views(): void
    {
        $this->repo->create('Atrasada', ['estado' => 'pendiente', 'vence' => '2026-10-01']);
        $this->repo->create('Para hoy', ['estado' => 'pendiente', 'vence' => '2026-10-06']);
        $this->repo->create('Para el jueves', ['estado' => 'pendiente', 'vence' => '2026-10-08']);
        $this->repo->create('Sin fecha', ['estado' => 'pendiente']);
        $this->repo->create('Gimnasio', ['estado' => 'pendiente', 'vence' => '2026-10-07', 'repite' => 'semanal:lun,mie,vie']);

        $this->get('/tareas/agenda')->assertOk()
            ->assertSeeInOrder(['Vencidas', 'Atrasada', 'Hoy', 'Para hoy', 'Mañana', 'Gimnasio', 'Próximos 7 días', 'Para el jueves'])
            ->assertDontSee('Sin fecha');
        $this->get('/tareas/recurrentes')->assertOk()->assertSee('Gimnasio')->assertSee('Cada semana: lun, mié y vie')->assertDontSee('Atrasada');
    }

    public function test_quick_add_tokens_end_to_end(): void
    {
        $this->post('/tareas', ['rapida' => 1, 'estado' => 'pendiente', 'area' => 'personal', 'prioridad' => 'media',
            'titulo' => 'Pagar luz #finanzas !alta *mensual:20 #casa-nueva [[Gastos fijos]]'])
            ->assertRedirect()->assertSessionHas('status');

        $t = $this->repo->find('Pagar luz');
        $this->assertSame(['finanzas', 'alta', 'mensual:20', '2026-10-20', '[[Gastos fijos]]', ['casa-nueva']],
            [$t['area'], $t['prioridad'], $t['repite'], $t['vence'], $t['proyecto'], $t['etiquetas']]);
        $this->assertStringContainsString('tags: [tarea, casa-nueva]', $this->note('Pagar luz'));

        $this->from('/tareas')->post('/tareas', ['rapida' => 1, 'titulo' => '!alta @mañana'])->assertSessionHasErrors('titulo');
    }

    public function test_edit_form_builds_and_clears_recurrence(): void
    {
        $task = $this->repo->create('Revisar backups', ['estado' => 'pendiente', 'area' => 'homelab']);
        $base = ['titulo' => 'Revisar backups', 'estado' => 'pendiente', 'area' => 'homelab', 'prioridad' => 'media', 'cuerpo' => '', 'hash' => $task['hash']];

        $this->get('/tareas/Revisar%20backups/editar')->assertOk()->assertSee('Repetición');
        $this->put('/tareas/Revisar%20backups', $base + ['repite_tipo' => 'semanal', 'repite_dias' => ['vie', 'lun'], 'repite_desde' => 'completada', 'etiquetas' => '#infra, Pi'])
            ->assertRedirect();
        $t = $this->repo->find('Revisar backups');
        $this->assertSame(['semanal:lun,vie', 'completada', ['infra', 'pi']], [$t['repite'], $t['repite_desde'], $t['etiquetas']]);

        $this->get('/tareas/Revisar%20backups/editar')->assertOk()->assertSee('value="lun"', false);

        $this->put('/tareas/Revisar%20backups', array_merge($base, ['hash' => $t['hash'], 'repite_tipo' => '', 'etiquetas' => 'infra, pi']))->assertRedirect();
        $t = $this->repo->find('Revisar backups');
        $this->assertNull($t['repite']);
        $this->assertStringNotContainsString('repite: ', $this->note('Revisar backups'));

        // Una tarea sin repetición no gana claves vacías al guardarla.
        $this->repo->create('Simple', ['estado' => 'pendiente']);
        $simple = $this->repo->find('Simple');
        $this->put('/tareas/Simple', ['titulo' => 'Simple', 'estado' => 'en-curso', 'area' => 'personal', 'prioridad' => 'media', 'cuerpo' => '', 'hash' => $simple['hash']]);
        $this->assertStringNotContainsString('repite', $this->note('Simple'));
        $this->assertStringNotContainsString('inicio', $this->note('Simple'));
    }
}
