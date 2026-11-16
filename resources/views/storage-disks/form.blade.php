<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">
            {{ $disk->exists ? 'Editar disco' : 'Agregar disco' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ $disk->exists ? route('storage-disks.update', $disk) : route('storage-disks.store') }}">
                    @csrf
                    @if ($disk->exists) @method('PUT') @endif

                    <div class="mb-4">
                        <x-input-label for="name" value="Nombre" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $disk->name ?: 'Disco externo') }}" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mb-6">
                        <x-input-label for="path" value="Ruta dentro del contenedor" />
                        <x-text-input id="path" name="path" class="mt-1 block w-full font-mono" value="{{ old('path', $disk->path) }}" placeholder="/mnt/external" required />
                        <p class="mt-1 text-sm text-zinc-500">
                            Es la ruta que ve el contenedor, no la del servidor. Para exponer un disco hay que montarlo como volumen en
                            <code>docker-compose.yml</code>, de solo lectura, por ejemplo <code>/mnt/mi-disco:/mnt/external:ro</code>, y poner aquí <code>/mnt/external</code>.
                            El explorador nunca escribe ni borra.
                        </p>
                        <x-input-error :messages="$errors->get('path')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Guardar</x-primary-button>
                        <a href="{{ $disk->exists ? route('files.show', $disk) : route('files.index') }}" class="text-zinc-400 hover:underline">Cancelar</a>
                    </div>
                </form>

                @if ($disk->exists)
                    <form method="POST" action="{{ route('storage-disks.destroy', $disk) }}" class="mt-8 border-t border-zinc-800 pt-6"
                          onsubmit="return confirm('¿Quitar este disco de bakman? No se borra ningún archivo.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-sm text-red-400 hover:text-red-300">Quitar este disco de bakman</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
