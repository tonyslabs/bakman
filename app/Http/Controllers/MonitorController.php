<?php

namespace App\Http\Controllers;

use App\Models\BackupJob;
use App\Services\JobSchedule;

class MonitorController extends Controller
{
    public function __invoke(JobSchedule $schedule)
    {
        $jobs = BackupJob::with(['target', 'databaseConnection', 'runs' => fn ($q) => $q->limit(1)])
            ->orderByDesc('enabled')
            ->orderBy('name')
            ->get()
            ->map(function (BackupJob $job) use ($schedule) {
                $last = $job->runs->first();

                return [
                    'job' => $job,
                    'last' => $last,
                    'lastSuccess' => $job->runs()->where('status', 'success')->first(),
                    'next' => $job->enabled ? $schedule->nextRun($job) : null,
                    'overdue' => $schedule->isOverdue($job, $last),
                    'stuck' => $last && $schedule->isStuck($job, $last),
                ];
            });

        return view('monitor.index', ['rows' => $jobs]);
    }
}
