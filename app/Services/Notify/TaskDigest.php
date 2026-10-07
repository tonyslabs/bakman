<?php

namespace App\Services\Notify;

use App\Services\Tasks\TaskRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Avisos de tareas: resumen de la mañana y recordatorio de la tarde. */
class TaskDigest
{
    public function __construct(private TaskRepository $tasks, private Notifier $notifier)
    {
    }

    /**
     * @param  'manana'|'tarde'  $tipo
     * @return string|null el texto enviado (null si no había nada que avisar o ya se avisó hoy)
     */
    public function send(string $tipo, bool $force = false): ?string
    {
        $hoy = TaskRepository::today();
        $abiertas = $this->tasks->all()->where('cerrada', false)->reject(fn ($t) => $t['pospuesta']);
        $vencidas = $abiertas->filter(fn ($t) => $t['vence'] && $t['vence'] < $hoy);
        $paraHoy = $abiertas->where('vence', $hoy);

        if ($tipo === 'tarde') {
            $vencidas = collect();
        }
        if ($vencidas->isEmpty() && $paraHoy->isEmpty()) {
            return null;
        }

        // Una vez por día y tipo, aunque el scheduler o alguien lo dispare de nuevo.
        if (! $force && ! Cache::add("avisos:tareas:{$tipo}:{$hoy}", true, now()->addDays(2))) {
            return null;
        }

        $fecha = Carbon::parse($hoy)->locale('es')->isoFormat('ddd D MMM');
        $lines = [];
        if ($vencidas->isNotEmpty()) {
            $lines[] = '⚠️ Vencidas ('.$vencidas->count().')';
            $lines = array_merge($lines, $this->items($vencidas, withDate: true));
        }
        if ($paraHoy->isNotEmpty()) {
            if ($lines) {
                $lines[] = '';
            }
            $lines[] = ($tipo === 'tarde' ? '⏳ Siguen abiertas hoy (' : '📌 Hoy (').$paraHoy->count().')';
            $lines = array_merge($lines, $this->items($paraHoy));
        }

        $message = implode("\n", $lines);
        $title = $tipo === 'manana'
            ? "Tareas · {$fecha}"
            : 'Te quedan '.$paraHoy->count().' para hoy';

        $this->notifier->send('tareas', $title, $message, [
            'priority' => $vencidas->isNotEmpty() ? 4 : 3,
            'tags' => [$tipo === 'manana' ? 'sunrise' : 'hourglass_flowing_sand'],
            'click' => Notifier::link('tareas/agenda'),
            'actions' => [
                Notifier::view('Agenda', Notifier::link('tareas/agenda')),
                Notifier::view('Tablero', Notifier::link('tareas/tablero')),
            ],
        ]);

        return $title."\n".$message;
    }

    /** @return list<string> */
    private function items(Collection $tasks, bool $withDate = false): array
    {
        $max = config('notify.tareas.max_items', 8);
        $prio = ['alta' => '🔴 ', 'media' => '', 'baja' => ''];

        $lines = $tasks
            ->sortBy([['vence', 'asc'], fn ($a, $b) => array_search($a['prioridad'], ['alta', 'media', 'baja']) <=> array_search($b['prioridad'], ['alta', 'media', 'baja'])])
            ->take($max)
            ->map(fn ($t) => '• '.($prio[$t['prioridad']] ?? '').$t['titulo']
                .($t['repite'] ? ' ↻' : '')
                .($withDate ? ' ('.Carbon::parse($t['vence'])->locale('es')->isoFormat('D MMM').')' : '')
                .($t['area_label'] ? ' · '.$t['area_label'] : ''))
            ->values()
            ->all();

        if ($tasks->count() > $max) {
            $lines[] = '… y '.($tasks->count() - $max).' más';
        }

        return $lines;
    }
}
