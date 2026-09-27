{{-- Estado de un run, migración o sincronización: siempre ícono + texto, nunca solo color. --}}
@props(['status' => null, 'stuck' => false, 'label' => null])

@php
    [$icon, $text, $class] = match (true) {
        $stuck => ['!', 'colgado', 'text-orange-400'],
        $status === 'success' => ['✓', 'éxito', 'text-green-400'],
        $status === 'failed' => ['✕', 'fallo', 'text-red-400'],
        $status === 'running' => ['●', 'corriendo', 'text-yellow-400'],
        $status === 'pending' || $status === 'queued' => ['○', 'pendiente', 'text-zinc-400'],
        default => ['–', 'nunca corrido', 'text-zinc-500'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap '.$class]) }}>
    <span aria-hidden="true" class="font-semibold">{{ $icon }}</span>{{ $label ?? $text }}
</span>
