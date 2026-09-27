@php
    use App\Support\Bytes;
    $tz = config('backups.timezone');
    $hasCritical = collect($attention)->contains(fn ($a) => $a['kind'] !== 'overdue');
    $used = $storage['total'] ? $storage['total'] - $storage['free'] : null;
    $usedPct = $used !== null ? (int) round($used / $storage['total'] * 100) : null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold text-xl text-zinc-100 leading-tight">{{ __('Dashboard') }}</h2>
            <span class="font-sans text-sm text-zinc-500">{{ now($tz)->format('Y-m-d H:i') }}</span>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flash class="mb-0" />

            {{-- Fila de indicadores --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg bg-zinc-900 p-5 ring-1 ring-zinc-800 shadow-sm">
                    <div class="text-sm text-zinc-400">Estado</div>
                    @if (empty($attention))
                        <div class="mt-2 text-2xl font-semibold text-zinc-100"><span aria-hidden="true" class="text-green-400">✓</span> Todo en orden</div>
                    @else
                        <div class="mt-2 text-2xl font-semibold text-zinc-100">
                            <span aria-hidden="true" class="{{ $hasCritical ? 'text-red-400' : 'text-yellow-400' }}">!</span>
                            {{ count($attention) }} {{ count($attention) === 1 ? 'requiere' : 'requieren' }} atención
                        </div>
                    @endif
                    <div class="mt-1 text-sm text-zinc-500">{{ $jobsEnabled }} de {{ $jobsTotal }} jobs activos</div>
                </div>

                <div class="rounded-lg bg-zinc-900 p-5 ring-1 ring-zinc-800 shadow-sm">
                    <div class="text-sm text-zinc-400">Ejecuciones últimas 24 h</div>
                    <div class="mt-2 text-2xl font-semibold text-zinc-100 tabular-nums">{{ $ok24h + $failed24h }}</div>
                    <div class="mt-1 text-sm tabular-nums">
                        <span class="text-zinc-400"><span aria-hidden="true" class="text-green-400">✓</span> {{ $ok24h }} {{ $ok24h === 1 ? 'éxito' : 'éxitos' }}</span>
                        <span class="ml-2 {{ $failed24h ? 'text-zinc-200' : 'text-zinc-500' }}"><span aria-hidden="true" class="{{ $failed24h ? 'text-red-400' : '' }}">✕</span> {{ $failed24h }} {{ $failed24h === 1 ? 'fallo' : 'fallos' }}</span>
                    </div>
                </div>

                <div class="rounded-lg bg-zinc-900 p-5 ring-1 ring-zinc-800 shadow-sm">
                    <div class="text-sm text-zinc-400">Último backup de base de datos</div>
                    @if ($lastDbBackup)
                        <div class="mt-2 text-2xl font-semibold text-zinc-100" title="{{ $lastDbBackup->started_at->timezone($tz)->format('Y-m-d H:i:s') }}">{{ $lastDbBackup->started_at->diffForHumans() }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ $lastDbBackup->backupJob->name }} · {{ Bytes::human($lastDbBackup->size_bytes) }}</div>
                    @else
                        <div class="mt-2 text-2xl font-semibold text-zinc-500">Ninguno</div>
                        <div class="mt-1 text-sm text-zinc-500">No hay backups de BD exitosos</div>
                    @endif
                </div>

                <div class="rounded-lg bg-zinc-900 p-5 ring-1 ring-zinc-800 shadow-sm">
                    <div class="text-sm text-zinc-400">{{ config('backups.browse_label') }}</div>
                    @if ($usedPct !== null)
                        <div class="mt-2 text-2xl font-semibold text-zinc-100 tabular-nums">{{ $usedPct }}% <span class="text-base font-normal text-zinc-500">usado</span></div>
                        <div class="mt-2 h-1.5 w-full rounded-full bg-zinc-800" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $usedPct }}" aria-label="Uso del disco">
                            <div class="h-1.5 rounded-full {{ $usedPct >= 90 ? 'bg-red-500' : ($usedPct >= 75 ? 'bg-yellow-400' : 'bg-zinc-300') }}" style="width: {{ max($usedPct, 1) }}%"></div>
                        </div>
                        <div class="mt-2 text-sm text-zinc-500 tabular-nums">{{ Bytes::human($storage['free']) }} libres de {{ Bytes::human($storage['total']) }}</div>
                    @else
                        <div class="mt-2 text-2xl font-semibold text-zinc-500">Sin montar</div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Requiere atención --}}
                <section class="overflow-hidden rounded-lg bg-zinc-900 ring-1 ring-zinc-800 shadow-sm">
                    <h3 class="border-b border-zinc-800 px-6 py-3 text-sm font-medium text-zinc-200">Requiere atención</h3>
                    @forelse ($attention as $item)
                        <a href="{{ route('backup-jobs.runs', $item['job']) }}" class="block border-b border-zinc-800 px-6 py-3 last:border-0 transition-colors hover:bg-zinc-800/30">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-zinc-100">{{ $item['job']->name }}</span>
                                @if ($item['kind'] === 'overdue')
                                    <span class="text-sm text-yellow-400"><span aria-hidden="true">!</span> atrasado</span>
                                @else
                                    <x-run-status class="text-sm" :status="$item['run']?->status" :stuck="$item['kind'] === 'stuck'" />
                                @endif
                            </div>
                            <div class="mt-1 truncate text-sm text-zinc-500" title="{{ $item['text'] }}">{{ $item['text'] }}</div>
                        </a>
                    @empty
                        <p class="px-6 py-10 text-center text-sm text-zinc-500">Nada pendiente: ningún job fallando, atrasado ni colgado.</p>
                    @endforelse
                </section>

                {{-- Próximas ejecuciones --}}
                <section class="overflow-hidden rounded-lg bg-zinc-900 ring-1 ring-zinc-800 shadow-sm">
                    <h3 class="border-b border-zinc-800 px-6 py-3 text-sm font-medium text-zinc-200">Próximas ejecuciones</h3>
                    @forelse ($upcoming as $item)
                        <div class="flex items-center justify-between gap-3 border-b border-zinc-800 px-6 py-2.5 text-sm last:border-0">
                            <span class="text-zinc-200">{{ $item['job']->name }}</span>
                            <span class="tabular-nums text-zinc-400" title="{{ $item['at']->timezone($tz)->format('Y-m-d H:i') }}">
                                {{ $item['at']->timezone($tz)->format('H:i') }} <span class="text-zinc-600">·</span> {{ $item['at']->diffForHumans() }}
                            </span>
                        </div>
                    @empty
                        <p class="px-6 py-10 text-center text-sm text-zinc-500">No hay jobs activos programados.</p>
                    @endforelse
                </section>
            </div>

            {{-- Actividad reciente --}}
            <x-table.card>
                <x-slot name="title">Actividad reciente</x-slot>
                <x-slot name="actions">
                    <x-table.action :href="route('monitor')">Ver monitor →</x-table.action>
                </x-slot>

                <thead>
                    <tr>
                        <x-table.th>Job</x-table.th>
                        <x-table.th>Estado</x-table.th>
                        <x-table.th>Inicio</x-table.th>
                        <x-table.th align="right">Duración</x-table.th>
                        <x-table.th align="right">Tamaño</x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recent as $run)
                        <x-table.row>
                            <x-table.td strong>
                                @if ($run->backupJob)
                                    <a href="{{ route('backup-jobs.runs', $run->backupJob) }}" class="hover:text-red-400">{{ $run->backupJob->name }}</a>
                                @else
                                    —
                                @endif
                            </x-table.td>
                            <x-table.td><x-run-status :status="$run->status" /></x-table.td>
                            <x-table.td muted title="{{ $run->started_at?->timezone($tz)->format('Y-m-d H:i:s') }}">{{ $run->started_at?->diffForHumans() }}</x-table.td>
                            <x-table.td numeric muted>{{ Bytes::duration($run->duration_seconds) }}</x-table.td>
                            <x-table.td numeric muted>{{ $run->size_bytes ? Bytes::human($run->size_bytes) : '—' }}</x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty colspan="5">Todavía no hay ejecuciones.</x-table.empty>
                    @endforelse
                </tbody>
            </x-table.card>

            {{-- Inventario --}}
            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-zinc-500">
                <span>Backups: <span class="text-zinc-300 tabular-nums">{{ Bytes::human($storage['backups']['bytes']) }}</span> en {{ $storage['backups']['files'] }} archivos</span>
                <span>Logs: <span class="text-zinc-300 tabular-nums">{{ Bytes::human($storage['logs']['bytes']) }}</span> en {{ $storage['logs']['files'] }} archivos</span>
                <span>Jobs: @foreach ($jobsByType as $type => $n)<span class="text-zinc-300">{{ $n }}</span> {{ $type }}@if (! $loop->last), @endif @endforeach</span>
                <a href="{{ route('database-connections.index') }}" class="hover:text-red-400">Conexiones BD: <span class="text-zinc-300">{{ $connections }}</span></a>
                <a href="{{ route('targets.index') }}" class="hover:text-red-400">Targets: <span class="text-zinc-300">{{ $targets }}</span></a>
            </div>
        </div>
    </div>
</x-app-layout>
