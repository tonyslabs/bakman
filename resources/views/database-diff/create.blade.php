<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Comparar bases de datos</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-500/10 text-red-400 border border-red-500/30 rounded-lg">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if ($connections->isEmpty())
                <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                    <p class="text-zinc-500">Hace falta al menos una conexión registrada. <a href="{{ route('database-connections.create') }}" class="text-red-500 hover:underline">Crear una</a>.</p>
                </div>
            @else
                <form method="POST" action="{{ route('database-diff.compare') }}" x-data="databaseDiffForm()" @submit="showComparing()">
                    @csrf

                    <div class="flex flex-col gap-6 lg:flex-row lg:items-stretch lg:gap-0">
                        <div class="flex-1 bg-zinc-900 border border-zinc-800 rounded-2xl p-6 lg:max-w-md">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-600/15 text-red-400 font-display text-sm">A</span>
                                <h3 class="font-display tracking-wide text-zinc-100">Origen</h3>
                            </div>

                            <div class="mb-4">
                                <x-input-label for="connection_a_id" value="Instancia" />
                                <select id="connection_a_id" name="connection_a_id" x-model="a.connectionId" @change="loadDatabases('a')" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500" required>
                                    <option value="">—</option>
                                    @foreach ($connections as $connection)
                                        <option value="{{ $connection->id }}">{{ $connection->name }} ({{ $connection->host }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="db_a" value="Base de datos" />
                                <select id="db_a" name="db_a" x-model="a.db" :disabled="!a.connectionId || a.loading || a.databases.length === 0" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 disabled:opacity-50" required>
                                    <option value="">—</option>
                                    <template x-for="db in a.databases" :key="db">
                                        <option :value="db" x-text="db"></option>
                                    </template>
                                </select>

                                <div class="mt-2 flex items-center gap-1.5 text-xs text-zinc-500" x-show="a.loading" x-cloak>
                                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    <span>Consultando bases de datos…</span>
                                </div>
                                <div class="mt-2 flex items-center gap-1.5 text-xs text-red-400" x-show="a.error" x-cloak>
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.25 3h.008v.008h-.008v-.008z" /></svg>
                                    <span x-text="a.error"></span>
                                </div>
                                <div class="mt-2 flex items-center gap-1.5 text-xs text-green-400" x-show="!a.loading && !a.error && a.databases.length" x-cloak>
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span x-text="a.databases.length + ' bases disponibles'"></span>
                                </div>
                            </div>
                        </div>

                        <div class="relative flex flex-col items-center justify-center gap-2 lg:w-32 lg:shrink-0">
                            <div class="absolute inset-x-10 top-1/2 h-px bg-zinc-800 lg:inset-x-auto lg:inset-y-10 lg:left-1/2 lg:h-auto lg:w-px"></div>
                            <button type="submit"
                                x-bind:disabled="!(a.connectionId && a.db && b.connectionId && b.db && sections.length > 0)"
                                title="Comparar"
                                aria-label="Comparar"
                                class="relative z-10 flex h-16 w-16 shrink-0 items-center justify-center rounded-full border-4 border-black bg-red-600 text-white shadow-lg shadow-black/40 transition hover:bg-red-500 disabled:bg-zinc-700 disabled:text-zinc-400 disabled:cursor-not-allowed disabled:hover:bg-zinc-700"
                            >
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            </button>
                            <span class="relative z-10 bg-black px-2 text-[10px] font-display uppercase tracking-widest text-zinc-500">Comparar</span>
                        </div>

                        <div class="flex-1 bg-zinc-900 border border-zinc-800 rounded-2xl p-6 lg:max-w-md">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-600/15 text-red-400 font-display text-sm">B</span>
                                <h3 class="font-display tracking-wide text-zinc-100">Destino</h3>
                            </div>

                            <div class="mb-4">
                                <x-input-label for="connection_b_id" value="Instancia" />
                                <select id="connection_b_id" name="connection_b_id" x-model="b.connectionId" @change="loadDatabases('b')" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500" required>
                                    <option value="">—</option>
                                    @foreach ($connections as $connection)
                                        <option value="{{ $connection->id }}">{{ $connection->name }} ({{ $connection->host }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <x-input-label for="db_b" value="Base de datos" />
                                <select id="db_b" name="db_b" x-model="b.db" :disabled="!b.connectionId || b.loading || b.databases.length === 0" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 disabled:opacity-50" required>
                                    <option value="">—</option>
                                    <template x-for="db in b.databases" :key="db">
                                        <option :value="db" x-text="db"></option>
                                    </template>
                                </select>

                                <div class="mt-2 flex items-center gap-1.5 text-xs text-zinc-500" x-show="b.loading" x-cloak>
                                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    <span>Consultando bases de datos…</span>
                                </div>
                                <div class="mt-2 flex items-center gap-1.5 text-xs text-red-400" x-show="b.error" x-cloak>
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.25 3h.008v.008h-.008v-.008z" /></svg>
                                    <span x-text="b.error"></span>
                                </div>
                                <div class="mt-2 flex items-center gap-1.5 text-xs text-green-400" x-show="!b.loading && !b.error && b.databases.length" x-cloak>
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <span x-text="b.databases.length + ' bases disponibles'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 bg-zinc-900 border border-zinc-800 rounded-2xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-display tracking-wide text-zinc-100">Qué comparar</h3>
                            <label class="flex items-center gap-2 text-xs text-zinc-400 cursor-pointer select-none">
                                <input type="checkbox" :checked="allSelected" @change="toggleAll($event.target.checked)" class="rounded border-zinc-700 bg-zinc-950 text-red-600 focus:ring-red-500 focus:ring-offset-zinc-900">
                                Todo
                            </label>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <template x-for="option in sectionOptions" :key="option.value">
                                <label
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg border text-sm cursor-pointer select-none transition"
                                    :class="sections.includes(option.value) ? 'bg-red-600/15 border-red-600/40 text-red-300' : 'bg-zinc-950 border-zinc-700 text-zinc-400 hover:border-zinc-600'"
                                >
                                    <input type="checkbox" name="sections[]" :value="option.value" x-model="sections" class="sr-only">
                                    <span x-text="option.label"></span>
                                </label>
                            </template>
                        </div>

                        <p class="mt-3 text-xs text-red-400" x-show="sections.length === 0" x-cloak>Elegí al menos una opción para comparar.</p>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function databaseDiffForm() {
            return {
                a: { connectionId: '', db: '', databases: [], loading: false, error: null },
                b: { connectionId: '', db: '', databases: [], loading: false, error: null },
                sectionOptions: [
                    { value: 'tables', label: 'Tablas' },
                    { value: 'views', label: 'Vistas' },
                    { value: 'procedures', label: 'Procedimientos' },
                    { value: 'functions', label: 'Funciones' },
                    { value: 'data', label: 'Datos' },
                ],
                sections: ['tables', 'views', 'procedures', 'functions', 'data'],
                get allSelected() {
                    return this.sections.length === this.sectionOptions.length;
                },
                toggleAll(checked) {
                    this.sections = checked ? this.sectionOptions.map(o => o.value) : [];
                },
                showComparing() {
                    Swal.fire({
                        title: 'Comparando…',
                        text: 'Esto puede tardar unos segundos, no cierres esta pestaña.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        background: '#18181b',
                        color: '#e4e4e7',
                        didOpen: () => Swal.showLoading(),
                    });
                },
                loadDatabases(side) {
                    const state = this[side];
                    state.db = '';
                    state.databases = [];
                    state.error = null;

                    if (!state.connectionId) {
                        return;
                    }

                    state.loading = true;

                    fetch(`{{ url('database-connections') }}/${state.connectionId}/databases`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                state.databases = data.databases;
                            } else {
                                state.error = data.message || 'No se pudo conectar.';
                            }
                        })
                        .catch(() => { state.error = 'Error de red.'; })
                        .finally(() => { state.loading = false; });
                },
            };
        }
    </script>
</x-app-layout>
