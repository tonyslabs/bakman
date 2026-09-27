@props(['section', 'connectionA', 'dbA', 'connectionB', 'dbB'])

<form method="POST" action="{{ route('database-diff-syncs.store') }}"
    class="mt-4 pt-4 border-t border-zinc-800 flex flex-wrap items-center gap-x-4 gap-y-2"
    x-data="{ source: 'a' }"
    data-label-a="{{ $connectionA->name }}/{{ $dbA }}"
    data-label-b="{{ $connectionB->name }}/{{ $dbB }}"
    @submit.prevent="confirmAndSync($el, source)"
>
    @csrf
    <input type="hidden" name="connection_a_id" value="{{ $connectionA->id }}">
    <input type="hidden" name="db_a" value="{{ $dbA }}">
    <input type="hidden" name="connection_b_id" value="{{ $connectionB->id }}">
    <input type="hidden" name="db_b" value="{{ $dbB }}">
    <input type="hidden" name="section" value="{{ $section }}">

    <span class="text-xs text-zinc-500">Fuente de la verdad:</span>

    <label class="flex items-center gap-1.5 text-sm text-zinc-300 cursor-pointer">
        <input type="radio" name="source_side" value="a" x-model="source" class="text-red-600 focus:ring-red-500 bg-zinc-950 border-zinc-700">
        <span>{{ $connectionA->name }}/{{ $dbA }}</span>
    </label>
    <label class="flex items-center gap-1.5 text-sm text-zinc-300 cursor-pointer">
        <input type="radio" name="source_side" value="b" x-model="source" class="text-red-600 focus:ring-red-500 bg-zinc-950 border-zinc-700">
        <span>{{ $connectionB->name }}/{{ $dbB }}</span>
    </label>

    <button type="submit" class="ml-auto px-4 py-1.5 bg-red-600 hover:bg-red-500 text-white text-xs font-semibold uppercase tracking-widest rounded-md transition">
        Continuar
    </button>
</form>
