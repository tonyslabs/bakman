<?php

namespace App\Http\Controllers;

use App\Services\Tasks\QuickAdd;
use App\Services\Tasks\Recurrence;
use App\Services\Tasks\Sections;
use App\Services\Tasks\TaskConflictException;
use App\Services\Tasks\TaskRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Tareas: notas del vault de Obsidian (ver TaskRepository). Varias vistas de la misma lista y
 * varias formas de mover estado; todo termina en un cambio del frontmatter de la nota.
 */
class TaskController extends Controller
{
    // La primera es la vista por defecto de /tareas.
    public const VISTAS = [
        'tablero' => 'Tablero',
        'lista' => 'Lista',
        'agenda' => 'Agenda',
        'inbox' => 'Inbox',
        'recurrentes' => 'Recurrentes',
        'hechas' => 'Hechas',
    ];

    public const DIAS = ['lun' => 'L', 'mar' => 'M', 'mie' => 'X', 'jue' => 'J', 'vie' => 'V', 'sab' => 'S', 'dom' => 'D'];

    public function __construct(private TaskRepository $tasks)
    {
    }

    public function index(Request $request, string $vista = 'tablero')
    {
        return view('tasks.index', $this->listing($request, $vista));
    }

    /** Datos de una vista (web y API v1): tareas filtradas, agenda y contadores. */
    protected function listing(Request $request, string $vista): array
    {
        abort_unless(array_key_exists($vista, self::VISTAS), 404);

        $all = $this->tasks->all();
        $filters = array_filter($request->only(['seccion', 'area', 'prioridad', 'etiqueta', 'q', 'pospuestas']));
        if (isset($filters['seccion']) && ! array_key_exists($filters['seccion'], Sections::labels())) {
            unset($filters['seccion']);
        }
        $scoped = $this->filter($all, $filters);
        $hoy = TaskRepository::today();

        // Pospuestas (inicio en el futuro): fuera de Lista/Tablero salvo que se pidan.
        $activas = $scoped->reject(fn ($t) => $t['cerrada'] || ($t['pospuesta'] && empty($filters['pospuestas'])));

        $tasks = match ($vista) {
            'inbox' => $scoped->where('estado', 'inbox'),
            'hechas' => $scoped->where('cerrada', true)->sortByDesc('completada'),
            'recurrentes' => $scoped->whereNotNull('repite')->where('cerrada', false)->sortBy(fn ($t) => $t['vence'] ?? '9999'),
            'agenda' => $scoped->where('cerrada', false)->whereNotNull('vence')->sortBy([['vence', 'asc'], ['orden', 'asc']]),
            'tablero' => $scoped->reject(fn ($t) => $t['estado'] === 'cancelada'
                || ($t['pospuesta'] && empty($filters['pospuestas']))
                // En el tablero, "Hecha" muestra solo lo cerrado en las últimas 2 semanas.
                || ($t['estado'] === 'hecha' && ($t['completada'] ?? '') < Carbon::parse($hoy)->subDays(14)->toDateString())),
            default => $activas,
        };

        return [
            'vista' => $vista,
            'tasks' => $tasks->values(),
            'agenda' => $vista === 'agenda' ? $this->agenda($tasks, $hoy) : null,
            'filters' => $filters,
            'hoy' => $hoy,
            'etiquetas' => $all->pluck('etiquetas')->flatten()->unique()->sort()->values(),
            'available' => $this->tasks->available(),
            'path' => $this->tasks->path(),
            'counts' => [
                'lista' => $activas->count(),
                'inbox' => $scoped->where('estado', 'inbox')->count(),
                'agenda' => $scoped->where('cerrada', false)->filter(fn ($t) => $t['vence'] && $t['vence'] <= $hoy)->count(),
                'recurrentes' => $scoped->whereNotNull('repite')->where('cerrada', false)->count(),
                'vencidas' => $scoped->where('vencida', true)->count(),
                'pospuestas' => $scoped->where('pospuesta', true)->count(),
                'secciones' => collect(Sections::labels())->map(fn ($l, $s) => $all->where('seccion', $s)->where('cerrada', false)->count()),
            ],
        ];
    }

