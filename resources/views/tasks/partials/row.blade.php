{{-- Fila de Lista/Agenda/Inbox/Recurrentes/Hechas. Las acciones van por fetch (ver tasksPage en index). --}}
@php $done = $task['cerrada']; @endphp
<li data-task data-id="{{ $task['id'] }}" data-estado="{{ $task['estado'] }}" data-edit="{{ route('tasks.edit', ['task' => $task['id'], 'volver' => $vista]) }}"
    x-data="{ subs: false }"
    @click="select($el)"
    :class="current === $el ? 'ring-1 ring-red-500/60 bg-zinc-900' : ''"
    class="group rounded-md px-3 py-2.5 transition hover:bg-zinc-900/70 {{ $done ? 'opacity-50' : '' }}">
    <div class="flex items-start gap-3">
        <input type="checkbox" @checked($done) @click.stop="toggle($el.closest('[data-task]'))"
               class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer rounded border-zinc-600 bg-zinc-950 text-red-600 focus:ring-red-500 focus:ring-offset-zinc-900"
               title="{{ $task['repite'] ? 'Hecha (se reprograma: '.$task['repite_label'].')' : 'Marcar hecha' }} (x)">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <a href="{{ route('tasks.edit', ['task' => $task['id'], 'volver' => $vista]) }}" data-title
                   class="truncate text-sm text-zinc-100 hover:text-red-400 {{ $done ? 'line-through' : '' }}">{{ $task['titulo'] }}</a>
                @if ($task['subtareas'])
                    <button type="button" @click.stop="subs = ! subs" class="shrink-0 text-xs text-zinc-500 hover:text-zinc-200" :title="subs ? 'Ocultar subtareas' : 'Ver subtareas (s)'">
                        <span x-text="subs ? '▾' : '▸'">▸</span>
                    </button>
                @endif
            </div>
            <div class="mt-1">@include('tasks.partials.badges', ['task' => $task, 'hideArea' => $hideArea ?? false])</div>
            @if ($task['subtareas'])
                <ul x-show="subs" x-cloak class="mt-2 space-y-1 border-l border-zinc-800 pl-3" data-subs>
                    @foreach ($task['subtareas'] as $sub)
                        <li class="flex items-start gap-2 text-sm">
                            <input type="checkbox" @checked($sub['hecha']) @click.stop="sub($el.closest('[data-task]'), {{ $sub['indice'] }}, $event.target)"
                                   class="mt-0.5 h-3.5 w-3.5 shrink-0 cursor-pointer rounded border-zinc-600 bg-zinc-950 text-red-600 focus:ring-red-500 focus:ring-offset-zinc-900">
                            <span @class(['text-zinc-300', 'line-through text-zinc-500' => $sub['hecha']])>{{ $sub['texto'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @unless ($done)
            @include('tasks.partials.posponer')
        @endunless
        <select data-estado-select @click.stop @change="setEstado($el.closest('[data-task]'), $event.target.value)"
                class="shrink-0 rounded-md border-zinc-800 bg-zinc-950 py-1 pl-2 pr-7 text-xs text-zinc-300 focus:border-red-500 focus:ring-red-500"
                title="Estado (1–6)">
            @foreach (config('tasks.estados') as $key => $label)
                <option value="{{ $key }}" @selected($task['estado'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</li>
