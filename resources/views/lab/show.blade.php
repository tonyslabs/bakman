<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Lab · {{ $moduleLabel }}</h2>
            <div class="flex flex-wrap items-center gap-3 font-sans">
                @if ($discovery)
                    @if ($discovery['configured'])
                        <span class="text-xs text-zinc-500">
                            {{ $discovery['last'] ? 'Sincronizado '.$discovery['last']['at']->diffForHumans() : 'Sin sincronizar todavía' }}
                        </span>
                        <form method="POST" action="{{ route('lab.discover') }}">
                            @csrf
                            <button class="rounded-md px-3 py-1.5 text-xs font-medium text-zinc-300 ring-1 ring-zinc-700 transition-colors hover:bg-zinc-800 hover:text-zinc-100">↻ Sincronizar ahora</button>
                        </form>
                    @else
                        <span class="text-xs text-yellow-400"><span aria-hidden="true">!</span> Descubrimiento desactivado: falta <code>PORTAINER_TOKEN</code></span>
                    @endif
                @endif
                @if ($hiddenCount)
                    <a href="{{ route('lab.show', ['module' => $module] + ($showHidden ? [] : ['ocultos' => 1])) }}" class="text-xs text-zinc-400 hover:text-zinc-100">
                        {{ $showHidden ? 'Esconder ocultos' : "Ver {$hiddenCount} ocultos" }}
                    </a>
                @endif
                <x-button-link href="{{ route('lab.projects.create', $module) }}">+ Nuevo proyecto</x-button-link>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto space-y-10 px-4 sm:px-6 lg:px-8">
            <x-flash />

            @if ($projects->isEmpty())
                <div class="rounded-lg bg-zinc-900 p-10 text-center text-zinc-500 ring-1 ring-zinc-800">
                    Todavía no hay proyectos en {{ $moduleLabel }}.
                    <a href="{{ route('lab.projects.create', $module) }}" class="text-red-400 hover:underline">Agrega el primero</a>.
                </div>
            @else
                @foreach ($sections as $section => $items)
                    @php
                        $running = $items->where('container_state', 'running')->count();
                        $withContainer = $items->whereNotNull('container_state')->where('container_state', '!=', 'removed')->count();
                    @endphp
                    <section>
                        @if ($sections->count() > 1 || $section !== '')
                            <h3 class="mb-3 flex items-baseline gap-2 text-sm font-medium uppercase tracking-wider text-zinc-400">
                                {{ $section !== '' ? $section : 'Sin sección' }}
                                <span class="text-xs normal-case tracking-normal text-zinc-600">
                                    {{ $items->count() }}@if ($withContainer) · {{ $running }}/{{ $withContainer }} contenedores en ejecución @endif
                                </span>
                            </h3>
                        @endif

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($items as $project)
                                @php
                                    $linkable = ! $project->monitor_only;
                                    $hasContainer = $project->container_state !== null;
                                    [$cIcon, $cText, $cClass] = match (true) {
                                        $project->container_state === 'running' && $project->container_health === 'unhealthy' => ['✕', 'no sano', 'text-red-400'],
                                        $project->container_state === 'running' && $project->container_health === 'starting' => ['○', 'arrancando', 'text-yellow-400'],
                                        $project->container_state === 'running' => ['●', $project->container_health === 'healthy' ? 'en ejecución · sano' : 'en ejecución', 'text-green-400'],
                                        $project->container_state === 'restarting' => ['!', 'reiniciando en bucle', 'text-orange-400'],
                                        $project->container_state === 'paused' => ['‖', 'pausado', 'text-yellow-400'],
                                        $project->container_state === 'removed' => ['?', 'el contenedor ya no existe', 'text-zinc-500'],
                                        default => ['✕', 'detenido', 'text-red-400'],
                                    };
                                @endphp
                                <div @class([
                                        'group relative rounded-lg border border-zinc-800 border-l-4 bg-zinc-900 p-5 transition hover:border-zinc-700',
                                        'border-l-red-500' => $linkable,
                                        'border-l-zinc-600' => ! $linkable,
                                        'opacity-50' => $project->hidden,
                                     ])
                                     x-data="{ state: null }"
                                     @if ($hasContainer)
                                     {{-- El estado lo da el contenedor (Portainer); no hace falta el ping HTTP. --}}
                                     @elseif ($project->isLoopback())
                                     {{-- Local: lo comprueba el navegador, que sí ve el localhost de esta máquina.
                                          no-cors: la respuesta es opaca, pero si el puerto no escucha la promesa falla. --}}
                                     x-init="const c = new AbortController(); setTimeout(() => c.abort(), 3000);
                                             const t0 = performance.now();
                                             fetch(@js($project->url), { mode: 'no-cors', cache: 'no-store', signal: c.signal })
                                                .then(() => state = { up: true, ms: Math.round(performance.now() - t0) })
                                                .catch(() => state = { up: false })"
                                     @else
                                     x-init="fetch('{{ route('lab.projects.ping', $project) }}', { headers: { Accept: 'application/json' } })
                                                .then(r => r.json()).then(d => state = d).catch(() => state = { up: false })"
                                     @endif>
                                    <div class="absolute right-3 top-3 flex items-center gap-2 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                                        <a href="{{ route('lab.projects.edit', $project) }}" class="text-xs text-zinc-500 hover:text-zinc-200">Editar</a>
                                        <form method="POST" action="{{ route('lab.projects.toggle-hidden', $project) }}">
                                            @csrf
                                            <button type="submit" class="text-xs text-zinc-500 hover:text-zinc-200">{{ $project->hidden ? 'Mostrar' : 'Ocultar' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('lab.projects.destroy', $project) }}" onsubmit="return confirm(@js('¿Borrar '.$project->name.'?'.($project->discovered && $project->container_state !== 'removed' ? ' La próxima sincronización la volverá a crear; usa Ocultar para que no aparezca.' : '')))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-zinc-500 hover:text-red-400">Borrar</button>
                                        </form>
                                    </div>

                                    @if ($linkable)
                                        <a href="{{ $project->url }}" target="_blank" rel="noopener" class="block">
                                    @else
                                        <div class="block">
                                    @endif
                                        <h4 class="mb-2 truncate pr-28 font-display text-lg text-zinc-100">{{ $project->name }}</h4>
                                        <p class="mb-3 truncate font-mono text-sm text-zinc-500" title="{{ $project->url }}">
                                            @if ($linkable || $project->port)
                                                {{ $project->host }}{{ $project->port ? ':'.$project->port : '' }}{{ $project->path ? '/'.ltrim($project->path, '/') : '' }}
                                            @else
                                                {{ $project->container ?? $project->host }}
                                            @endif
                                        </p>
                                        @if ($project->description)
                                            <p class="mb-3 line-clamp-2 text-sm text-zinc-400">{{ $project->description }}</p>
                                        @endif

                                        {{-- Estado: ícono + texto, nunca solo color --}}
                                        <p class="flex flex-wrap items-center gap-x-2 text-xs">
                                            @if ($hasContainer)
                                                <span class="{{ $cClass }}"><span aria-hidden="true">{{ $cIcon }}</span> {{ $cText }}</span>
                                                @if ($project->container_status && $project->container_state !== 'removed')
                                                    <span class="text-zinc-500">{{ $project->container_status }}</span>
                                                @endif
                                            @else
                                                <span x-show="state === null" class="text-zinc-500">○ comprobando…</span>
                                                @if ($project->isLoopback())
                                                    {{-- En desarrollo "apagado" es lo normal: gris, no rojo. --}}
                                                    <span x-show="state && state.up" x-cloak class="text-green-400">● corriendo <span class="tabular-nums text-zinc-500" x-text="state?.ms ? state.ms + ' ms' : ''"></span></span>
                                                    <span x-show="state && state.up === false" x-cloak class="text-zinc-500">○ detenido</span>
                                                    <span class="text-zinc-600">· en tu equipo</span>
                                                @else
                                                    <span x-show="state && state.up" x-cloak class="text-green-400">● en línea <span class="tabular-nums text-zinc-500" x-text="state?.ms ? state.ms + ' ms' : ''"></span></span>
                                                    <span x-show="state && state.up === false" x-cloak class="text-red-400">✕ sin respuesta <span class="text-zinc-500" x-text="state?.status ? '(HTTP ' + state.status + ')' : ''"></span></span>
                                                @endif
                                            @endif
                                            @if (! $linkable)
                                                <span class="text-zinc-600">· solo estado</span>
                                            @endif
                                            @if ($project->discovered)
                                                <span class="text-zinc-600">· auto</span>
                                            @endif
                                        </p>
                                    @if ($linkable)
                                        </a>
                                    @else
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
