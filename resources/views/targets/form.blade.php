<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">
            {{ $target->exists ? 'Editar target' : 'Nuevo target' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ $target->exists ? route('targets.update', $target) : route('targets.store') }}">
                    @csrf
                    @if ($target->exists) @method('PUT') @endif

                    <div class="mb-4">
                        <x-input-label for="name" value="Nombre" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $target->name) }}" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="hostname" value="Host (IP tailnet)" />
                        <x-text-input id="hostname" name="hostname" class="mt-1 block w-full" value="{{ old('hostname', $target->hostname) }}" required />
                        <x-input-error :messages="$errors->get('hostname')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="ssh_user" value="Usuario SSH" />
                        <x-text-input id="ssh_user" name="ssh_user" class="mt-1 block w-full" value="{{ old('ssh_user', $target->ssh_user ?? 'root') }}" required />
                        <x-input-error :messages="$errors->get('ssh_user')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="ssh_port" value="Puerto SSH" />
                        <x-text-input id="ssh_port" type="number" name="ssh_port" class="mt-1 block w-full" value="{{ old('ssh_port', $target->ssh_port ?? 22) }}" required />
                        <x-input-error :messages="$errors->get('ssh_port')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="notes" value="Notas" />
                        <textarea id="notes" name="notes" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500">{{ old('notes', $target->notes) }}</textarea>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Guardar</x-primary-button>
                        <a href="{{ route('targets.index') }}" class="text-zinc-400 hover:underline">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
