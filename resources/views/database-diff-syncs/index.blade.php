@php use App\Support\Bytes; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Sincronizaciones</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <x-table.card :count="$syncs->total()">
                <x-slot name="title">Sincronizaciones desde Comparar</x-slot>
                <x-slot name="actions">
                    <a href="{{ route('database-diff.create') }}" class="text-xs font-medium text-zinc-400 hover:text-zinc-100">← Comparar bases de datos</a>
                </x-slot>

                <thead>
                    <tr>
                        <x-table.th>Sección</x-table.th>
                        <x-table.th>Fuente</x-table.th>
                        <x-table.th>Destino</x-table.th>
                        <x-table.th>Estado</x-table.th>
                        <x-table.th align="right">Duración</x-table.th>
                        <x-table.th align="right">Cuándo</x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($syncs as $sync)
                        @php
                            $sourceConn = $sync->source_side === 'a' ? $sync->connectionA : $sync->connectionB;
                            $sourceDb = $sync->source_side === 'a' ? $sync->db_a : $sync->db_b;
                            $destConn = $sync->source_side === 'a' ? $sync->connectionB : $sync->connectionA;
                            $destDb = $sync->source_side === 'a' ? $sync->db_b : $sync->db_a;
                        @endphp
                        <x-table.row>
                            <x-table.td><x-badge class="capitalize">{{ $sync->section }}</x-badge></x-table.td>
                            <x-table.td><span class="text-zinc-100">{{ $sourceConn->name }}</span> <span class="text-zinc-500">/</span> <span class="font-mono text-xs">{{ $sourceDb }}</span></x-table.td>
                            <x-table.td><span class="text-zinc-100">{{ $destConn->name }}</span> <span class="text-zinc-500">/</span> <span class="font-mono text-xs">{{ $destDb }}</span></x-table.td>
                            <x-table.td><x-run-status :status="$sync->status" /></x-table.td>
                            <x-table.td numeric muted>{{ Bytes::duration($sync->duration_seconds) }}</x-table.td>
                            <x-table.td numeric muted title="{{ $sync->created_at->timezone(config('backups.timezone'))->format('Y-m-d H:i:s') }}">{{ $sync->created_at->diffForHumans() }}</x-table.td>
                        </x-table.row>
                        @if ($sync->status === 'failed' && $sync->error_message)
                            <x-table.detail colspan="6" variant="error">{{ $sync->error_message }}</x-table.detail>
                        @elseif ($sync->status === 'success' && $sync->summary)
                            <x-table.detail colspan="6">{{ implode("\n", $sync->summary) }}</x-table.detail>
                        @endif
                    @empty
                        <x-table.empty colspan="6">No hay sincronizaciones todavía.</x-table.empty>
                    @endforelse
                </tbody>
            </x-table.card>

            <div class="mt-4">{{ $syncs->links() }}</div>
        </div>
    </div>
</x-app-layout>
