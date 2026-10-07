{{-- Tablero: una columna por estado; arrastrar cambia estado y orden (SortableJS).
     Soltar una recurrente en "Hecha" la reprograma (vuelve a Pendiente con la próxima fecha). --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>

<div class="flex gap-4 overflow-x-auto pb-4"
     x-init="$nextTick(() => $root.querySelectorAll('[data-col]').forEach(col => Sortable.create(col, {
        group: 'tareas', animation: 150, ghostClass: 'opacity-30', delay: 150, delayOnTouchOnly: true,
        // Arrastre por mouse en vez de HTML5 nativo: si no, al agarrar el título se arrastra el link.
        forceFallback: true, fallbackClass: 'rotate-2', fallbackTolerance: 4,
        onEnd: async (evt) => {
            if (evt.from === evt.to && evt.oldIndex === evt.newIndex) return;
            const estado = evt.to.dataset.col;
            const ids = [...evt.to.querySelectorAll('[data-card]')].map(c => c.dataset.id);
            const { recurrieron } = await request(@js(route('tasks.reordenar')), 'POST', { estado, ids });
            if (recurrieron.length) return reloadAfter(recurrieron.join(' · '));
            evt.item.dataset.estado = estado;
            [evt.from, evt.to].forEach(c => c.closest('[data-column]').querySelector('[data-count]').textContent = c.querySelectorAll('[data-card]').length);
            flash('«' + evt.item.dataset.title + '» → ' + estados[estado]);
        },
     })))">
    @foreach (collect(config('tasks.estados'))->except('cancelada') as $estado => $label)
        @php $items = $tasks->where('estado', $estado); @endphp
        <div data-column class="flex w-72 shrink-0 flex-col 2xl:w-auto 2xl:min-w-0 2xl:flex-1 rounded-lg bg-zinc-950 ring-1 ring-zinc-800">
            <h3 class="flex items-baseline justify-between px-3 pt-3 pb-2 text-sm font-medium uppercase tracking-wider text-zinc-400">
                {{ $label }}
                <span data-count class="text-xs normal-case tracking-normal text-zinc-600">{{ $items->count() }}</span>
            </h3>
            <div data-col="{{ $estado }}" class="min-h-24 flex-1 space-y-2 px-2 pb-3">
                @foreach ($items as $task)
                    <div data-card data-id="{{ $task['id'] }}" data-title="{{ $task['titulo'] }}" data-estado="{{ $task['estado'] }}"
                         @class(['cursor-grab rounded-md border border-zinc-800 border-l-4 bg-zinc-900 p-3 active:cursor-grabbing hover:border-zinc-700',
                                 'border-l-red-500' => $task['prioridad'] === 'alta',
                                 'border-l-yellow-500' => $task['prioridad'] === 'media',
                                 'border-l-zinc-600' => ! in_array($task['prioridad'], ['alta', 'media'], true),
                                 'opacity-60' => $estado === 'hecha'])>
                        <a href="{{ route('tasks.edit', ['task' => $task['id'], 'volver' => 'tablero']) }}" draggable="false"
                           class="block text-sm text-zinc-100 hover:text-red-400 {{ $estado === 'hecha' ? 'line-through' : '' }}">{{ $task['titulo'] }}</a>
                        <div class="mt-2">@include('tasks.partials.badges', ['task' => $task])</div>
                        @if ($task['progreso'])
                            <div class="mt-2 h-1 overflow-hidden rounded bg-zinc-800" title="Subtareas {{ $task['progreso'][0] }}/{{ $task['progreso'][1] }}">
                                <div class="h-full bg-green-500/70" style="width: {{ round(100 * $task['progreso'][0] / $task['progreso'][1]) }}%"></div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
