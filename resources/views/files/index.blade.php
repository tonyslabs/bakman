@php
    use App\Support\Bytes;
    $tz = config('backups.timezone');
    $human = fn ($bytes) => Bytes::human($bytes);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold text-xl text-zinc-100 leading-tight">{{ $label }}</h2>
            @if ($total)
                <span class="font-sans text-sm text-zinc-500">
                    {{ $human($total - $free) }} usados de {{ $human($total) }} · {{ $human($free) }} libres
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <nav class="mb-4 flex flex-wrap items-center gap-1 text-sm text-zinc-400">
                <a href="{{ route('files.index') }}" class="hover:text-red-400 {{ $current === '' ? 'text-zinc-100' : '' }}">/</a>
                @foreach ($crumbs as $crumb)
                    <span class="text-zinc-600">/</span>
                    <a href="{{ route('files.index', ['path' => $crumb['path']]) }}"
                       class="hover:text-red-400 {{ $loop->last ? 'text-zinc-100' : '' }}">{{ $crumb['name'] }}</a>
                @endforeach
            </nav>

            <x-table.card :count="count($entries).' elementos'">
                <x-slot name="title">/{{ $current }}</x-slot>

                <thead>
                    <tr>
                        <x-table.th>Nombre</x-table.th>
                        <x-table.th align="right">Tamaño</x-table.th>
                        <x-table.th align="right">Modificado</x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @if ($parent !== null)
                        <x-table.row>
                            <x-table.td colspan="3">
                                <a href="{{ route('files.index', ['path' => $parent]) }}" class="text-zinc-400 hover:text-red-400">↰ ..</a>
                            </x-table.td>
                        </x-table.row>
                    @endif

                    @forelse ($entries as $entry)
                        <x-table.row>
                            <x-table.td>
                                @if (! $entry['readable'])
                                    <span class="text-zinc-600" title="Sin permiso de lectura"><span aria-hidden="true">🔒</span> {{ $entry['name'] }}{{ $entry['is_dir'] ? '/' : '' }}</span>
                                @elseif ($entry['is_dir'])
                                    <a href="{{ route('files.index', ['path' => $entry['path']]) }}" class="font-medium text-zinc-100 hover:text-red-400">
                                        <span aria-hidden="true" class="text-red-500">▸</span> {{ $entry['name'] }}/
                                    </a>
                                @else
                                    <a href="{{ route('files.download', ['path' => $entry['path']]) }}" class="text-zinc-300 hover:text-red-400" title="Descargar">
                                        <span aria-hidden="true" class="text-zinc-600">·</span> {{ $entry['name'] }}
                                    </a>
                                @endif
                            </x-table.td>
                            <x-table.td numeric muted>
                                @if ($entry['is_dir'])
                                    {{ $entry['items'] === null ? '—' : $entry['items'].' '.($entry['items'] === 1 ? 'elemento' : 'elementos') }}
                                @else
                                    {{ $entry['size'] === false || $entry['size'] === null ? '—' : $human($entry['size']) }}
                                @endif
                            </x-table.td>
                            <x-table.td numeric muted>
                                {{ $entry['modified'] ? \Illuminate\Support\Carbon::createFromTimestamp($entry['modified'], $tz)->format('Y-m-d H:i') : '—' }}
                            </x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty colspan="3">{{ $readable ? 'Carpeta vacía.' : 'Sin permiso para leer esta carpeta.' }}</x-table.empty>
                    @endforelse
                </tbody>
            </x-table.card>
        </div>
    </div>
</x-app-layout>
