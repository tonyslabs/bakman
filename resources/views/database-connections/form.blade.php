<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">
            {{ $connection->exists ? 'Editar conexión' : 'Nueva conexión' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ $connection->exists ? route('database-connections.update', $connection) : route('database-connections.store') }}" x-data="{ testing: false, result: null }">
                    @csrf
                    @if ($connection->exists) @method('PUT') @endif

                    <div class="mb-4">
                        <x-input-label for="name" value="Nombre" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $connection->name) }}" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="host" value="Host" />
                        <x-text-input id="host" name="host" class="mt-1 block w-full" value="{{ old('host', $connection->host) }}" required />
                        <x-input-error :messages="$errors->get('host')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="port" value="Puerto" />
                        <x-text-input id="port" type="number" name="port" class="mt-1 block w-full" value="{{ old('port', $connection->port ?? 3306) }}" required />
                        <x-input-error :messages="$errors->get('port')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="username" value="Usuario" />
                        <x-text-input id="username" name="username" class="mt-1 block w-full" value="{{ old('username', $connection->username) }}" required />
                        <x-input-error :messages="$errors->get('username')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="password" value="Contraseña" />
                        <x-text-input id="password" type="password" name="password" class="mt-1 block w-full" placeholder="{{ $connection->exists ? '(sin cambios)' : '' }}" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="notes" value="Notas" />
                        <textarea id="notes" name="notes" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500">{{ old('notes', $connection->notes) }}</textarea>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Guardar</x-primary-button>
                        <button type="button"
                            :disabled="testing"
                            @click="
                                testing = true; result = null;
                                fetch('{{ route('database-connections.test') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    },
                                    body: JSON.stringify({
                                        id: {{ $connection->exists ? $connection->id : 'null' }},
                                        host: document.getElementById('host').value,
                                        port: document.getElementById('port').value,
                                        username: document.getElementById('username').value,
                                        password: document.getElementById('password').value,
                                    }),
                                })
                                    .then(r => r.json())
                                    .then(data => { result = data; })
                                    .catch(() => { result = { success: false, message: 'Error de red.' }; })
                                    .finally(() => { testing = false; })
                            "
                            class="px-4 py-2 border border-zinc-700 text-zinc-200 rounded disabled:opacity-50"
                        >
                            <span x-show="!testing">Probar conexión</span>
                            <span x-show="testing" x-cloak>Probando…</span>
                        </button>
                        <a href="{{ route('database-connections.index') }}" class="text-zinc-400 hover:underline">Cancelar</a>
                    </div>
                    <p x-show="result" x-cloak :class="result?.success ? 'text-green-400' : 'text-red-400'" x-text="result?.message" class="mt-3 text-sm"></p>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
