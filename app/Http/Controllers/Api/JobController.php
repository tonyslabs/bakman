<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunBackupJob;
use App\Models\BackupJob;
use App\Services\JobSchedule;

class JobController extends Controller
{
    public function __construct(private JobSchedule $schedule)
    {
    }

    /** Equivale a Jobs → Monitor. */
    public function index()
    {
        $jobs = BackupJob::with(['target', 'databaseConnection', 'runs' => fn ($q) => $q->limit(1)])
            ->orderByDesc('enabled')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $jobs->map(fn ($job) => $this->job($job))]);
    }

    public function show(BackupJob $backupJob)
    {
        $backupJob->load(['target', 'databaseConnection']);

        return response()->json(['data' => $this->job($backupJob, detail: true)]);
    }

    public function runs(BackupJob $backupJob)
    {
        $page = $backupJob->runs()->paginate(20);
        $page->getCollection()->each->setRelation('backupJob', $backupJob);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($run) => Payload::run($run)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function run(BackupJob $backupJob)
    {
        // Un toque doble en el teléfono no debe encolar dos corridas a la vez.
        if ($backupJob->runs()->where('status', 'running')->exists()) {
            return response()->json(['message' => 'Este job ya se está ejecutando.'], 409);
        }

        RunBackupJob::dispatch($backupJob);

        return response()->json(['message' => 'Job encolado para ejecutarse.'], 202);
    }

    private function job(BackupJob $job, bool $detail = false): array
    {
        $last = $job->relationLoaded('runs') ? $job->runs->first() : $job->latestRun();
        $last?->setRelation('backupJob', $job);
        $lastSuccess = $job->runs()->where('status', 'success')->first();
        $lastSuccess?->setRelation('backupJob', $job);

        $data = [
            'id' => $job->id,
            'name' => $job->name,
            'type' => $job->type,
            'enabled' => $job->enabled,
            'schedule' => array_values(array_filter(array_map('trim', explode('|', (string) $job->schedule_cron)))),
            'target' => $job->target?->name,
            'connection' => $job->databaseConnection?->name,
            'last_run' => Payload::run($last),
            'last_success' => Payload::run($lastSuccess),
            'next_run' => $job->enabled ? $this->schedule->nextRun($job)?->toIso8601String() : null,
            'overdue' => $this->schedule->isOverdue($job, $last),
            'stuck' => $last ? $this->schedule->isStuck($job, $last) : false,
        ];

        if ($detail) {
            $data += [
                'db_name' => $job->db_name,
                'command' => $job->command,
                'timeout_seconds' => $job->timeout_seconds,
                'retention_count' => $job->retention_count,
                'upcoming' => $job->enabled
                    ? array_map(fn ($d) => $d->toIso8601String(), $this->schedule->upcoming($job, 5))
                    : [],
            ];
        }

        return $data;
    }
}
