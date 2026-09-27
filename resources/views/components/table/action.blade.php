{{--
    Acción de fila. Con `href` es un enlace; con `action` es un formulario
    (`method` para DELETE/PUT, `confirm` pide confirmación). Sin ninguno, un botón.
    variant: default | primary | danger
--}}
@props(['href' => null, 'action' => null, 'method' => 'POST', 'confirm' => null, 'variant' => 'default'])

@php
    $classes = 'inline-flex items-center rounded px-2 py-1 text-xs font-medium transition-colors disabled:opacity-50 '.match ($variant) {
        'primary' => 'text-red-400 hover:bg-red-500/10 hover:text-red-300',
        'danger' => 'text-zinc-500 hover:bg-red-500/10 hover:text-red-400',
        default => 'text-zinc-400 hover:bg-zinc-800 hover:text-zinc-100',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@elseif ($action)
    <form method="POST" action="{{ $action }}" class="inline" @if ($confirm) onsubmit="return confirm(@js($confirm))" @endif>
        @csrf
        @if (strtoupper($method) !== 'POST') @method($method) @endif
        <button type="submit" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
    </form>
@else
    <button type="button" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
