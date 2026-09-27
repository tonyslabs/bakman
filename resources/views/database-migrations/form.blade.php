<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Nueva migración</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                @if ($connections->isEmpty())
                    <p class="text-zinc-500">No hay conexiones registradas. <a href="{{ route('database-connections.create') }}" class="text-red-500 hover:underline">Crear una</a> primero.</p>
                @else
                    <form method="POST" action="{{ route('database-migrations.store') }}">
                        @csrf

                        <fieldset class="mb-6 border border-zinc-800 rounded-md p-4">
                            <legend class="text-sm font-medium text-zinc-300 px-1">Origen</legend>

                            <div class="mb-4">
                                <x-input-label for="source_connection_id" value="Conexión" />
                                <select id="source_connection_id" name="source_connection_id" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500" required>
                                    <option value="">—</option>
                                    @foreach ($connections as $connection)
                                        <option value="{{ $connection->id }}" @selected(old('source_connection_id') == $connection->id)>{{ $connection->name }} ({{ $connection->host }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('source_connection_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="source_db_name" value="Base de datos" />
                                <x-text-input id="source_db_name" name="source_db_name" class="mt-1 block w-full" value="{{ old('source_db_name') }}" required />
                                <x-input-error :messages="$errors->get('source_db_name')" class="mt-2" />
                            </div>
                        </fieldset>

                        <fieldset class="mb-6 border border-zinc-800 rounded-md p-4">
                            <legend class="text-sm font-medium text-zinc-300 px-1">Destino</legend>

                            <div class="mb-4">
                                <x-input-label for="target_connection_id" value="Conexión" />
                                <select id="target_connection_id" name="target_connection_id" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500" required>
                                    <option value="">—</option>
                                    @foreach ($connections as $connection)
                                        <option value="{{ $connection->id }}" @selected(old('target_connection_id') == $connection->id)>{{ $connection->name }} ({{ $connection->host }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('target_connection_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="target_db_name" value="Base de datos (se crea si no existe)" />
                                <x-text-input id="target_db_name" name="target_db_name" class="mt-1 block w-full" value="{{ old('target_db_name') }}" required />
                                <x-input-error :messages="$errors->get('target_db_name')" class="mt-2" />
                            </div>
                        </fieldset>

                        <div class="mb-4">
                            <x-input-label for="tables_text" value="Tablas (una por línea, vacío = base completa)" />
                            <textarea id="tables_text" name="tables_text" rows="4" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 font-mono text-sm">{{ old('tables_text') }}</textarea>
                            <x-input-error :messages="$errors->get('tables_text')" class="mt-2" />
                        </div>

                        <p class="mb-4 text-sm text-zinc-500">La migración corre en segundo plano y queda en el historial con su estado.</p>

                        <div class="flex items-center gap-4">
                            <x-primary-button>Migrar</x-primary-button>
                            <a href="{{ route('database-migrations.index') }}" class="text-zinc-400 hover:underline">Cancelar</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
