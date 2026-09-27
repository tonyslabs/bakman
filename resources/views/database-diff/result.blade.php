<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">
            Comparación: {{ $connectionA->name }}/{{ $dbA }} vs {{ $connectionB->name }}/{{ $dbB }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('database-diff.create') }}" class="text-red-500 hover:underline">&laquo; Nueva comparación</a>
                <a href="{{ route('database-diff-syncs.index') }}" class="text-zinc-400 hover:underline text-sm">Historial de sincronizaciones</a>
            </div>

            @if (isset($result['tables']))
                <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold mb-3">Tablas (estructura)</h3>

                    @if (count($result['tables']['only_in_a']) || count($result['tables']['only_in_b']))
                        <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                            <div>
                                <p class="font-medium text-zinc-300 mb-1">Solo en A ({{ $connectionA->name }}/{{ $dbA }})</p>
                                @forelse ($result['tables']['only_in_a'] as $table)
                                    <div class="text-zinc-400">{{ $table }}</div>
                                @empty
                                    <div class="text-zinc-500">—</div>
                                @endforelse
                            </div>
                            <div>
                                <p class="font-medium text-zinc-300 mb-1">Solo en B ({{ $connectionB->name }}/{{ $dbB }})</p>
                                @forelse ($result['tables']['only_in_b'] as $table)
                                    <div class="text-zinc-400">{{ $table }}</div>
                                @empty
                                    <div class="text-zinc-500">—</div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    @if (empty($result['tables']['items']))
                        <p class="text-zinc-500 text-sm">No hay tablas en común.</p>
                    @else
                        <x-table.card flush>
                            <thead>
                                <tr>
                                    <x-table.th>Tabla</x-table.th>
                                    <x-table.th>Diferencias de columnas</x-table.th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($result['tables']['items'] as $table)
                                    <x-table.row>
                                        <x-table.td mono strong>{{ $table['name'] }}</x-table.td>
                                        <x-table.td class="text-xs">
                                            @php $cd = $table['columns_diff']; @endphp
                                            @if (empty($cd['only_in_a']) && empty($cd['only_in_b']) && empty($cd['changed']))
                                                <span class="text-green-400"><span aria-hidden="true">✓</span> idéntica</span>
                                            @else
                                                <div class="space-y-0.5 font-mono">
                                                    @foreach ($cd['only_in_a'] as $col)
                                                        <div class="text-red-400">− {{ $col }} <span class="text-zinc-500">(solo A)</span></div>
                                                    @endforeach
                                                    @foreach ($cd['only_in_b'] as $col)
                                                        <div class="text-red-400">+ {{ $col }} <span class="text-zinc-500">(solo B)</span></div>
                                                    @endforeach
                                                    @foreach ($cd['changed'] as $change)
                                                        <div class="text-yellow-400">~ {{ $change['column'] }}: <span class="text-zinc-400">{{ $change['a'] }}</span> → <span class="text-zinc-200">{{ $change['b'] }}</span></div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </x-table.td>
                                    </x-table.row>
                                @endforeach
                            </tbody>
                        </x-table.card>
                    @endif

                    <x-database-diff.sync-form section="tables" :connection-a="$connectionA" :db-a="$dbA" :connection-b="$connectionB" :db-b="$dbB" />
                </div>
            @endif

            @if (isset($result['data']))
                <div class="bg-zinc-900 shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold mb-3">Datos</h3>

                    @if (count($result['data']['only_in_a']) || count($result['data']['only_in_b']))
                        <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                            <div>
                                <p class="font-medium text-zinc-300 mb-1">Solo en A ({{ $connectionA->name }}/{{ $dbA }})</p>
                                @forelse ($result['data']['only_in_a'] as $table)
                                    <div class="text-zinc-400">{{ $table }}</div>
                                @empty
                                    <div class="text-zinc-500">—</div>
                                @endforelse
                            </div>
                            <div>
                                <p class="font-medium text-zinc-300 mb-1">Solo en B ({{ $connectionB->name }}/{{ $dbB }})</p>
                                @forelse ($result['data']['only_in_b'] as $table)
                                    <div class="text-zinc-400">{{ $table }}</div>
                                @empty
                                    <div class="text-zinc-500">—</div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    @if (empty($result['data']['items']))
                        <p class="text-zinc-500 text-sm">No hay tablas en común.</p>
                    @else
                        <x-table.card flush>
                            <thead>
                                <tr>
                                    <x-table.th>Tabla</x-table.th>
                                    <x-table.th>Estado</x-table.th>
                                    <x-table.th align="right">Filas A</x-table.th>
                                    <x-table.th align="right">Filas B</x-table.th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($result['data']['items'] as $table)
                                    <x-table.row>
                                        <x-table.td mono strong>{{ $table['name'] }}</x-table.td>
                                        <x-table.td>
                                            @if ($table['identical'])
                                                <span class="text-green-400"><span aria-hidden="true">✓</span> idéntica</span>
                                            @else
                                                <span class="text-yellow-400"><span aria-hidden="true">≠</span> difiere</span>
                                            @endif
                                        </x-table.td>
                                        <x-table.td numeric>{{ number_format($table['count_a']) }}</x-table.td>
                                        <x-table.td numeric><span @class(['text-yellow-400' => $table['count_a'] !== $table['count_b']])>{{ number_format($table['count_b']) }}</span></x-table.td>
                                    </x-table.row>
                                @endforeach
                            </tbody>
                        </x-table.card>
                    @endif

                    <x-database-diff.sync-form section="data" :connection-a="$connectionA" :db-a="$dbA" :connection-b="$connectionB" :db-b="$dbB" />
                </div>
            @endif

            @if (isset($result['views']))
                <x-database-diff.definition-section title="Vistas" section-key="views" :data="$result['views']" :connection-a="$connectionA" :db-a="$dbA" :connection-b="$connectionB" :db-b="$dbB" />
            @endif

            @if (isset($result['procedures']))
                <x-database-diff.definition-section title="Procedimientos" section-key="procedures" :data="$result['procedures']" :connection-a="$connectionA" :db-a="$dbA" :connection-b="$connectionB" :db-b="$dbB" />
            @endif

            @if (isset($result['functions']))
                <x-database-diff.definition-section title="Funciones" section-key="functions" :data="$result['functions']" :connection-a="$connectionA" :db-a="$dbA" :connection-b="$connectionB" :db-b="$dbB" />
            @endif
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmAndSync(form, source) {
            const sourceLabel = source === 'a' ? form.dataset.labelA : form.dataset.labelB;
            const destLabel = source === 'a' ? form.dataset.labelB : form.dataset.labelA;

            Swal.fire({
                title: '¿Sincronizar?',
                html: `Vas a igualar <b>${destLabel}</b> según <b>${sourceLabel}</b>.<br>Esto puede borrar o sobreescribir datos en el destino.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#3f3f46',
                background: '#18181b',
                color: '#e4e4e7',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    </script>
</x-app-layout>