    public function store(Request $request)
    {
        $areas = array_keys(Sections::areas());

        // Captura rápida: los tokens del título (!alta #up @mañana *semanal:lun [[Nota]]) mandan sobre los selects.
        if ($request->boolean('rapida')) {
            $parsed = QuickAdd::parse((string) $request->input('titulo'), TaskRepository::today(), $areas);
            $request->merge(array_filter([
                'titulo' => $parsed['titulo'] ?? '',
                'prioridad' => $parsed['prioridad'] ?? null,
                'area' => $parsed['area'] ?? null,
                'vence' => $parsed['vence'] ?? null,
                'repite' => $parsed['repite'] ?? null,
                'proyecto' => $parsed['proyecto'] ?? null,
                'etiquetas' => implode(', ', $parsed['etiquetas'] ?? []) ?: null,
            ], fn ($v) => $v !== null) + ['titulo' => $parsed['titulo'] ?? '']);
        }

        $data = $this->validated($request, creating: true);
        $task = $this->tasks->create($data['titulo'], $data, $data['cuerpo'] ?? '');

        $extra = collect([
            $task['vence'] ? 'vence '.Carbon::parse($task['vence'])->isoFormat('ddd D MMM') : null,
            $task['repite_label'],
            $task['area_label'],
        ])->filter()->implode(' · ');

        return $request->expectsJson()
            ? response()->json(['data' => $task], 201)
            : back()->with('status', "Tarea creada: {$task['titulo']}".($extra ? " ({$extra})" : ''));
    }

    public function edit(string $task)
    {
        return view('tasks.edit', ['task' => $this->tasks->findOrFail($task)]);
    }

