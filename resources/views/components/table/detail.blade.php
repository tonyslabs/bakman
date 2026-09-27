{{-- Fila secundaria bajo otra fila: el error de un fallo o un resumen. --}}
@props(['colspan', 'variant' => 'muted'])

<tr class="{{ $variant === 'error' ? 'bg-red-500/5' : 'bg-zinc-950/40' }}">
    <td colspan="{{ $colspan }}" class="px-6 pb-3 pt-0 font-mono text-xs {{ $variant === 'error' ? 'text-red-300/90' : 'text-zinc-400' }}">
        <div class="border-l-2 {{ $variant === 'error' ? 'border-red-500/60' : 'border-zinc-700' }} pl-3 whitespace-pre-wrap break-words">{{ $slot }}</div>
    </td>
</tr>
