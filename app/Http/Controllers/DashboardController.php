<?php

namespace App\Http\Controllers;

use App\Models\BackupJob;
use App\Models\BackupJobRun;
use App\Models\DatabaseConnection;
use App\Models\Target;
use App\Services\JobSchedule;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __invoke(JobSchedule $schedule)
    {
        $jobs = BackupJob::with(['runs' => fn ($q) => $q->limit(1)])->orderBy('name')->get();

        // Lo que requiere atención: último run fallido, atrasado o colgado.
        $attention = [];
        foreach ($jobs as $job) {
            $last = $job->runs->first();

            if ($last && $schedule->isStuck($job, $last)) {
                $attention[] = ['job' => $job, 'kind' => 'stuck', 'text' => 'Lleva '.$last->started_at->diffForHumans(null, true).' corriendo, más que su tiempo máximo', 'run' => $last];
            } elseif ($job->enabled && $last?->status === 'failed') {
                $attention[] = ['job' => $job, 'kind' => 'failed', 'text' => $last->error_message ?: 'Falló sin mensaje', 'run' => $last];
            } elseif ($schedule->isOverdue($job, $last)) {
                $attention[] = ['job' => $job, 'kind' => 'overdue', 'text' => 'Le tocaba correr y no hay ejecución desde '.$schedule->previousDue($job)->diffForHumans(), 'run' => $last];
            }
        }

        $upcoming = [];
        foreach ($jobs->where('enabled', true) as $job) {
            foreach ($schedule->upcoming($job, 5) as $date) {
                $upcoming[] = ['job' => $job, 'at' => $date];
            }
        }
        usort($upcoming, fn ($a, $b) => $a['at'] <=> $b['at']);

        $since24h = now()->subDay();
        $runs24h = BackupJobRun::where('started_at', '>=', $since24h)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $lastDbBackup = BackupJobRun::with('backupJob')
            ->where('status', 'success')
            ->whereHas('backupJob', fn ($q) => $q->where('type', 'database'))
            ->latest('id')
            ->first();

        return view('dashboard', [
            'jobsTotal' => $jobs->count(),
            'jobsEnabled' => $jobs->where('enabled', true)->count(),
            'jobsByType' => $jobs->countBy('type'),
            'attention' => $attention,
            'upcoming' => array_slice($upcoming, 0, 6),
            'recent' => BackupJobRun::with('backupJob')->latest('id')->limit(8)->get(),
            'ok24h' => (int) ($runs24h['success'] ?? 0),
            'failed24h' => (int) ($runs24h['failed'] ?? 0),
            'lastDbBackup' => $lastDbBackup,
            'storage' => $this->storage(),
            'connections' => DatabaseConnection::count(),
            'targets' => Target::count(),
        ]);
    }

    /**
     * Uso del disco y lo que ocupan backups y logs. Recorrer el árbol sobre NFS
     * cuesta, así que se cachea unos minutos.
     */
    private function storage(): array
    {
        return Cache::remember('dashboard.storage', 600, function () {
            $root = config('backups.browse_root');

            return [
                'total' => @disk_total_space($root) ?: null,
                'free' => @disk_free_space($root) ?: null,
                'backups' => $this->directoryStats(config('backups.path')),
                'logs' => $this->directoryStats(config('backups.logs_path')),
            ];
        });
    }

    private function directoryStats(string $dir): array
    {
        $bytes = 0;
        $files = 0;

        if (is_dir($dir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $bytes += $file->getSize();
                    $files++;
                }
            }
        }

        return ['bytes' => $bytes, 'files' => $files];
    }
}
