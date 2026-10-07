<?php

namespace App\Services\Notify;

use App\Models\BackupJob;
use App\Models\BackupJobRun;
use App\Services\JobSchedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Avisos de jobs: falla (la primera y luego cada N seguidas, no una por corrida), vuelta a la
 * normalidad, atrasado y colgado. El estado "está fallando" vive en la cache (Redis).
 */
class JobAlerts
{
    public function __construct(private Notifier $notifier, private JobSchedule $schedule)
    {
    }

    public function failed(BackupJob $job, BackupJobRun $run): void
    {
        $key = "avisos:jobs:{$job->id}:fallas";
        $count = (int) Cache::get($key, 0) + 1;
        Cache::forever($key, $count);

        $cada = max(1, config('notify.jobs.recordar_cada', 5));
        if ($count !== 1 && $count % $cada !== 0) {
            return;
        }

        $this->notifier->send('jobs',
            $count === 1 ? "✕ Falló {$job->name}" : "✕ {$job->name} sigue fallando ({$count} seguidas)",
            Str::limit($run->error_message ?: 'Sin mensaje de error.', 300),
            [
                'priority' => 4,
                'tags' => ['rotating_light'],
                'click' => Notifier::link("backup-jobs/{$job->id}/runs"),
                'actions' => [Notifier::view('Ver ejecuciones', Notifier::link("backup-jobs/{$job->id}/runs"))],
            ],
        );
    }

    public function succeeded(BackupJob $job, BackupJobRun $run): void
    {
        $count = (int) Cache::pull("avisos:jobs:{$job->id}:fallas", 0);
        if ($count === 0) {
            return;
        }

        $this->notifier->send('jobs', "✓ {$job->name} volvió a funcionar",
            'Después de '.$count.' '.($count === 1 ? 'falla' : 'fallas seguidas').'. Última corrida OK en '.($run->duration_seconds ?? 0).' s.',
            ['priority' => 3, 'tags' => ['white_check_mark'], 'click' => Notifier::link("backup-jobs/{$job->id}/runs")],
        );
    }

    /** Revisión periódica (scheduler): atrasados y colgados, un aviso por caso. */
    public function watch(): int
    {
        $sent = 0;

        BackupJob::query()->where('enabled', true)->with(['runs' => fn ($q) => $q->limit(1)])->each(function (BackupJob $job) use (&$sent) {
            $last = $job->runs->first();

            if ($last && $this->schedule->isStuck($job, $last)
                && Cache::add("avisos:jobs:{$job->id}:colgado:{$last->id}", true, now()->addDays(7))) {
                $sent++;
                $this->notifier->send('jobs', "⏸ {$job->name} parece colgado",
                    'Corriendo desde '.$last->started_at->timezone(config('backups.timezone'))->format('d/m H:i').' (más que su timeout).',
                    ['priority' => 4, 'tags' => ['warning'], 'click' => Notifier::link('monitor')],
                );
            }

            if ($this->schedule->isOverdue($job, $last)) {
                // Un aviso por "ranura" perdida: la marca es la última ejecución conocida.
                $marca = $last?->id ?? 'nunca';
                if (Cache::add("avisos:jobs:{$job->id}:atrasado:{$marca}", true, now()->addDays(7))) {
                    $sent++;
                    $this->notifier->send('jobs', "⌛ {$job->name} está atrasado",
                        'No corrió cuando le tocaba'.($last ? ' (última: '.$last->started_at?->timezone(config('backups.timezone'))->format('d/m H:i').').' : '.'),
                        ['priority' => 4, 'tags' => ['hourglass'], 'click' => Notifier::link('monitor')],
                    );
                }
            }
        });

        return $sent;
    }
}
