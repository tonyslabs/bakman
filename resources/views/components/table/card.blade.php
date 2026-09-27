{{--
    Contenedor estándar de tabla. Slots opcionales:
      title   → texto de la barra superior (con `count` al lado)
      actions → botones a la derecha de la barra
    `flush` quita el fondo propio, para tablas anidadas dentro de otra tarjeta.
--}}
@props(['count' => null, 'flush' => false])

<div {{ $attributes->merge(['class' => ($flush ? 'rounded-md ring-1 ring-zinc-800' : 'rounded-lg bg-zinc-900 ring-1 ring-zinc-800 shadow-sm').' overflow-hidden']) }}>
    @if (isset($title) || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-800 px-6 py-3">
            <div class="flex items-baseline gap-2">
                @isset($title)
                    <h3 class="text-sm font-medium text-zinc-200">{{ $title }}</h3>
                @endisset
                @if ($count !== null)
                    <span class="text-xs tabular-nums text-zinc-500">{{ $count }}</span>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            {{ $slot }}
        </table>
    </div>
</div>
