{{-- Recurrentes: cada serie con su regla, próxima fecha y cuántas veces se hizo. --}}
@if ($tasks->isEmpty())
    <div class="rounded-lg bg-zinc-900 p-10 text-center text-zinc-500 ring-1 ring-zinc-800">
        No hay tareas recurrentes. Créalas con <code>*semanal:lun</code>, <code>*mensual:5</code>, <code>*cada:3d</code>… en la captura rápida, o desde Editar → Repetición.
    </div>
@else
    <ul class="space-y-1 rounded-lg bg-zinc-950 p-2 ring-1 ring-zinc-800">
        @foreach ($tasks as $task)
            @include('tasks.partials.row')
            <li class="-mt-1 mb-1 pl-10 text-xs text-zinc-600">
                {{ $task['veces'] ? 'Hecha '.$task['veces'].' '.($task['veces'] === 1 ? 'vez' : 'veces') : 'Todavía no se ha hecho' }}@if ($task['ultima']) · última {{ \Illuminate\Support\Carbon::parse($task['ultima'])->isoFormat('D MMM') }}@endif
                · cuenta desde {{ $task['repite_desde'] === 'completada' ? 'que se completa' : 'la fecha programada' }}@if ($task['repite_hasta']) · hasta {{ $task['repite_hasta'] }}@endif
            </li>
        @endforeach
    </ul>
@endif
