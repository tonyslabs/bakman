<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Ajustes</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <x-flash class="mb-0" />

            <p class="text-sm text-zinc-400">
                Lo que pongas aquí manda sobre el <code>.env</code> y no hace falta reiniciar el contenedor.
                Si dejas un campo vacío, bakman usa el valor del <code>.env</code> o el de fábrica (se ve en gris dentro del campo).
            </p>

            <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
                @csrf @method('PUT')

                @foreach ($groups as $group => $items)
                    <section class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wider text-zinc-400">{{ $group }}</h3>

                        @foreach ($items as $item)
                            @php
                                $field = str_replace('.', '_', $item['key']);
                                $name = "settings[{$field}]";
                                $old = old("settings.{$field}", $item['value']);
                                $input = 'mt-1 block w-full bg-zinc-950 text-zinc-100 border-zinc-700 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500';
                            @endphp
                            <div class="mb-5 last:mb-0">
                                <x-input-label :for="$field" :value="$item['label']" />

                                @if ($item['type'] === 'pairs')
                                    <textarea id="{{ $field }}" name="{{ $name }}" rows="4" placeholder="{{ $item['default'] ?: ($item['placeholder'] ?? '') }}" class="{{ $input }} font-mono text-sm">{{ $old }}</textarea>
                                @elseif ($item['type'] === 'timezone')
                                    <select id="{{ $field }}" name="{{ $name }}" class="{{ $input }}">
                                        <option value="">Por defecto{{ $item['default'] ? ' ('.$item['default'].')' : '' }}</option>
                                        @foreach ($timezones as $tz)
                                            <option value="{{ $tz }}" @selected($old === $tz)>{{ $tz }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($item['type'] === 'secret')
                                    <x-text-input :id="$field" :name="$name" type="password" autocomplete="new-password" class="mt-1 block w-full font-mono"
                                        placeholder="{{ $item['has_secret'] ? '•••••••• (guardado; escribe para cambiarlo)' : '' }}" />
                                    @if ($item['has_secret'])
                                        <label class="mt-2 flex items-center text-sm text-zinc-400">
                                            <input type="checkbox" name="clear[{{ $field }}]" value="1" class="rounded border-zinc-700 bg-zinc-950 text-red-500 shadow-sm focus:ring-red-500">
                                            <span class="ml-2">Quitar el token guardado</span>
                                        </label>
                                    @endif
                                @else
                                    <x-text-input :id="$field" :name="$name" :type="$item['type'] === 'int' ? 'number' : 'text'" class="mt-1 block w-full {{ in_array($item['type'], ['text', 'ports']) ? 'font-mono' : '' }}"
                                        value="{{ $old }}" placeholder="{{ $item['default'] ?: ($item['placeholder'] ?? '') }}" />
                                @endif

                                @isset($item['help'])
                                    <p class="mt-1 text-sm text-zinc-500">{{ $item['help'] }}</p>
                                @endisset
                                <x-input-error :messages="$errors->get('settings.'.$field)" class="mt-2" />
                            </div>
                        @endforeach
                    </section>
                @endforeach

                <x-primary-button>Guardar ajustes</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
