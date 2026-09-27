@props(['dim' => false])

<tr {{ $attributes->merge(['class' => 'border-t border-zinc-800/80 transition-colors hover:bg-zinc-800/30'.($dim ? ' opacity-60' : '')]) }}>{{ $slot }}</tr>
