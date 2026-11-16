@props(['label', 'open' => false, 'addHref' => null, 'addTitle' => 'Agregar'])

<div x-data="{ open: {{ $open ? 'true' : 'false' }} }">
    <div class="flex items-center">
        <button type="button" @click="open = !open"
            class="flex-1 flex items-center justify-between rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-500 hover:text-zinc-300 transition duration-150 ease-in-out">
            <span>{{ $label }}</span>
            <svg :class="open ? 'rotate-90' : ''" class="w-3 h-3 shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
        @if ($addHref)
            <a href="{{ $addHref }}" title="{{ $addTitle }}" aria-label="{{ $addTitle }}"
               class="ml-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-zinc-500 hover:bg-zinc-900 hover:text-red-400 transition duration-150 ease-in-out">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14" /></svg>
            </a>
        @endif
    </div>
    <div x-show="open" x-transition class="mt-1 ml-3 space-y-1 border-l border-zinc-800 pl-2">
        {{ $slot }}
    </div>
</div>
