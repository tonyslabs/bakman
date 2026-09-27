<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Jobs</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <x-table.card :count="$jobs->count()">
                <x-slot name="title">Backups y scripts programados</x-slot>
                <x-slot name="actions">
                    <x-button-link href="{{ route('backup-jobs.create') }}">+ Nuevo job</x-button-link>
                </x-slot>

                <thead>
                    <tr>
                        <x-table.th>Nombre</x-table.th>
                        <x-table.th>Tipo</x-table.th>
                        <x-table.th>Origen</x-table.th>
                        <x-table.th>Programación</x-table.th>
                        <x-table.th><span class="sr-only">Acciones</span></x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <x-table.row :dim="! $job->enabled">
                            <x-table.td>
                                <div class="font-medium text-zinc-100">{{ $job->name }}</div>
                                @unless ($job->enabled)
                                    <div class="text-xs text-zinc-500">desactivado</div>
                                @endunless
                            </x-table.td>
                            <x-table.td><x-badge>{{ $job->type }}</x-badge></x-table.td>
                            <x-table.td>
                                @if ($job->type === 'database')
                                    {{ $job->databaseConnection?->name ?? '—' }} <span class="text-zinc-500">/</span> <span class="font-mono text-xs">{{ $job->db_name }}</span>
                                @else
                                    {{ $job->target?->name ?? '—' }}
                                @endif
                            </x-table.td>
                            <x-table.td mono>
                                @foreach (array_filter(array_map('trim', explode('|', $job->schedule_cron))) as $cron)
                                    <div>{{ $cron }}</div>
                                @endforeach
                            </x-table.td>
                            <x-table.td>
                                <x-table.actions>
                                    <x-table.action :action="route('backup-jobs.run', $job)" variant="primary">Ejecutar</x-table.action>
                                    <x-table.action :href="route('backup-jobs.runs', $job)">Historial</x-table.action>
                                    <x-table.action :href="route('backup-jobs.edit', $job)">Editar</x-table.action>
                                    <x-table.action :action="route('backup-jobs.destroy', $job)" method="DELETE" confirm="¿Borrar el job {{ $job->name }}?" variant="danger">Borrar</x-table.action>
                                </x-table.actions>
                            </x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty colspan="5">Todavía no hay jobs.</x-table.empty>
                    @endforelse
                </tbody>
            </x-table.card>
        </div>
    </div>
</x-app-layout>