    public function update(Request $request, string $task)
    {
        $current = $this->tasks->findOrFail($task);
        // El formulario web arma la regla con repite_tipo/días/…; la API la manda ya hecha en `repite`.
        $request->merge(['repite' => $request->has('repite_tipo') ? $this->ruleFromForm($request) : $request->input('repite')]);
        $data = $this->validated($request);

        $changes = collect($data)->only(['estado', 'prioridad', 'area', 'proyecto', 'vence', 'inicio', 'repite', 'repite_desde', 'repite_hasta'])
            ->filter(fn ($v, $k) => $v !== ($current[$k] ?? null))
            ->all();
        if (! $data['repite'] && ! $current['repite']) {
            unset($changes['repite'], $changes['repite_desde'], $changes['repite_hasta']);
        } elseif (! $data['repite'] && array_key_exists('repite', $changes)) {
            // Sin repetición: se limpian también sus opciones.
            $changes += ['repite_desde' => null, 'repite_hasta' => null];
        }
        $etiquetas = $this->splitTags($data['etiquetas'] ?? '');
        if ($etiquetas !== $current['etiquetas']) {
            $changes['etiquetas'] = $etiquetas;
        }

        try {
            $updated = $this->tasks->update(
                $task,
                $changes,
                cuerpo: ($data['cuerpo'] ?? '') !== $current['cuerpo'] ? ($data['cuerpo'] ?? '') : null,
                hash: $request->input('hash'),
                titulo: $data['titulo'],
            );
        } catch (TaskConflictException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'errors' => ['cuerpo' => [$e->getMessage()]]], 409)
                : back()->withInput()->withErrors(['cuerpo' => $e->getMessage()]);
        }

        return $request->expectsJson()
            ? response()->json(['data' => $updated, 'mensaje' => $this->statusMessage($updated)])
            : redirect()->route('tasks.index', $request->input('volver', 'tablero'))->with('status', $this->statusMessage($updated));
    }

    /** Checkbox, menú de estado y atajos de teclado: cambia solo `estado` (y `orden`). */
    public function estado(Request $request, string $task)
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(array_keys(config('tasks.estados')))],
            'orden' => ['nullable', 'integer'],
        ]);

        $this->tasks->findOrFail($task);
        $updated = $this->tasks->move($task, $data['estado'], $data['orden'] ?? null);

        return $request->expectsJson()
            ? response()->json(['data' => $updated, 'mensaje' => $this->statusMessage($updated)])
            : back()->with('status', $this->statusMessage($updated));
    }

    /** Posponer / mover de fecha: `vence` y/o `inicio` (vacío = quitar). */
    public function fecha(Request $request, string $task)
    {
        $data = $request->validate([
            'vence' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'inicio' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'preset' => ['sometimes', Rule::in(array_values(config('tasks.posponer')))],
        ]);

        if (isset($data['preset'])) {
            $data['vence'] = Carbon::parse(TaskRepository::today())->modify($data['preset'])->toDateString();
            unset($data['preset']);
        }

        $this->tasks->findOrFail($task);
        $updated = $this->tasks->update($task, $data);
        $msg = "«{$updated['titulo']}» → ".($updated['vence'] ? Carbon::parse($updated['vence'])->isoFormat('ddd D MMM') : 'sin fecha');

        return $request->expectsJson()
            ? response()->json(['data' => $updated, 'mensaje' => $msg])
            : back()->with('status', $msg);
    }

    public function subtarea(Request $request, string $task, int $indice)
    {
        $data = $request->validate(['hecha' => ['sometimes', 'boolean']]);

        $this->tasks->findOrFail($task);
        $updated = $this->tasks->toggleSubtask($task, $indice, $data['hecha'] ?? null);

        return $request->expectsJson()
            ? response()->json(['data' => $updated])
            : back();
    }

    /** Arrastrar en el tablero: la columna destino queda con este orden. */
    public function reordenar(Request $request)
    {
        $data = $request->validate([
            'estado' => ['required', Rule::in(array_keys(config('tasks.estados')))],
            'ids' => ['present', 'array'],
            'ids.*' => ['string'],
        ]);

        $recurrieron = $this->tasks->reorder($data['estado'], $data['ids']);

        return response()->json([
            'ok' => true,
            'recurrieron' => collect($recurrieron)->map(fn ($t) => $this->statusMessage($t))->values(),
        ]);
    }

    public function destroy(Request $request, string $task)
    {
        $found = $this->tasks->findOrFail($task);
        $this->tasks->delete($task);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->route('tasks.index', $request->input('volver', 'tablero'))
            ->with('status', "Tarea borrada: {$found['titulo']}");
    }

    protected function statusMessage(array $task): string
    {
        if ($task['recurrio'] ?? null) {
            return "↻ «{$task['titulo']}» hecha ({$task['veces']}ª vez). Próxima: ".Carbon::parse($task['recurrio'])->isoFormat('ddd D MMM');
        }

        return "«{$task['titulo']}» → ".config('tasks.estados')[$task['estado']];
    }

    /** @return array<string, array{label: string, tasks: Collection}> */
    private function agenda(Collection $tasks, string $hoy): array
    {
        $d = Carbon::parse($hoy);
        $manana = $d->copy()->addDay()->toDateString();
        $semana = $d->copy()->addDays(7)->toDateString();

        $groups = [
            'vencidas' => ['label' => 'Vencidas', 'tasks' => $tasks->filter(fn ($t) => $t['vence'] < $hoy)],
            'hoy' => ['label' => 'Hoy · '.$d->isoFormat('dddd D MMM'), 'tasks' => $tasks->where('vence', $hoy)],
            'manana' => ['label' => 'Mañana · '.Carbon::parse($manana)->isoFormat('dddd D MMM'), 'tasks' => $tasks->where('vence', $manana)],
            'semana' => ['label' => 'Próximos 7 días', 'tasks' => $tasks->filter(fn ($t) => $t['vence'] > $manana && $t['vence'] <= $semana)],
            'despues' => ['label' => 'Más adelante', 'tasks' => $tasks->filter(fn ($t) => $t['vence'] > $semana)],
        ];

        return array_filter($groups, fn ($g) => $g['tasks']->isNotEmpty());
    }

    private function filter(Collection $tasks, array $filters): Collection
    {
        return $tasks
            ->when($filters['seccion'] ?? null, fn ($c, $s) => $c->where('seccion', $s))
            ->when($filters['area'] ?? null, fn ($c, $area) => $c->where('area', $area))
            ->when($filters['prioridad'] ?? null, fn ($c, $p) => $c->where('prioridad', $p))
            ->when($filters['etiqueta'] ?? null, fn ($c, $tag) => $c->filter(fn ($t) => in_array($tag, $t['etiquetas'], true)))
            ->when($filters['q'] ?? null, fn ($c, $q) => $c->filter(
                fn ($t) => mb_stripos($t['titulo'].' '.$t['cuerpo'].' '.$t['proyecto_label'].' '.implode(' ', $t['etiquetas']), $q) !== false
            ));
    }

    /** Arma la regla `repite` desde los campos del formulario (tipo, días, día del mes, cada N). */
    private function ruleFromForm(Request $request): ?string
    {
        $dias = array_values(array_intersect(array_keys(self::DIAS), (array) $request->input('repite_dias', [])));
        $diaMes = $request->input('repite_dia_mes');

        $rule = match ($request->input('repite_tipo')) {
            'diaria', 'habiles', 'anual' => $request->input('repite_tipo'),
            'semanal' => $dias ? 'semanal:'.implode(',', $dias) : 'semanal',
            'mensual' => filled($diaMes) ? 'mensual:'.$diaMes : 'mensual',
            'cada' => 'cada:'.max(1, (int) $request->input('repite_n', 1)).$request->input('repite_unidad', 'd'),
            default => null,
        };

        return Recurrence::parse($rule)?->rule();
    }

    /** @return list<string> */
    private function splitTags(?string $raw): array
    {
        $tags = array_map(fn ($t) => mb_strtolower(ltrim(trim($t), '#')), preg_split('/[\s,]+/', (string) $raw));

        return array_values(array_unique(array_filter($tags, fn ($t) => $t !== '' && $t !== 'tarea')));
    }

    private function validated(Request $request, bool $creating = false): array
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'estado' => [$creating ? 'nullable' : 'required', Rule::in(array_keys(config('tasks.estados')))],
            'prioridad' => ['nullable', Rule::in(array_keys(config('tasks.prioridades')))],
            'area' => ['nullable', Rule::in(array_keys(Sections::areas()))],
            'proyecto' => ['nullable', 'string', 'max:200'],
            'vence' => ['nullable', 'date_format:Y-m-d'],
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'repite' => ['nullable', 'string', function ($attr, $value, $fail) {
                if (filled($value) && ! Recurrence::parse($value)) {
                    $fail('La regla de repetición no es válida.');
                }
            }],
            'repite_desde' => ['nullable', Rule::in(['vence', 'completada'])],
            'repite_hasta' => ['nullable', 'date_format:Y-m-d'],
            'etiquetas' => ['nullable', 'string', 'max:300'],
            'cuerpo' => ['nullable', 'string', 'max:100000'],
        ], ['titulo.required' => 'Falta el título (¿quedó solo con tokens como !alta o @mañana?).']);

        if (isset($data['cuerpo'])) {
            $data['cuerpo'] = trim(str_replace("\r\n", "\n", $data['cuerpo']), "\n");
        }

        // "Bakman Mobile" → "[[Bakman Mobile]]" (link de Obsidian).
        if (filled($data['proyecto'] ?? null) && ! str_starts_with($data['proyecto'], '[[')) {
            $data['proyecto'] = '[['.trim($data['proyecto']).']]';
        }

        foreach (['estado', 'prioridad', 'area', 'proyecto', 'vence', 'inicio', 'repite', 'repite_hasta'] as $key) {
            $data[$key] = filled($data[$key] ?? null) ? $data[$key] : null;
        }
        $data['repite_desde'] = $data['repite'] ? ($data['repite_desde'] ?? 'vence') : null;
        if (! $data['repite']) {
            $data['repite_hasta'] = null;
        }

        if ($creating) {
            $data['etiquetas'] = $this->splitTags($data['etiquetas'] ?? '');
            $data = array_filter($data, fn ($v) => $v !== null && $v !== []);
        }

        return $data;
    }
}
