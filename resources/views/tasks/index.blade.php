<x-app-layout>
    @php
        $seccion = $filters['seccion'] ?? null;
        $keep = fn (array $extra = []) => array_filter($extra + $filters);
    @endphp
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Tareas</h2>
                {{-- Sección: Todo / Trabajo / Personal (se mantiene al cambiar de vista) --}}
                <div class="flex rounded-md bg-zinc-900 p-0.5 font-sans text-sm ring-1 ring-zinc-800">
                    @foreach (['' => 'Todo'] + \App\Services\Tasks\Sections::labels() as $key => $label)
                        <a href="{{ route('tasks.index', ['vista' => $vista] + $keep(['seccion' => $key ?: null, 'area' => null])) }}"
                           @class(['rounded px-3 py-1 transition-colors', 'bg-zinc-700 text-zinc-100' => ($seccion ?? '') === $key, 'text-zinc-400 hover:text-zinc-100' => ($seccion ?? '') !== $key])>
                            {{ $label }}
                            @if ($key)<span class="ml-0.5 text-xs text-zinc-500">{{ $counts['secciones'][$key] }}</span>@endif
                        </a>
                    @endforeach
                </div>
            </div>
            <nav class="flex flex-wrap items-center gap-1 font-sans text-sm">
                @foreach (\App\Http\Controllers\TaskController::VISTAS as $key => $label)
                    <a href="{{ route('tasks.index', ['vista' => $key] + $keep()) }}"
                       @class(['rounded-md px-3 py-1.5 transition-colors', 'bg-red-600/10 text-red-400 ring-1 ring-red-500/40' => $vista === $key, 'text-zinc-400 hover:bg-zinc-900 hover:text-zinc-100' => $vista !== $key])>
                        {{ $label }}
                        @if (in_array($key, ['lista', 'inbox', 'recurrentes']) && $counts[$key])<span class="ml-1 text-xs text-zinc-500">{{ $counts[$key] }}</span>@endif
                        @if ($key === 'agenda' && $counts['agenda'])<span class="ml-1 rounded bg-yellow-500/20 px-1 text-xs text-yellow-300" title="Para hoy o vencidas">{{ $counts['agenda'] }}</span>@endif
                    </a>
                @endforeach
            </nav>
        </div>
    </x-slot>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('tasksPage', (cfg) => ({
                current: null,
                msg: '',
                msgTimer: null,
                estados: cfg.estados,
                help: false,

                rows() { return [...this.$root.querySelectorAll('[data-task]')]; },
                select(el) { this.current = el; el?.scrollIntoView({ block: 'nearest' }); },
                flash(text, ms = 2500) { this.msg = text; clearTimeout(this.msgTimer); this.msgTimer = setTimeout(() => this.msg = '', ms); },
                url(tpl, id, extra = '') { return tpl.replace('__ID__', encodeURIComponent(id)).replace('__N__', extra); },
                // Cambios que mueven la tarea de grupo/fecha: aviso y recarga para que la vista quede al día.
                reloadAfter(text) { this.flash(text, 4000); setTimeout(() => location.reload(), 1300); },

                async request(url, method, body) {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify(body),
                    });
                    if (!res.ok) {
                        this.flash('No se pudo guardar (' + res.status + '). Recargando…');
                        setTimeout(() => location.reload(), 1200);
                        throw new Error(res.status);
                    }
                    return res.json();
                },

                async setEstado(row, estado) {
                    const { data, mensaje } = await this.request(this.url(cfg.estadoUrl, row.dataset.id), 'PATCH', { estado });
                    if (data.recurrio) return this.reloadAfter(mensaje);
                    const done = ['hecha', 'cancelada'].includes(data.estado);
                    row.dataset.estado = data.estado;
                    row.classList.toggle('opacity-50', done);
                    row.querySelector('[data-title]')?.classList.toggle('line-through', done);
                    const cb = row.querySelector(':scope > div > input[type=checkbox]');
                    if (cb) cb.checked = done;
                    const sel = row.querySelector('[data-estado-select]');
                    if (sel) sel.value = data.estado;
                    this.flash(mensaje);
                },

                toggle(row) {
                    this.select(row);
                    return this.setEstado(row, row.dataset.estado === 'hecha' ? 'pendiente' : 'hecha');
                },

                async fecha(row, body) {
                    const { mensaje } = await this.request(this.url(cfg.fechaUrl, row.dataset.id), 'PATCH', body);
                    this.reloadAfter(mensaje);
                },

                async sub(row, n, input) {
                    const { data } = await this.request(this.url(cfg.subUrl, row.dataset.id, n), 'PATCH', { hecha: input.checked });
                    input.nextElementSibling?.classList.toggle('line-through', input.checked);
                    input.nextElementSibling?.classList.toggle('text-zinc-500', input.checked);
                    const [hechas, total] = data.progreso;
                    this.flash('Subtareas ' + hechas + '/' + total + (hechas === total ? ' — ¿marcar la tarea como hecha? (x)' : ''));
                },

                // Atajos: j/k = moverse · x = hecha · 1–6 = estado · p = posponer a mañana · s = subtareas
                //         e/Enter = editar · n = nueva · / = buscar · ? = ayuda
                key(e) {
                    if (e.target.closest('input, textarea, select') || e.metaKey || e.ctrlKey || e.altKey) {
                        if (e.key === 'Escape') e.target.blur();
                        return;
                    }
                    const rows = this.rows();
                    const i = rows.indexOf(this.current);
                    const keys = Object.keys(this.estados);
                    if (e.key === 'n') { e.preventDefault(); document.getElementById('quick-titulo')?.focus(); }
                    else if (e.key === '/') { e.preventDefault(); document.getElementById('filtro-q')?.focus(); }
                    else if (e.key === '?') { this.help = !this.help; }
                    else if (e.key === 'Escape') { this.help = false; this.current = null; }
                    else if (!rows.length) return;
                    else if (e.key === 'j' || e.key === 'ArrowDown') { e.preventDefault(); this.select(rows[Math.min(i + 1, rows.length - 1)]); }
                    else if (e.key === 'k' || e.key === 'ArrowUp') { e.preventDefault(); this.select(rows[Math.max(i - 1, 0)]); }
                    else if (!this.current) return;
                    else if (e.key === 'x') this.toggle(this.current);
                    else if (e.key === 'p') this.fecha(this.current, { preset: '+1 day' });
                    else if (e.key === 's') Alpine.$data(this.current).subs = !Alpine.$data(this.current).subs;
                    else if (/^[1-9]$/.test(e.key) && keys[e.key - 1]) this.setEstado(this.current, keys[e.key - 1]);
                    else if (e.key === 'e' || e.key === 'Enter') location.href = this.current.dataset.edit;
                },
            }));
        });
    </script>

    <div class="py-8"
         x-data="tasksPage({
            estadoUrl: @js(route('tasks.estado', '__ID__')),
            fechaUrl: @js(route('tasks.fecha', '__ID__')),
            subUrl: @js(route('tasks.subtarea', ['task' => '__ID__', 'indice' => 0])).replace(/0$/, '__N__'),
            estados: @js(config('tasks.estados')),
         })"
         @keydown.window="key($event)">
        <div class="mx-auto space-y-6 px-4 sm:px-6 lg:px-8 {{ $vista === 'tablero' ? 'max-w-[110rem]' : 'max-w-5xl' }}">
            <x-flash />

            @unless ($available)
                <div class="rounded-md border border-yellow-500/30 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-300">
                    <span aria-hidden="true">!</span> No se puede escribir en <code>{{ $path }}</code>. Revisa que el vault esté montado en el contenedor (<code>TASKS_PATH</code>).
                </div>
            @endunless

            {{-- Captura rápida con tokens --}}
            <form method="POST" action="{{ route('tasks.store') }}" class="space-y-2 rounded-lg bg-zinc-900 p-3 ring-1 ring-zinc-800">
                @csrf
                <input type="hidden" name="rapida" value="1">
                <input type="hidden" name="estado" value="{{ $vista === 'inbox' ? 'inbox' : 'pendiente' }}">
                <div class="flex flex-wrap items-center gap-2">
                    <x-text-input id="quick-titulo" name="titulo" class="min-w-0 flex-1 text-sm" required maxlength="300" autocomplete="off"
                                  placeholder="Nueva tarea… (n)   ej: Pagar internet #finanzas !alta @15 *mensual:15" />
                    <select name="area" class="rounded-md border-zinc-700 bg-zinc-950 text-sm text-zinc-300 focus:border-red-500 focus:ring-red-500">
                        @include('tasks.partials.area-options', ['selected' => $filters['area'] ?? ($seccion === 'trabajo' ? 'up' : 'personal')])
                    </select>
                    <select name="prioridad" class="rounded-md border-zinc-700 bg-zinc-950 text-sm text-zinc-300 focus:border-red-500 focus:ring-red-500">
                        @foreach (config('tasks.prioridades') as $key => $label)
                            <option value="{{ $key }}" @selected($key === 'media')>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="vence" title="Vence" class="rounded-md border-zinc-700 bg-zinc-950 text-sm text-zinc-300 focus:border-red-500 focus:ring-red-500 [color-scheme:dark]">
                    <x-primary-button>Agregar</x-primary-button>
                </div>
                <p class="text-xs text-zinc-600">
                    <code class="text-zinc-500">!alta</code> prioridad ·
                    <code class="text-zinc-500">#up</code> área (o etiqueta si no es un área) ·
                    <code class="text-zinc-500">@hoy @mañana @vie @15 @+3 @10/10</code> fecha ·
                    <code class="text-zinc-500">*diaria *habiles *semanal:lun,jue *mensual:5 *cada:2s</code> repetir ·
                    <code class="text-zinc-500">[[Nota]]</code> proyecto
                </p>
                <x-input-error :messages="$errors->get('titulo')" />
                <x-input-error :messages="$errors->get('repite')" />
            </form>

            {{-- Filtros --}}
            <form method="GET" action="{{ route('tasks.index', $vista) }}" class="flex flex-wrap items-center gap-2 text-sm">
                @if ($seccion)<input type="hidden" name="seccion" value="{{ $seccion }}">@endif
                <input id="filtro-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar… (/)"
                       class="w-56 rounded-md border-zinc-800 bg-zinc-950 text-sm text-zinc-200 placeholder-zinc-600 focus:border-red-500 focus:ring-red-500">
                <select name="area" onchange="this.form.submit()" class="rounded-md border-zinc-800 bg-zinc-950 text-sm text-zinc-300 focus:border-red-500 focus:ring-red-500">
                    @include('tasks.partials.area-options', ['selected' => $filters['area'] ?? null, 'empty' => 'Todas las áreas'])
                </select>
                <select name="prioridad" onchange="this.form.submit()" class="rounded-md border-zinc-800 bg-zinc-950 text-sm text-zinc-300 focus:border-red-500 focus:ring-red-500">
                    <option value="">Toda prioridad</option>
                    @foreach (config('tasks.prioridades') as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['prioridad'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @if ($etiquetas->isNotEmpty())
                    <select name="etiqueta" onchange="this.form.submit()" class="rounded-md border-zinc-800 bg-zinc-950 text-sm text-zinc-300 focus:border-red-500 focus:ring-red-500">
                        <option value="">Toda etiqueta</option>
                        @foreach ($etiquetas as $tag)
                            <option value="{{ $tag }}" @selected(($filters['etiqueta'] ?? '') === $tag)>#{{ $tag }}</option>
                        @endforeach
                    </select>
                @endif
                @if ($counts['pospuestas'] && in_array($vista, ['lista', 'tablero']))
                    <label class="flex items-center gap-1.5 text-xs text-zinc-400">
                        <input type="checkbox" name="pospuestas" value="1" @checked(! empty($filters['pospuestas'])) onchange="this.form.submit()"
                               class="h-3.5 w-3.5 rounded border-zinc-600 bg-zinc-950 text-red-600 focus:ring-red-500 focus:ring-offset-zinc-900">
                        Ver {{ $counts['pospuestas'] }} pospuesta{{ $counts['pospuestas'] > 1 ? 's' : '' }}
                    </label>
                @endif
                @if (array_diff_key($filters, ['seccion' => 1]))
                    <a href="{{ route('tasks.index', ['vista' => $vista] + array_filter(['seccion' => $seccion])) }}" class="text-xs text-zinc-500 hover:text-zinc-200">Quitar filtros</a>
                @endif
                <span class="ml-auto flex items-center gap-3">
                    @if ($counts['vencidas'])
                        <a href="{{ route('tasks.index', ['vista' => 'agenda'] + $keep()) }}" class="text-xs text-red-400 hover:text-red-300">⚠ {{ $counts['vencidas'] }} vencida{{ $counts['vencidas'] > 1 ? 's' : '' }}</a>
                    @endif
                    <button type="button" @click="help = ! help" class="text-xs text-zinc-600 hover:text-zinc-300" title="Atajos de teclado">? atajos</button>
                </span>
            </form>

            <div x-show="help" x-cloak x-transition.opacity class="grid grid-cols-2 gap-x-6 gap-y-1 rounded-lg bg-zinc-900 p-4 text-xs text-zinc-400 ring-1 ring-zinc-800 sm:grid-cols-4">
                @foreach (['j / k' => 'bajar / subir', 'x' => 'hecha / pendiente', '1–6' => 'cambiar estado', 'p' => 'posponer a mañana', 's' => 'ver subtareas', 'e · Enter' => 'editar', 'n' => 'nueva tarea', '/' => 'buscar', 'Esc' => 'soltar selección', '?' => 'esta ayuda'] as $k => $v)
                    <div><kbd class="rounded bg-zinc-800 px-1.5 py-0.5 font-mono text-zinc-200">{{ $k }}</kbd> {{ $v }}</div>
                @endforeach
            </div>

            @include('tasks.'.match ($vista) { 'tablero' => 'tablero', 'agenda' => 'agenda', 'recurrentes' => 'recurrentes', default => 'lista' })
        </div>

        {{-- Aviso de la última acción --}}
        <div x-show="msg" x-cloak x-transition.opacity
             class="fixed bottom-4 right-4 z-50 max-w-sm rounded-md border border-zinc-700 bg-zinc-900 px-4 py-2 text-sm text-zinc-200 shadow-lg"
             x-text="msg"></div>
    </div>
</x-app-layout>
