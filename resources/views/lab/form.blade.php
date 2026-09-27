<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">
            {{ $project->exists ? 'Editar proyecto' : 'Nuevo proyecto' }} · {{ $moduleLabel }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ $project->exists ? route('lab.projects.update', $project) : route('lab.projects.store', $module) }}">
                    @csrf
                    @if ($project->exists) @method('PUT') @endif

                    <div class="mb-4">
                        <x-input-label for="name" value="Nombre" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $project->name) }}" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <x-input-label for="section" value="Sección (opcional, p. ej. la máquina)" />
                        <x-text-input id="section" name="section" list="lab-sections" class="mt-1 block w-full" value="{{ old('section', $project->section) }}" />
                        <datalist id="lab-sections">
                            @foreach ($sections as $section)
                                <option value="{{ $section }}"></option>
                            @endforeach
                        </datalist>
                        <x-input-error :messages="$errors->get('section')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <x-input-label for="scheme" value="Esquema" />
                            <select id="scheme" name="scheme" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500">
                                @foreach (['http', 'https'] as $scheme)
                                    <option value="{{ $scheme }}" @selected(old('scheme', $project->scheme) === $scheme)>{{ $scheme }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-2">
                            <x-input-label for="host" value="Host (IP tailnet o hostname)" />
                            <x-text-input id="host" name="host" class="mt-1 block w-full" value="{{ old('host', $project->host ?? '100.76.255.29') }}" required />
                            <x-input-error :messages="$errors->get('host')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <x-input-label :value="'Puerto'.($module === 'develop' ? '' : ' (opcional)')" for="port" />
                            <x-text-input id="port" type="number" name="port" class="mt-1 block w-full" value="{{ old('port', $project->port) }}" :required="$module === 'develop'" />
                            <x-input-error :messages="$errors->get('port')" class="mt-2" />
                        </div>
                        <div class="col-span-2">
                            <x-input-label for="path" value="Path (opcional)" />
                            <x-text-input id="path" name="path" class="mt-1 block w-full" placeholder="/ o /admin" value="{{ old('path', $project->path) }}" />
                            <x-input-error :messages="$errors->get('path')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mb-6">
                        <x-input-label for="description" value="Descripción" />
                        <textarea id="description" name="description" rows="3" class="mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500">{{ old('description', $project->description) }}</textarea>
                    </div>

                    @if ($module === 'homelab')
                        <div class="mb-4">
                            <x-input-label for="container" value="Contenedor (opcional)" />
                            <x-text-input id="container" name="container" class="mt-1 block w-full font-mono" value="{{ old('container', $project->container) }}" />
                            <p class="mt-1 text-sm text-zinc-500">Nombre del contenedor en esa máquina. Si lo pones, la tarjeta muestra el estado del contenedor y el descubrimiento automático no la duplica.</p>
                            <x-input-error :messages="$errors->get('container')" class="mt-2" />
                        </div>
                    @endif

                    <div class="mb-6 flex items-start">
                        <input type="hidden" name="monitor_only" value="0">
                        <input type="checkbox" id="monitor_only" name="monitor_only" value="1" class="mt-0.5 rounded border-zinc-700 bg-zinc-950 text-red-500 shadow-sm focus:ring-red-500" @checked(old('monitor_only', $project->monitor_only))>
                        <label for="monitor_only" class="ml-2 text-sm">
                            Solo estado
                            <span class="block text-zinc-500">Para servicios sin interfaz web: la tarjeta muestra si responde pero no es un enlace. Usa el path de salud (p. ej. <code>/ping</code>).</span>
                        </label>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Guardar</x-primary-button>
                        <a href="{{ route('lab.show', $module) }}" class="text-zinc-400 hover:underline">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
