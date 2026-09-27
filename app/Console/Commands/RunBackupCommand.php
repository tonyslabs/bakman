<?php

namespace App\Console\Commands;

use App\Jobs\RunBackupJob;
use App\Models\BackupJob;
use Illuminate\Console\Command;

class RunBackupCommand extends Command
{
    protected $signature = 'backups:run {job : ID del backup_job a ejecutar}';

    protected $description = 'Ejecuta (o encola) un backup job por su ID';

    public function handle(): int
    {
        $job = BackupJob::find($this->argument('job'));

        if (! $job) {
            $this->error('No existe ese backup_job.');

            return self::FAILURE;
        }

        RunBackupJob::dispatchSync($job);
        $this->info("Job {$job->id} ({$job->slug}) ejecutado.");

        return self::SUCCESS;
    }
}
