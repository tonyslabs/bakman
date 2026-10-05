<?php

namespace App\Http\Controllers\Api;

use App\Models\BackupJob;
use App\Models\BackupJobRun;

/**
 * Forma JSON de jobs y runs, compartida por los controladores de la API.
 * Fechas en ISO 8601 (UTC); la app las muestra en la hora del teléfono.
 */
final class Payload
{
    public static function run(?BackupJobRun $run, bool $withJob = false): ?array
    {
        if (! $run) {
            return null;
        }

        $data = [
            'id' => $run->id,
            'job_id' => $run->backup_job_id,
            'status' => $run->status,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'duration_seconds' => $run->duration_seconds,
            'size_bytes' => $run->size_bytes !== null ? (int) $run->size_bytes : null,
            'error_message' => $run->error_message,
            // Los scripts dejan un log legible; los backups dejan un archivo (.sql.gz, .tar.gz).
            'has_log' => $run->output_path !== null && $run->backupJob?->type === 'script',
        ];

        if ($withJob) {
            $data['job'] = self::jobRef($run->backupJob);
        }

        return $data;
    }

    public static function jobRef(?BackupJob $job): ?array
    {
        return $job ? ['id' => $job->id, 'name' => $job->name, 'type' => $job->type] : null;
    }
}
