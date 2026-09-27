@props(['title', 'sectionKey', 'data', 'connectionA', 'dbA', 'connectionB', 'dbB'])

<div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
    <h3 class="font-semibold mb-3">{{ $title }}</h3>

    @if (count($data['only_in_a']) || count($data['only_in_b']))
        <div class="grid grid-cols-2 gap-4 text-sm mb-4">
            <div>
                <p class="font-medium text-zinc-300 mb-1">Solo en A ({{ $connectionA->name }}/{{ $dbA }})</p>
                @forelse ($data['only_in_a'] as $name)
                    <div class="text-zinc-400">{{ $name }}</div>
                @empty
                    <div class="text-zinc-500">—</div>
                @endforelse
            </div>
            <div>
                <p class="font-medium text-zinc-300 mb-1">Solo en B ({{ $connectionB->name }}/{{ $dbB }})</p>
                @forelse ($data['only_in_b'] as $name)
                    <div class="text-zinc-400">{{ $name }}</div>
                @empty
                    <div class="text-zinc-500">—</div>
                @endforelse
            </div>
        </div>
    @endif

    @if (empty($data['items']))
        <p class="text-zinc-500 text-sm">No hay elementos en común.</p>
    @else
        <div class="space-y-1 text-sm">
            @foreach ($data['items'] as $item)
                <div class="flex items-center justify-between border-b border-zinc-800 py-1.5">
                    <span class="text-zinc-300">{{ $item['name'] }}</span>
                    @if ($item['identical'])
                        <span class="text-green-400 text-xs">idéntica</span>
                    @else
                        <span class="text-yellow-400 text-xs">difiere</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <x-database-diff.sync-form :section="$sectionKey" :connection-a="$connectionA" :db-a="$dbA" :connection-b="$connectionB" :db-b="$dbB" />
</div>
