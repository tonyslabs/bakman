<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\TaskController as WebTaskController;
use App\Services\Tasks\Sections;
use App\Services\Tasks\TaskRepository;
use Illuminate\Http\Request;

/**
 * Tareas para bakman-mobile. Misma lógica que la web (hereda de su controlador): las acciones
 * (crear, estado, fecha, subtareas, reordenar, editar, borrar) ya responden JSON con Accept: json.
 */
class TaskController extends WebTaskController
{
    /** GET /tasks?vista=tablero|lista|agenda|inbox|recurrentes|hechas&seccion=&area=&prioridad=&etiqueta=&q=&pospuestas= */
    public function index(Request $request, string $vista = 'tablero')
    {
        $listing = $this->listing($request, $request->input('vista', $vista));

        return response()->json([
            'data' => $listing['tasks'],
            'meta' => [
                'vista' => $listing['vista'],
                'hoy' => $listing['hoy'],
                'filters' => $listing['filters'],
                'counts' => $listing['counts'],
                'etiquetas' => $listing['etiquetas'],
                'available' => $listing['available'],
                'agenda' => $listing['agenda'] === null ? null : collect($listing['agenda'])
                    ->map(fn ($g, $key) => ['key' => $key, 'label' => $g['label'], 'ids' => $g['tasks']->pluck('id')->values()])
                    ->values(),
            ],
        ]);
    }

    /** Lo que la app necesita para armar filtros, selects y menús (estados, secciones, posponer…). */
    public function config()
    {
        return response()->json(['data' => [
            'vistas' => self::VISTAS,
            'estados' => config('tasks.estados'),
            'prioridades' => config('tasks.prioridades'),
            'secciones' => collect(config('tasks.secciones'))->map(fn ($s, $key) => ['key' => $key, 'label' => $s['label'], 'areas' => $s['areas']])->values(),
            'areas' => Sections::areas(),
            'posponer' => collect(config('tasks.posponer'))->map(fn ($preset, $label) => ['label' => $label, 'preset' => $preset])->values(),
            'dias' => self::DIAS,
            'hoy' => TaskRepository::today(),
        ]]);
    }

    public function show(TaskRepository $tasks, string $task)
    {
        return response()->json(['data' => $tasks->findOrFail($task)]);
    }
}
