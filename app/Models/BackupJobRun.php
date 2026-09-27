<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupJobRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'backup_job_id', 'status', 'started_at', 'finished_at',
        'duration_seconds', 'size_bytes', 'output_path', 'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function backupJob(): BelongsTo
    {
        return $this->belongsTo(BackupJob::class);
    }
}
