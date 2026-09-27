@props(['label', 'open' => false])

<div x-data="{ open: {{ $open ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open"
        class="w-full flex items-center justify-between rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wider text-zinc-500 hover:text-zinc-300 transition duration-150 ease-in-out">
        <span>{{ $label }}</span>
        <svg :class="open ? 'rotate-90' : ''" class="w-3 h-3 shrink-0 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
    </button>
    <div x-show="open" x-transition class="mt-1 ml-3 space-y-1 border-l border-zinc-800 pl-2">
        {{ $slot }}
    </div>
</div>
