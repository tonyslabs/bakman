<?php

namespace App\Services;

use App\Models\BackupJob;
use App\Models\BackupJobRun;
use Cron\CronExpression;
use Illuminate\Support\Carbon;

/**
 * Lectura de la programación de un job: próximas ejecuciones, la última que
 * debió ocurrir y si quedó atrasado. Entiende varias expresiones separadas por "|".
 */
class JobSchedule
{
    /** Margen antes de considerar que una ejecución debida no ocurrió. */
    public const GRACE_MINUTES = 15;

    /** @return CronExpression[] */
    public function expressions(BackupJob $job): array
    {
        $expressions = [];

        foreach (array_filter(array_map('trim', explode('|', (string) $job->schedule_cron))) as $cron) {
            if (CronExpression::isValidExpression($cron)) {
                $expressions[] = new CronExpression($cron);
            }
        }

        return $expressions;
    }

    public function nextRun(BackupJob $job, ?Carbon $from = null): ?Carbon
    {
        $from ??= now();
        $dates = array_map(
            fn (CronExpression $e) => Carbon::instance($e->getNextRunDate($from, 0, false, $this->timezone())),
            $this->expressions($job),
        );

        return $dates ? min($dates) : null;
    }

    /** @return Carbon[] las próximas $count ejecuciones del job, en orden. */
    public function upcoming(BackupJob $job, int $count, ?Carbon $from = null): array
    {
        $from ??= now();
        $dates = [];

        foreach ($this->expressions($job) as $expression) {
            foreach ($expression->getMultipleRunDates($count, $from, false, false, $this->timezone()) as $date) {
                $dates[] = Carbon::instance($date);
            }
        }

        usort($dates, fn ($a, $b) => $a <=> $b);

        return array_slice($dates, 0, $count);
    }

    public function previousDue(BackupJob $job, ?Carbon $from = null): ?Carbon
    {
        $from ??= now();
        $dates = array_map(
            fn (CronExpression $e) => Carbon::instance($e->getPreviousRunDate($from, 0, true, $this->timezone())),
            $this->expressions($job),
        );

        return $dates ? max($dates) : null;
    }

    /**
     * Atrasado = estaba activo, le tocaba correr hace más del margen y no hay
     * ninguna ejecución (buena o mala) desde entonces.
     */
    public function isOverdue(BackupJob $job, ?BackupJobRun $lastRun, ?Carbon $now = null): bool
    {
        if (! $job->enabled) {
            return false;
        }

        $now ??= now();
        $due = $this->previousDue($job, $now->copy()->subMinutes(self::GRACE_MINUTES));

        if (! $due) {
            return false;
        }

        $reference = $lastRun?->started_at ?? $job->created_at;

        return $reference !== null && $reference->lt($due);
    }

    /** Una ejecución sigue "corriendo" mucho después de su tiempo máximo: el worker murió. */
    public function isStuck(BackupJob $job, BackupJobRun $run, ?Carbon $now = null): bool
    {
        if ($run->status !== 'running' || ! $run->started_at) {
            return false;
        }

        $limit = $job->type === 'script'
            ? ($job->timeout_seconds ?: config('backups.script_default_timeout'))
            : 3700;

        return $run->started_at->lt(($now ?? now())->copy()->subSeconds($limit + 300));
    }

    private function timezone(): string
    {
        return config('backups.timezone');
    }
}
