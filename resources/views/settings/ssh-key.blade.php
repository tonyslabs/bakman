<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Clave SSH del Backend Manager</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                <p class="mb-4 text-zinc-400">
                    Esta clave pública debe estar en <code>/root/.ssh/authorized_keys</code> de cada nodo
                    de la flota que quieras respaldar. Añadirla es un paso manual, único por nodo.
                </p>
                @if ($publicKey)
                    <textarea readonly rows="3" class="w-full font-mono text-sm bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md" onclick="this.select()">{{ $publicKey }}</textarea>
                @else
                    <p class="text-red-400">No se encontró la clave pública en el volumen.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
