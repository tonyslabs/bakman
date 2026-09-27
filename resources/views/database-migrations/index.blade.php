@php use App\Support\Bytes; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Migraciones de base de datos</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <x-table.card :count="$migrations->total()">
                <x-slot name="title">Dump → restore entre conexiones</x-slot>
                <x-slot name="actions">
                    <x-button-link href="{{ route('database-migrations.create') }}">+ Nueva migración</x-button-link>
                </x-slot>

                <thead>
                    <tr>
                        <x-table.th>Origen</x-table.th>
                        <x-table.th>Destino</x-table.th>
                        <x-table.th>Estado</x-table.th>
                        <x-table.th align="right">Duración</x-table.th>
                        <x-table.th align="right">Cuándo</x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($migrations as $migration)
                        <x-table.row>
                            <x-table.td><span class="text-zinc-100">{{ $migration->sourceConnection->name }}</span> <span class="text-zinc-500">/</span> <span class="font-mono text-xs">{{ $migration->source_db_name }}</span></x-table.td>
                            <x-table.td><span class="text-zinc-100">{{ $migration->targetConnection->name }}</span> <span class="text-zinc-500">/</span> <span class="font-mono text-xs">{{ $migration->target_db_name }}</span></x-table.td>
                            <x-table.td><x-run-status :status="$migration->status" /></x-table.td>
                            <x-table.td numeric muted>{{ Bytes::duration($migration->duration_seconds) }}</x-table.td>
                            <x-table.td numeric muted title="{{ $migration->created_at->timezone(config('backups.timezone'))->format('Y-m-d H:i:s') }}">{{ $migration->created_at->diffForHumans() }}</x-table.td>
                        </x-table.row>
                        @if ($migration->status === 'failed' && $migration->error_message)
                            <x-table.detail colspan="5" variant="error">{{ $migration->error_message }}</x-table.detail>
                        @endif
                    @empty
                        <x-table.empty colspan="5">No hay migraciones todavía.</x-table.empty>
                    @endforelse
                </tbody>
            </x-table.card>

            <div class="mt-4">{{ $migrations->links() }}</div>
        </div>
    </div>
</x-app-layout>
