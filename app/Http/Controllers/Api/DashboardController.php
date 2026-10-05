<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupJob;
use App\Models\BackupJobRun;
use App\Models\DatabaseConnection;
use App\Models\Target;
use App\Services\JobSchedule;
use App\Services\StorageStats;

class DashboardController extends Controller
{
    public function __invoke(JobSchedule $schedule, StorageStats $storage)
    {
        $jobs = BackupJob::with(['runs' => fn ($q) => $q->limit(1)])->orderBy('name')->get();

        // Misma regla que el dashboard web: último run fallido, atrasado o colgado.
        $attention = [];
        foreach ($jobs as $job) {
            $last = $job->runs->first();
            $kind = null;
            $text = null;

            if ($last && $schedule->isStuck($job, $last)) {
                $kind = 'stuck';
                $text = 'Lleva '.$last->started_at->diffForHumans(null, true).' corriendo, más que su tiempo máximo';
            } elseif ($job->enabled && $last?->status === 'failed') {
                $kind = 'failed';
                $text = $last->error_message ?: 'Falló sin mensaje';
            } elseif ($schedule->isOverdue($job, $last)) {
                $kind = 'overdue';
                $text = 'Le tocaba correr y no hay ejecución desde '.$schedule->previousDue($job)->diffForHumans();
            }

            if ($kind) {
                $attention[] = ['job' => Payload::jobRef($job), 'kind' => $kind, 'text' => $text, 'run_id' => $last?->id];
            }
        }

        $upcoming = [];
        foreach ($jobs->where('enabled', true) as $job) {
            foreach ($schedule->upcoming($job, 5) as $date) {
                $upcoming[] = ['job' => Payload::jobRef($job), 'at' => $date];
            }
        }
        usort($upcoming, fn ($a, $b) => $a['at'] <=> $b['at']);
        $upcoming = array_map(
            fn ($u) => ['job' => $u['job'], 'at' => $u['at']->toIso8601String()],
            array_slice($upcoming, 0, 6),
        );

        $runs24h = BackupJobRun::where('started_at', '>=', now()->subDay())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $lastDbBackup = BackupJobRun::with('backupJob')
            ->where('status', 'success')
            ->whereHas('backupJob', fn ($q) => $q->where('type', 'database'))
            ->latest('id')
            ->first();

        return response()->json(['data' => [
            'status' => $attention ? 'attention' : 'ok',
            'jobs' => [
                'total' => $jobs->count(),
                'enabled' => $jobs->where('enabled', true)->count(),
                'by_type' => $jobs->countBy('type'),
            ],
            'runs_24h' => [
                'success' => (int) ($runs24h['success'] ?? 0),
                'failed' => (int) ($runs24h['failed'] ?? 0),
            ],
            'last_db_backup' => Payload::run($lastDbBackup, withJob: true),
            'storage' => $storage->get(),
            'attention' => $attention,
            'upcoming' => $upcoming,
            'recent' => BackupJobRun::with('backupJob')->latest('id')->limit(8)->get()
                ->map(fn ($run) => Payload::run($run, withJob: true)),
            'inventory' => [
                'connections' => DatabaseConnection::count(),
                'targets' => Target::count(),
            ],
        ]]);
    }
}
