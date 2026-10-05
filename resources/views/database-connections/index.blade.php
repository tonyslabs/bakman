<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Conexiones de base de datos</h2>
    </x-slot>

    {{--
        "var env": pide el bloque al servidor al hacer clic (la contraseña nunca va en el HTML)
        y lo copia. Bakman se abre por http://100.x, donde navigator.clipboard no existe, así
        que se usa execCommand; si el navegador tampoco lo deja, se muestra el texto para copiarlo.
    --}}
    <script>
        async function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                try { await navigator.clipboard.writeText(text); return true; } catch (e) {}
            }
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            let ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            area.remove();
            return ok;
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('connectionRow', (envUrl) => ({
                testing: false,
                result: null,
                envOpen: false,
                envLoading: false,
                envResult: null,
                envText: null,

                async copyEnv(framework, label) {
                    this.envOpen = false;
                    this.envLoading = true;
                    this.envResult = null;
                    this.envText = null;
                    try {
                        const response = await fetch(envUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({ framework }),
                        });
                        if (! response.ok) throw new Error();
                        const { text } = await response.json();
                        if (await copyText(text)) {
                            this.flashEnv(`.env de ${label} copiado`);
                        } else {
                            this.envText = text;
                        }
                    } catch (e) {
                        this.envResult = { success: false, message: 'No se pudo generar el .env.' };
                    } finally {
                        this.envLoading = false;
                    }
                },

                async copyEnvFallback() {
                    if (await copyText(this.envText)) {
                        this.envText = null;
                        this.flashEnv('.env copiado');
                    } else {
                        this.$refs.envArea.select();
                    }
                },

                flashEnv(message) {
                    this.envResult = { success: true, message };
                    setTimeout(() => { this.envResult = null; }, 4000);
                },
            }));
        });
    </script>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <x-table.card :count="$connections->count()">
                <x-slot name="title">Servidores MySQL / MariaDB</x-slot>
                <x-slot name="actions">
                    <x-button-link href="{{ route('database-connections.create') }}">+ Nueva conexión</x-button-link>
                </x-slot>

                <thead>
                    <tr>
                        <x-table.th>Nombre</x-table.th>
                        <x-table.th>Servidor</x-table.th>
                        <x-table.th>Usuario</x-table.th>
                        <x-table.th><span class="sr-only">Acciones</span></x-table.th>
                    </tr>
                </thead>
                @forelse ($connections as $connection)
                    <tbody x-data="connectionRow(@js(route('database-connections.env', $connection)))">
                        <x-table.row>
                            <x-table.td strong>{{ $connection->name }}</x-table.td>
                            <x-table.td mono>{{ $connection->host }}<span class="text-zinc-500">:{{ $connection->port }}</span></x-table.td>
                            <x-table.td mono>{{ $connection->username }}</x-table.td>
                            <x-table.td>
                                <x-table.actions>
                                    <span x-show="result" x-cloak class="mr-2 inline-flex items-center gap-1 text-xs" :class="result?.success ? 'text-green-400' : 'text-red-400'">
                                        <span aria-hidden="true" x-text="result?.success ? '✓' : '✕'"></span><span x-text="result?.message"></span>
                                    </span>
                                    <span x-show="envResult" x-cloak class="mr-2 inline-flex items-center gap-1 text-xs" :class="envResult?.success ? 'text-green-400' : 'text-red-400'">
                                        <span aria-hidden="true" x-text="envResult?.success ? '✓' : '✕'"></span><span x-text="envResult?.message"></span>
                                    </span>
                                    <x-table.action
                                        x-bind:disabled="testing"
                                        x-on:click="
                                            testing = true; result = null;
                                            fetch('{{ route('database-connections.test') }}', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'Accept': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                },
                                                body: JSON.stringify({ id: {{ $connection->id }} }),
                                            })
                                                .then(r => r.json())
                                                .then(data => { result = data; })
                                                .catch(() => { result = { success: false, message: 'Error de red.' }; })
                                                .finally(() => { testing = false; })
                                        "
                                    >
                                        <span x-show="!testing">Probar</span>
                                        <span x-show="testing" x-cloak>Probando…</span>
                                    </x-table.action>
                                    <x-table.action x-show="!envOpen" x-bind:disabled="envLoading" x-on:click="envOpen = true">
                                        <span x-show="!envLoading">var env</span>
                                        <span x-show="envLoading" x-cloak>Copiando…</span>
                                    </x-table.action>
                                    <span x-show="envOpen" x-cloak x-on:click.outside="envOpen = false" x-on:keydown.escape.window="envOpen = false" class="inline-flex items-center gap-0.5 rounded bg-zinc-800/60 px-1">
                                        @foreach (\App\Models\DatabaseConnection::ENV_FRAMEWORKS as $framework => $label)
                                            <x-table.action variant="primary" x-on:click="copyEnv({{ Js::from($framework) }}, {{ Js::from($label) }})">{{ $label }}</x-table.action>
                                        @endforeach
                                        <x-table.action x-on:click="envOpen = false" aria-label="Cancelar">✕</x-table.action>
                                    </span>
                                    <x-table.action :href="route('database-connections.edit', $connection)">Editar</x-table.action>
                                    <x-table.action :action="route('database-connections.destroy', $connection)" method="DELETE" confirm="¿Borrar la conexión {{ $connection->name }}?" variant="danger">Borrar</x-table.action>
                                </x-table.actions>
                            </x-table.td>
                        </x-table.row>
                        <tr x-show="envText" x-cloak class="bg-zinc-950/40">
                            <td colspan="4" class="px-6 pb-3 pt-1">
                                <p class="mb-2 text-xs text-zinc-400">El navegador no dejó copiar solo. Cópialo desde aquí:</p>
                                <textarea x-ref="envArea" readonly rows="6" :value="envText" x-on:focus="$el.select()" class="block w-full rounded-md border-zinc-700 bg-zinc-950 font-mono text-xs text-zinc-200 focus:border-red-500 focus:ring-red-500"></textarea>
                                <div class="mt-2 flex justify-end gap-1">
                                    <x-table.action variant="primary" x-on:click="copyEnvFallback()">Copiar</x-table.action>
                                    <x-table.action x-on:click="envText = null">Cerrar</x-table.action>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <x-table.empty colspan="4">Todavía no hay conexiones.</x-table.empty>
                    </tbody>
                @endforelse
            </x-table.card>
        </div>
    </div>
</x-app-layout>
