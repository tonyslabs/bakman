@props(['active' => false])

@php
$classes = ($active ?? false)
    ? 'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium bg-red-600/10 text-red-400 border-l-2 border-red-500 transition duration-150 ease-in-out'
    : 'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-zinc-400 border-l-2 border-transparent hover:text-zinc-100 hover:bg-zinc-900 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
