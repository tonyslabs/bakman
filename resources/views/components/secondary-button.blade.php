<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-zinc-900 border border-zinc-700 rounded-md font-semibold text-xs text-zinc-300 uppercase tracking-widest shadow-sm hover:bg-zinc-950 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
