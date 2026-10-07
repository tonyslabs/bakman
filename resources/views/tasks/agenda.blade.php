{{-- Agenda: tareas con fecha, por tramos (vencidas, hoy, mañana, 7 días, después). --}}
@if (! $agenda)
    <div class="rounded-lg bg-zinc-900 p-10 text-center text-zinc-500 ring-1 ring-zinc-800">
        Nada con fecha. Ponle fecha a una tarea con ⏱ o con <code>@mañana</code> al crearla.
    </div>
@endif
@foreach ($agenda as $key => $grupo)
    <section>
        <h3 @class(['mb-2 flex items-baseline gap-2 text-sm font-medium uppercase tracking-wider', 'text-red-400' => $key === 'vencidas', 'text-yellow-300' => $key === 'hoy', 'text-zinc-400' => ! in_array($key, ['vencidas', 'hoy'])])>
            {{ $grupo['label'] }} <span class="text-xs normal-case tracking-normal text-zinc-600">{{ $grupo['tasks']->count() }}</span>
        </h3>
        <ul class="space-y-1 rounded-lg bg-zinc-950 p-2 ring-1 ring-zinc-800">
            @foreach ($grupo['tasks'] as $task)
                @include('tasks.partials.row')
            @endforeach
        </ul>
    </section>
@endforeach
