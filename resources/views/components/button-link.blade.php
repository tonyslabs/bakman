{{-- Botón principal como enlace (p. ej. "Nuevo target"). --}}
<a {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-zinc-900']) }}>{{ $slot }}</a>
