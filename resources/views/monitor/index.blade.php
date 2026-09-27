@php use App\Support\Bytes; $tz = config('backups.timezone'); @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Monitor</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <x-table.card :count="$rows->count()">
                <x-slot name="title">Estado de los jobs</x-slot>
                <x-slot name="actions">
                    <x-table.action :href="route('backup-jobs.index')">Administrar jobs</x-table.action>
                </x-slot>

                <thead>
                    <tr>
                        <x-table.th>Job</x-table.th>
                        <x-table.th>Estado</x-table.th>
                        <x-table.th>Última ejecución</x-table.th>
                        <x-table.th>Último éxito</x-table.th>
                        <x-table.th>Próxima</x-table.th>
                        <x-table.th><span class="sr-only">Acciones</span></x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $job = $row['job']; $last = $row['last']; @endphp
                        <x-table.row :dim="! $job->enabled">
                            <x-table.td>
                                <div class="font-medium text-zinc-100">{{ $job->name }}</div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-xs text-zinc-500">
                                    <x-badge>{{ $job->type }}</x-badge>
                                    <span>{{ $job->type === 'database' ? ($job->databaseConnection?->name.' / '.$job->db_name) : ($job->target?->name ?? '—') }}</span>
                                    @unless ($job->enabled) <span>· desactivado</span> @endunless
                                </div>
                            </x-table.td>
                            <x-table.td>
                                <x-run-status :status="$last?->status" :stuck="$row['stuck']" />
                                @if ($row['overdue'])
                                    <div class="text-xs text-yellow-400"><span aria-hidden="true">!</span> atrasado</div>
                                @endif
                            </x-table.td>
                            <x-table.td>
                                @if ($last)
                                    <div title="{{ $last->started_at?->timezone($tz)->format('Y-m-d H:i:s') }}">{{ $last->started_at?->diffForHumans() }}</div>
                                    <div class="text-xs tabular-nums text-zinc-500">{{ Bytes::duration($last->duration_seconds) }}@if ($last->size_bytes) · {{ Bytes::human($last->size_bytes) }}@endif</div>
                                @else
                                    <span class="text-zinc-500">—</span>
                                @endif
                            </x-table.td>
                            <x-table.td :muted="! $row['lastSuccess']">{{ $row['lastSuccess']?->started_at?->diffForHumans() ?? '—' }}</x-table.td>
                            <x-table.td :muted="! $row['next']">
                                @if ($row['next'])
                                    <div class="tabular-nums">{{ $row['next']->timezone($tz)->format('H:i') }}</div>
                                    <div class="text-xs text-zinc-500">{{ $row['next']->diffForHumans() }}</div>
                                @else
                                    —
                                @endif
                            </x-table.td>
                            <x-table.td>
                                <x-table.actions>
                                    <x-table.action :action="route('backup-jobs.run', $job)" variant="primary">Ejecutar</x-table.action>
                                    <x-table.action :href="route('backup-jobs.runs', $job)">Historial</x-table.action>
                                </x-table.actions>
                            </x-table.td>
                        </x-table.row>
                        @if ($last?->status === 'failed' && $last->error_message)
                            <x-table.detail colspan="6" variant="error">{{ \Illuminate\Support\Str::limit($last->error_message, 400) }}</x-table.detail>
                        @endif
                    @empty
                        <x-table.empty colspan="6">No hay jobs todavía. <a href="{{ route('backup-jobs.create') }}" class="text-red-400 hover:underline">Crear uno</a>.</x-table.empty>
                    @endforelse
                </tbody>
            </x-table.card>
        </div>
    </div>
</x-app-layout>
