@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-zinc-950 border-zinc-700 text-zinc-100 placeholder-zinc-600 focus:border-red-500 focus:ring-red-500 rounded-md shadow-sm']) }}>
