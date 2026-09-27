<?php

namespace App\Jobs;

use App\Models\DatabaseDiffSync;
use App\Services\DatabaseDiffSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDatabaseDiffSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public DatabaseDiffSync $sync)
    {
    }

    public function handle(DatabaseDiffSyncService $service): void
    {
        $this->sync->update(['status' => 'running', 'started_at' => now()]);

        try {
            $summary = $service->sync($this->sync);

            $this->sync->update([
                'status' => 'success',
                'summary' => $summary,
                'finished_at' => now(),
                'duration_seconds' => $this->sync->started_at->diffInSeconds(now()),
            ]);
        } catch (\Throwable $e) {
            $this->sync->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_seconds' => $this->sync->started_at->diffInSeconds(now()),
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
