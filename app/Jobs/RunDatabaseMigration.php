<?php

namespace App\Jobs;

use App\Models\DatabaseMigration;
use App\Services\DatabaseMigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDatabaseMigration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;

    public int $tries = 1;

    public function __construct(public DatabaseMigration $migration)
    {
    }

    public function handle(DatabaseMigrationService $service): void
    {
        $this->migration->update(['status' => 'running', 'started_at' => now()]);

        try {
            $service->run($this->migration);

            $this->migration->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_seconds' => $this->migration->started_at->diffInSeconds(now()),
            ]);
        } catch (\Throwable $e) {
            $this->migration->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_seconds' => $this->migration->started_at->diffInSeconds(now()),
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
