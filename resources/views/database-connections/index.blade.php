<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Conexiones de base de datos</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <x-table.card :count="$connections->count()">
                <x-slot name="title">Servidores MySQL / MariaDB</x-slot>
                <x-slot name="actions">
                    <x-button-link href="{{ route('database-connections.create') }}">+ Nueva conexión</x-button-link>
                </x-slot>

                <thead>
                    <tr>
                        <x-table.th>Nombre</x-table.th>
                        <x-table.th>Servidor</x-table.th>
                        <x-table.th>Usuario</x-table.th>
                        <x-table.th><span class="sr-only">Acciones</span></x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($connections as $connection)
                        <x-table.row x-data="{ testing: false, result: null }">
                            <x-table.td strong>{{ $connection->name }}</x-table.td>
                            <x-table.td mono>{{ $connection->host }}<span class="text-zinc-500">:{{ $connection->port }}</span></x-table.td>
                            <x-table.td mono>{{ $connection->username }}</x-table.td>
                            <x-table.td>
                                <x-table.actions>
                                    <span x-show="result" x-cloak class="mr-2 inline-flex items-center gap-1 text-xs" :class="result?.success ? 'text-green-400' : 'text-red-400'">
                                        <span aria-hidden="true" x-text="result?.success ? '✓' : '✕'"></span><span x-text="result?.message"></span>
                                    </span>
                                    <x-table.action
                                        x-bind:disabled="testing"
                                        x-on:click="
                                            testing = true; result = null;
                                            fetch('{{ route('database-connections.test') }}', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'Accept': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                },
                                                body: JSON.stringify({ id: {{ $connection->id }} }),
                                            })
                                                .then(r => r.json())
                                                .then(data => { result = data; })
                                                .catch(() => { result = { success: false, message: 'Error de red.' }; })
                                                .finally(() => { testing = false; })
                                        "
                                    >
                                        <span x-show="!testing">Probar</span>
                                        <span x-show="testing" x-cloak>Probando…</span>
                                    </x-table.action>
                                    <x-table.action :href="route('database-connections.edit', $connection)">Editar</x-table.action>
                                    <x-table.action :action="route('database-connections.destroy', $connection)" method="DELETE" confirm="¿Borrar la conexión {{ $connection->name }}?" variant="danger">Borrar</x-table.action>
                                </x-table.actions>
                            </x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty colspan="4">Todavía no hay conexiones.</x-table.empty>
                    @endforelse
                </tbody>
            </x-table.card>
        </div>
    </div>
</x-app-layout>
