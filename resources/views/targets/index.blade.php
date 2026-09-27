<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-zinc-100 leading-tight">Targets</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <x-flash />

            <x-table.card :count="$targets->count()">
                <x-slot name="title">Equipos accesibles por SSH</x-slot>
                <x-slot name="actions">
                    <x-button-link href="{{ route('targets.create') }}">+ Nuevo target</x-button-link>
                </x-slot>

                <thead>
                    <tr>
                        <x-table.th>Nombre</x-table.th>
                        <x-table.th>Conexión</x-table.th>
                        <x-table.th><span class="sr-only">Acciones</span></x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($targets as $target)
                        <x-table.row>
                            <x-table.td strong>{{ $target->name }}</x-table.td>
                            <x-table.td mono>{{ $target->ssh_user }}@{{ $target->hostname }}<span class="text-zinc-500">:{{ $target->ssh_port }}</span></x-table.td>
                            <x-table.td>
                                <x-table.actions>
                                    <x-table.action :href="route('targets.edit', $target)">Editar</x-table.action>
                                    <x-table.action :action="route('targets.destroy', $target)" method="DELETE" confirm="¿Borrar el target {{ $target->name }}?" variant="danger">Borrar</x-table.action>
                                </x-table.actions>
                            </x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty colspan="3">Todavía no hay targets.</x-table.empty>
                    @endforelse
                </tbody>
            </x-table.card>
        </div>
    </div>
</x-app-layout>
