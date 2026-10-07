{{-- Prioridad · área · proyecto · vence · repetición · subtareas · etiquetas de una tarea. --}}
@php
    $hoy = \App\Services\Tasks\TaskRepository::today();
    $prioClass = match ($task['prioridad']) {
        'alta' => 'text-red-400 ring-red-500/40 bg-red-500/10',
        'media' => 'text-yellow-300 ring-yellow-500/30 bg-yellow-500/10',
        default => 'text-zinc-400 ring-zinc-700 bg-zinc-800/60',
    };
@endphp
<div class="flex flex-wrap items-center gap-1.5 text-xs">
    @if ($task['prioridad'])
        <x-badge class="{{ $prioClass }}">{{ config('tasks.prioridades')[$task['prioridad']] ?? $task['prioridad'] }}</x-badge>
    @endif
    @if ($task['area'] && empty($hideArea))
        <x-badge>{{ $task['area_label'] }}</x-badge>
    @endif
    @if ($task['vence'])
        <span @class(['font-medium', 'text-red-400' => $task['vencida'], 'text-yellow-300' => ! $task['vencida'] && $task['vence'] === $hoy, 'text-zinc-400' => ! $task['vencida'] && $task['vence'] !== $hoy])
              title="Vence {{ $task['vence'] }}">
            {{ $task['vencida'] ? '⚠ ' : '' }}{{ $task['vence'] === $hoy ? 'Hoy' : \Illuminate\Support\Carbon::parse($task['vence'])->isoFormat('ddd D MMM') }}
        </span>
    @endif
    @if ($task['repite'])
        <span class="text-sky-400" title="{{ $task['repite_label'] }}{{ $task['repite_desde'] === 'completada' ? ', desde que se completa' : '' }}{{ $task['repite_hasta'] ? ', hasta '.$task['repite_hasta'] : '' }}{{ $task['veces'] ? ' · hecha '.$task['veces'].' veces' : '' }}">↻ {{ $task['repite_label'] }}</span>
    @endif
    @if ($task['pospuesta'])
        <span class="text-zinc-500" title="Oculta hasta {{ $task['inicio'] }}">⏸ desde {{ \Illuminate\Support\Carbon::parse($task['inicio'])->isoFormat('D MMM') }}</span>
    @endif
    @if ($task['progreso'])
        <span @class(['tabular-nums', 'text-green-400' => $task['progreso'][0] === $task['progreso'][1], 'text-zinc-400' => $task['progreso'][0] !== $task['progreso'][1]])
              title="Subtareas">☑ {{ $task['progreso'][0] }}/{{ $task['progreso'][1] }}</span>
    @endif
    @if ($task['proyecto_label'])
        <span class="text-zinc-500">↳ {{ $task['proyecto_label'] }}</span>
    @endif
    @foreach ($task['etiquetas'] as $tag)
        <a href="{{ route('tasks.index', ['vista' => $vista ?? 'tablero', 'etiqueta' => $tag]) }}" class="text-violet-400/80 hover:text-violet-300">#{{ $tag }}</a>
    @endforeach
</div>
