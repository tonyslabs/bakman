{{-- Lista / Inbox: agrupada por sección (Trabajo / Personal) y área. Hechas: plana, más reciente primero. --}}
@if ($tasks->isEmpty())
    <div class="rounded-lg bg-zinc-900 p-10 text-center text-zinc-500 ring-1 ring-zinc-800">
        {{ match ($vista) { 'inbox' => 'Inbox vacío.', 'hechas' => 'Todavía no hay tareas cerradas.', default => 'No hay tareas activas con estos filtros.' } }}
    </div>
@elseif ($vista === 'hechas')
    <ul class="space-y-1 rounded-lg bg-zinc-950 p-2 ring-1 ring-zinc-800">
        @foreach ($tasks as $task)
            @include('tasks.partials.row')
        @endforeach
    </ul>
@else
    @foreach (config('tasks.secciones') + ['' => ['label' => 'Sin área', 'areas' => ['' => null]]] as $seccionKey => $seccion)
        @php $enSeccion = $tasks->filter(fn ($t) => ($t['seccion'] ?? '') === $seccionKey); @endphp
        @continue($enSeccion->isEmpty())
        <div class="space-y-4">
            <h3 class="flex items-baseline gap-2 border-b border-zinc-800 pb-1 font-display text-lg text-zinc-200">
                {{ $seccion['label'] }} <span class="font-sans text-xs text-zinc-600">{{ $enSeccion->count() }}</span>
            </h3>
            @foreach ($seccionKey === '' ? ['' => 'Sin área'] : $seccion['areas'] as $area => $areaLabel)
                @php $items = $seccionKey === '' ? $enSeccion : $enSeccion->where('area', $area); @endphp
                @continue($items->isEmpty())
                <section>
                    <h4 class="mb-2 flex items-baseline gap-2 text-sm font-medium uppercase tracking-wider text-zinc-400">
                        {{ $areaLabel }} <span class="text-xs normal-case tracking-normal text-zinc-600">{{ $items->count() }}</span>
                    </h4>
                    <ul class="space-y-1 rounded-lg bg-zinc-950 p-2 ring-1 ring-zinc-800">
                        @foreach ($items as $task)
                            @include('tasks.partials.row', ['hideArea' => true])
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    @endforeach
@endif
