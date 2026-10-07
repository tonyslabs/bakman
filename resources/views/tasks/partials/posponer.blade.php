{{-- Menú ⏱: mover la fecha de vencimiento (presets de config/tasks.php o una fecha). --}}
<div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false" @click.stop>
    <button type="button" @click="open = ! open" class="rounded-md px-1.5 py-1 text-zinc-500 hover:bg-zinc-800 hover:text-zinc-200" title="Mover fecha (p = mañana)">⏱</button>
    <div x-show="open" x-cloak x-transition.opacity
         class="absolute right-0 z-20 mt-1 w-48 rounded-md border border-zinc-700 bg-zinc-900 py-1 text-sm shadow-lg">
        @foreach (config('tasks.posponer') as $label => $preset)
            <button type="button" class="block w-full px-3 py-1.5 text-left text-zinc-300 hover:bg-zinc-800"
                    @click="open = false; fecha($el.closest('[data-task]'), { preset: @js($preset) })">{{ $label }}</button>
        @endforeach
        <div class="border-t border-zinc-800 px-3 py-2">
            <input type="date" class="w-full rounded border-zinc-700 bg-zinc-950 py-1 text-xs text-zinc-300 [color-scheme:dark]"
                   @change="open = false; fecha($el.closest('[data-task]'), { vence: $event.target.value })">
        </div>
        @if ($task['vence'])
            <button type="button" class="block w-full px-3 py-1.5 text-left text-xs text-zinc-500 hover:bg-zinc-800"
                    @click="open = false; fecha($el.closest('[data-task]'), { vence: null })">Quitar fecha</button>
        @endif
    </div>
</div>
