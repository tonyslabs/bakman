@props(['align' => 'left'])

<th {{ $attributes->merge(['scope' => 'col', 'class' => 'whitespace-nowrap bg-zinc-950/40 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 first:pl-6 last:pr-6 '.($align === 'right' ? 'text-right' : 'text-left')]) }}>{{ $slot }}</th>
