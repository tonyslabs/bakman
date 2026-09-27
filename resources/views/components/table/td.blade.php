{{--
    `numeric` alinea a la derecha con cifras de ancho fijo; `mono` para hosts, rutas y cron.
    Color del texto: `strong` (principal), por defecto (secundario) o `muted` (terciario).
    No pasar clases text-* de color: chocarían con estas.
--}}
@props(['align' => 'left', 'numeric' => false, 'mono' => false, 'muted' => false, 'strong' => false])

@php
    $classes = 'px-4 py-3 align-middle first:pl-6 last:pr-6';
    $classes .= ($numeric || $align === 'right') ? ' text-right' : '';
    $classes .= $numeric ? ' tabular-nums whitespace-nowrap' : '';
    $classes .= $mono ? ' font-mono text-xs' : '';
    $classes .= $strong ? ' font-medium text-zinc-100' : ($muted ? ' text-zinc-500' : ' text-zinc-300');
@endphp

<td {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</td>
