<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Editar tarea</h2>
    </x-slot>

    @php
        $volver = request('volver', 'tablero');
        $select = 'mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500';
        // Regla guardada → campos del constructor ("semanal:lun,jue" → tipo semanal + días).
        [$tipo, $arg] = array_pad(explode(':', (string) $task['repite'], 2), 2, null);
        $cadaN = $tipo === 'cada' && preg_match('/^(\d+)([dsm])$/', (string) $arg, $m) ? [$m[1], $m[2]] : [1, 'd'];
        $form = [
            'tipo' => old('repite_tipo', $tipo ?: ''),
            'dias' => old('repite_dias', $tipo === 'semanal' && $arg ? explode(',', $arg) : []),
            'diaMes' => old('repite_dia_mes', $tipo === 'mensual' ? (string) $arg : ''),
            'n' => old('repite_n', $cadaN[0]),
            'unidad' => old('repite_unidad', $cadaN[1]),
        ];
    @endphp
    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('tasks.update', $task['id']) }}" x-data="{ tipo: @js($form['tipo']) }">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="hash" value="{{ old('hash', $task['hash']) }}">
                    <input type="hidden" name="volver" value="{{ $volver }}">

                    <div class="mb-4">
                        <x-input-label for="titulo" value="Título (= nombre de la nota en Obsidian)" />
                        <x-text-input id="titulo" name="titulo" class="mt-1 block w-full" value="{{ old('titulo', $task['titulo']) }}" required maxlength="120" />
                        <x-input-error :messages="$errors->get('titulo')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="estado" value="Estado" />
                            <select id="estado" name="estado" class="{{ $select }}">
                                @foreach (config('tasks.estados') as $key => $text)
                                    <option value="{{ $key }}" @selected(old('estado', $task['estado']) === $key)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="prioridad" value="Prioridad" />
                            <select id="prioridad" name="prioridad" class="{{ $select }}">
                                <option value="">—</option>
                                @foreach (config('tasks.prioridades') as $key => $text)
                                    <option value="{{ $key }}" @selected(old('prioridad', $task['prioridad']) === $key)>{{ $text }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <x-input-label for="area" value="Sección · área" />
                            <select id="area" name="area" class="{{ $select }}">
                                @include('tasks.partials.area-options', ['selected' => old('area', $task['area']), 'empty' => '—'])
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <x-input-label for="vence" value="Vence" />
                            <x-text-input id="vence" type="date" name="vence" class="mt-1 block w-full [color-scheme:dark]" value="{{ old('vence', $task['vence']) }}" />
                            <x-input-error :messages="$errors->get('vence')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="inicio" value="Ocultar hasta (opcional)" />
                            <x-text-input id="inicio" type="date" name="inicio" class="mt-1 block w-full [color-scheme:dark]" value="{{ old('inicio', $task['inicio']) }}" />
                            <p class="mt-1 text-xs text-zinc-500">No aparece en Lista/Tablero antes de esta fecha.</p>
                        </div>
                    </div>

                    {{-- Repetición --}}
                    <fieldset class="mb-4 rounded-md border border-zinc-800 p-4">
                        <legend class="px-1 text-sm text-zinc-300">↻ Repetición</legend>
                        <div class="flex flex-wrap items-center gap-3">
                            <select name="repite_tipo" x-model="tipo" class="rounded-md border-zinc-700 bg-zinc-950 text-sm text-zinc-100 focus:border-red-500 focus:ring-red-500">
                                @foreach (['' => 'No se repite', 'diaria' => 'Cada día', 'habiles' => 'Días hábiles (L–V)', 'semanal' => 'Cada semana', 'mensual' => 'Cada mes', 'anual' => 'Cada año', 'cada' => 'Cada N…'] as $key => $text)
                                    <option value="{{ $key }}">{{ $text }}</option>
                                @endforeach
                            </select>

                            <div x-show="tipo === 'semanal'" x-cloak class="flex gap-1">
                                @foreach (\App\Http\Controllers\TaskController::DIAS as $dia => $letra)
                                    <label class="cursor-pointer">
                                        <input type="checkbox" name="repite_dias[]" value="{{ $dia }}" class="peer sr-only" @checked(in_array($dia, $form['dias'], true))>
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs ring-1 ring-zinc-700 text-zinc-400 peer-checked:bg-red-600 peer-checked:text-white peer-checked:ring-red-600" title="{{ $dia }}">{{ $letra }}</span>
                                    </label>
                                @endforeach
                                <span class="self-center pl-2 text-xs text-zinc-500">(ninguno = el mismo día que vence)</span>
                            </div>

                            <div x-show="tipo === 'mensual'" x-cloak>
                                <select name="repite_dia_mes" class="rounded-md border-zinc-700 bg-zinc-950 text-sm text-zinc-100 focus:border-red-500 focus:ring-red-500">
                                    <option value="">el mismo día que vence</option>
                                    @foreach (range(1, 31) as $d)
                                        <option value="{{ $d }}" @selected($form['diaMes'] === (string) $d)>día {{ $d }}</option>
                                    @endforeach
                                    <option value="ultimo" @selected($form['diaMes'] === 'ultimo')>último día</option>
                                </select>
                            </div>

                            <div x-show="tipo === 'cada'" x-cloak class="flex items-center gap-2">
                                <input type="number" name="repite_n" min="1" max="365" value="{{ $form['n'] }}" class="w-20 rounded-md border-zinc-700 bg-zinc-950 text-sm text-zinc-100 focus:border-red-500 focus:ring-red-500">
                                <select name="repite_unidad" class="rounded-md border-zinc-700 bg-zinc-950 text-sm text-zinc-100 focus:border-red-500 focus:ring-red-500">
                                    @foreach (['d' => 'días', 's' => 'semanas', 'm' => 'meses'] as $key => $text)
                                        <option value="{{ $key }}" @selected($form['unidad'] === $key)>{{ $text }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div x-show="tipo !== ''" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1 text-sm text-zinc-300">
                                <p class="text-xs text-zinc-500">La próxima fecha se cuenta desde…</p>
                                <label class="flex items-center gap-2"><input type="radio" name="repite_desde" value="vence" @checked(old('repite_desde', $task['repite_desde']) !== 'completada') class="border-zinc-600 bg-zinc-950 text-red-600 focus:ring-red-500"> la fecha programada <span class="text-xs text-zinc-500">(pagos, reuniones)</span></label>
                                <label class="flex items-center gap-2"><input type="radio" name="repite_desde" value="completada" @checked(old('repite_desde', $task['repite_desde']) === 'completada') class="border-zinc-600 bg-zinc-950 text-red-600 focus:ring-red-500"> el día en que la completo <span class="text-xs text-zinc-500">(mantenimiento)</span></label>
                            </div>
                            <div>
                                <x-input-label for="repite_hasta" value="Repetir hasta (opcional)" />
                                <x-text-input id="repite_hasta" type="date" name="repite_hasta" class="mt-1 block w-full [color-scheme:dark]" value="{{ old('repite_hasta', $task['repite_hasta']) }}" />
                            </div>
                        </div>
                        <p x-show="tipo !== ''" x-cloak class="mt-3 text-xs text-zinc-500">
                            Al marcarla hecha vuelve a Pendiente con la próxima fecha, sus subtareas se desmarcan y queda anotada en "## Registro".
                            @if ($task['veces']) Hecha {{ $task['veces'] }} {{ $task['veces'] === 1 ? 'vez' : 'veces' }}@if ($task['ultima']), la última el {{ $task['ultima'] }}@endif. @endif
                        </p>
                        <x-input-error :messages="$errors->get('repite')" class="mt-2" />
                    </fieldset>

                    <div class="grid grid-cols-1 gap-4 mb-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="proyecto" value="Proyecto (nota de Obsidian, opcional)" />
                            <x-text-input id="proyecto" name="proyecto" class="mt-1 block w-full" placeholder="Bakman Mobile" value="{{ old('proyecto', $task['proyecto']) }}" />
                        </div>
                        <div>
                            <x-input-label for="etiquetas" value="Etiquetas (separadas por coma)" />
                            <x-text-input id="etiquetas" name="etiquetas" class="mt-1 block w-full" placeholder="llamada, urgente" value="{{ old('etiquetas', implode(', ', $task['etiquetas'])) }}" />
                        </div>
                    </div>

                    <div class="mb-6">
                        <x-input-label for="cuerpo" value="Notas (cuerpo de la nota, markdown)" />
                        <textarea id="cuerpo" name="cuerpo" rows="12" class="mt-1 block w-full rounded-md border-zinc-700 bg-zinc-950 font-mono text-sm text-zinc-100 shadow-sm focus:border-red-500 focus:ring-red-500"
                                  placeholder="Contexto, links…&#10;&#10;- [ ] subtarea 1&#10;- [ ] subtarea 2">{{ old('cuerpo', $task['cuerpo']) }}</textarea>
                        <x-input-error :messages="$errors->get('cuerpo')" class="mt-2" />
                        <p class="mt-1 text-xs text-zinc-500">
                            Las líneas <code>- [ ] …</code> son subtareas (se marcan desde la lista).
                            Creada {{ $task['creada'] ?? '—' }}@if ($task['completada']) · cerrada {{ $task['completada'] }}@endif
                            · modificada {{ \Illuminate\Support\Carbon::createFromTimestamp($task['modificada'])->diffForHumans() }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between">
                        <a href="{{ route('tasks.index', $volver) }}" class="text-sm text-zinc-400 hover:text-zinc-100">← Volver</a>
                        <x-primary-button>Guardar</x-primary-button>
                    </div>
                </form>

                <form method="POST" action="{{ route('tasks.destroy', $task['id']) }}" class="mt-6 border-t border-zinc-800 pt-4"
                      onsubmit="return confirm('¿Borrar la nota de la tarea? (queda 30 días en .stversions del homelab)')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="volver" value="{{ $volver }}">
                    <button class="text-xs text-red-400 hover:text-red-300">Borrar tarea</button>
                    <span class="text-xs text-zinc-600">— mejor marcarla Cancelada si quieres conservar el historial.</span>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
