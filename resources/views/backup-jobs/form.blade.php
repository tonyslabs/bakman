<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">
            {{ $job->exists ? 'Editar backup job' : 'Nuevo backup job' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ $job->exists ? route('backup-jobs.update', $job) : route('backup-jobs.store') }}">
                    @csrf
                    @if ($job->exists) @method('PUT') @endif

                    <div class="mb-4">
                        <x-input-label for="name" value="Nombre" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $job->name) }}" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="type" value="Tipo" />
                        <select id="type" name="type" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500" onchange="backupJobToggleType(this.value)" required>
                            @foreach (['file' => 'Ficheros', 'system' => 'Sistema', 'database' => 'Base de datos', 'script' => 'Script'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $job->type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div id="fields-target">
                        <div class="mb-4">
                            <x-input-label for="target_id" value="Target" />
                            <select id="target_id" name="target_id" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500">
                                <option value="">—</option>
                                @foreach ($targets as $target)
                                    <option value="{{ $target->id }}" @selected(old('target_id', $job->target_id) == $target->id)>{{ $target->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('target_id')" class="mt-2" />
                        </div>
                    </div>

                    <div id="fields-file-system">
                        <div class="mb-4">
                            <x-input-label for="paths_text" value="Rutas (una por línea)" />
                            <textarea id="paths_text" name="paths_text" rows="4" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 font-mono text-sm">{{ old('paths_text', implode("\n", $job->paths ?? [])) }}</textarea>
                            <x-input-error :messages="$errors->get('paths_text')" class="mt-2" />
                        </div>
                    </div>

                    <div id="fields-script">
                        <div class="mb-4">
                            <x-input-label for="command" value="Comando" />
                            <textarea id="command" name="command" rows="3" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 font-mono text-sm" placeholder="cd /home/darius/scripts/proyecto && .venv/bin/python script.py">{{ old('command', $job->command) }}</textarea>
                            <x-input-error :messages="$errors->get('command')" class="mt-2" />
                            <p class="mt-1 text-sm text-zinc-500">Corre en el target por SSH con <code>sh -c</code>. Para un venv, llama directo a su <code>.venv/bin/python</code>.</p>
                        </div>
                        <div class="mb-4">
                            <x-input-label for="timeout_seconds" value="Tiempo máximo en segundos (vacío = {{ config('backups.script_default_timeout') }})" />
                            <x-text-input id="timeout_seconds" type="number" name="timeout_seconds" class="mt-1 block w-full" value="{{ old('timeout_seconds', $job->timeout_seconds) }}" />
                            <x-input-error :messages="$errors->get('timeout_seconds')" class="mt-2" />
                        </div>
                    </div>

                    <div id="fields-database">
                        <div class="mb-4">
                            <x-input-label for="database_connection_id" value="Conexión" />
                            <select id="database_connection_id" name="database_connection_id" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500">
                                <option value="">—</option>
                                @foreach ($connections as $connection)
                                    <option value="{{ $connection->id }}" @selected(old('database_connection_id', $job->database_connection_id) == $connection->id)>{{ $connection->name }} ({{ $connection->host }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('database_connection_id')" class="mt-2" />
                            <p class="mt-1 text-sm text-zinc-500">Se administran en <a href="{{ route('database-connections.index') }}" class="text-red-500 hover:underline">Conexiones de base de datos</a>.</p>
                        </div>
                        <div class="mb-4">
                            <x-input-label for="db_name" value="Nombre de la base" />
                            <x-text-input id="db_name" name="db_name" class="mt-1 block w-full" value="{{ old('db_name', $job->db_name) }}" />
                            <x-input-error :messages="$errors->get('db_name')" class="mt-2" />
                        </div>
                        <div class="mb-4">
                            <x-input-label for="tables_text" value="Tablas (una por línea, vacío = dump completo)" />
                            <textarea id="tables_text" name="tables_text" rows="4" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 font-mono text-sm">{{ old('tables_text', implode("\n", $job->tables ?? [])) }}</textarea>
                            <x-input-error :messages="$errors->get('tables_text')" class="mt-2" />
                        </div>
                        <div class="mb-4 flex items-center">
                            <input type="hidden" name="schema_only" value="0">
                            <input type="checkbox" id="schema_only" name="schema_only" value="1" class="rounded border-zinc-700 bg-zinc-950 text-red-500 shadow-sm focus:ring-red-500" @checked(old('schema_only', $job->schema_only ?? false))>
                            <label for="schema_only" class="ml-2">Solo estructura (sin datos)</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <x-input-label for="schedule_cron" value="Expresión cron (ej. 0 3 * * *; varias separadas por |)" />
                        <x-text-input id="schedule_cron" name="schedule_cron" class="mt-1 block w-full font-mono" value="{{ old('schedule_cron', $job->schedule_cron ?? '0 3 * * *') }}" required />
                        <x-input-error :messages="$errors->get('schedule_cron')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="retention_count" value="Retención (últimos N, vacío = ilimitado)" />
                        <x-text-input id="retention_count" type="number" name="retention_count" class="mt-1 block w-full" value="{{ old('retention_count', $job->retention_count) }}" />
                    </div>

                    <div class="mb-4 flex items-center">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" id="enabled" name="enabled" value="1" class="rounded border-zinc-700 bg-zinc-950 text-red-500 shadow-sm focus:ring-red-500" @checked(old('enabled', $job->enabled ?? true))>
                        <label for="enabled" class="ml-2">Activo</label>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Guardar</x-primary-button>
                        <a href="{{ route('backup-jobs.index') }}" class="text-zinc-400 hover:underline">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function backupJobToggleType(type) {
            const show = (id, on) => document.getElementById(id).style.display = on ? 'block' : 'none';
            show('fields-target', type !== 'database');
            show('fields-file-system', type === 'file' || type === 'system');
            show('fields-script', type === 'script');
            show('fields-database', type === 'database');
        }
        backupJobToggleType(document.getElementById('type').value);
    </script>
</x-app-layout>
