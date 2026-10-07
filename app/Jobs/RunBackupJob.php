<?php

namespace App\Jobs;

use App\Exceptions\ScriptFailedException;
use App\Models\BackupJob;
use App\Models\BackupJobRun;
use App\Services\BackupService;
use App\Services\Notify\JobAlerts;
use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3700;

    public int $tries = 1;

    public function __construct(public BackupJob $backupJob)
    {
    }

    public function handle(BackupService $service): void
    {
        $run = BackupJobRun::create([
            'backup_job_id' => $this->backupJob->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $outputPath = match ($this->backupJob->type) {
                'file', 'system' => $service->runFileBackup($this->backupJob),
                'database' => $service->runDatabaseBackup($this->backupJob),
                'script' => $service->runScript($this->backupJob),
            };

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_seconds' => $run->started_at->diffInSeconds(now()),
                'size_bytes' => filesize($outputPath),
                'output_path' => str_replace($service->rootFor($this->backupJob).'/', '', $outputPath),
            ]);

            $this->backupJob->update(['last_run_at' => now()]);

            $service->applyRetention($this->backupJob);

            app(JobAlerts::class)->succeeded($this->backupJob, $run);
        } catch (\Throwable $e) {
            $log = $e instanceof ScriptFailedException && file_exists($e->logPath) ? $e->logPath : null;

            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_seconds' => $run->started_at->diffInSeconds(now()),
                'error_message' => $e->getMessage(),
                'size_bytes' => $log ? filesize($log) : null,
                'output_path' => $log ? str_replace($service->rootFor($this->backupJob).'/', '', $log) : null,
            ]);

            if ($log) {
                $service->applyRetention($this->backupJob);
            }

            $this->backupJob->update(['last_run_at' => now()]);

            app(JobAlerts::class)->failed($this->backupJob, $run);
        }
    }

    /**
     * El worker murió a mitad del job (redeploy, OOM, timeout del queue): handle()
     * nunca llegó a cerrar la ejecución, que quedaría en "running" para siempre.
     */
    public function failed(?Throwable $e): void
    {
        $interrupted = BackupJobRun::where('backup_job_id', $this->backupJob->id)->where('status', 'running')->latest('id')->first();

        BackupJobRun::where('backup_job_id', $this->backupJob->id)
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => 'Interrumpido: el worker de bakman se detuvo durante la ejecución'.($e ? ' ('.class_basename($e).')' : '').'.',
            ]);

        if ($interrupted) {
            app(JobAlerts::class)->failed($this->backupJob, $interrupted->fresh());
        }
    }
}
