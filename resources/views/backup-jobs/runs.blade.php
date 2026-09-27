@php use App\Support\Bytes; $tz = config('backups.timezone'); @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Historial — {{ $job->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <x-table.card :count="$runs->total().' ejecuciones'">
                <x-slot name="title">{{ $job->type === 'script' ? 'Ejecuciones del script' : 'Backups generados' }}</x-slot>
                <x-slot name="actions">
                    <x-table.action :href="route('monitor')">← Monitor</x-table.action>
                    <x-table.action :action="route('backup-jobs.run', $job)" variant="primary">Ejecutar ahora</x-table.action>
                </x-slot>

                <thead>
                    <tr>
                        <x-table.th>Inicio</x-table.th>
                        <x-table.th>Estado</x-table.th>
                        <x-table.th align="right">Duración</x-table.th>
                        <x-table.th align="right">Tamaño</x-table.th>
                        <x-table.th><span class="sr-only">Archivo</span></x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <x-table.row>
                            <x-table.td>
                                <div class="tabular-nums text-zinc-100">{{ $run->started_at?->timezone($tz)->format('Y-m-d H:i:s') }}</div>
                                <div class="text-xs text-zinc-500">{{ $run->started_at?->diffForHumans() }}</div>
                            </x-table.td>
                            <x-table.td><x-run-status :status="$run->status" /></x-table.td>
                            <x-table.td numeric muted>{{ Bytes::duration($run->duration_seconds) }}</x-table.td>
                            <x-table.td numeric muted>{{ $run->size_bytes ? Bytes::human($run->size_bytes) : '—' }}</x-table.td>
                            <x-table.td>
                                <x-table.actions>
                                    @if ($run->output_path)
                                        <x-table.action :href="route('backup-job-runs.download', $run)">{{ $job->type === 'script' ? 'Ver log' : 'Descargar' }}</x-table.action>
                                    @endif
                                </x-table.actions>
                            </x-table.td>
                        </x-table.row>
                        @if ($run->status === 'failed' && $run->error_message)
                            <x-table.detail colspan="5" variant="error">{{ $run->error_message }}</x-table.detail>
                        @endif
                    @empty
                        <x-table.empty colspan="5">Este job todavía no se ha ejecutado.</x-table.empty>
                    @endforelse
                </tbody>
            </x-table.card>

            <div class="mt-4">{{ $runs->links() }}</div>
        </div>
    </div>
</x-app-layout>
