<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BackupJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'type', 'target_id', 'paths',
        'database_connection_id', 'db_name', 'tables', 'schema_only',
        'command', 'timeout_seconds',
        'schedule_cron', 'retention_count', 'enabled', 'last_run_at',
    ];

    protected $casts = [
        'paths' => 'array',
        'tables' => 'array',
        'schema_only' => 'boolean',
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
        'timeout_seconds' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (BackupJob $job) {
            if (empty($job->slug)) {
                $job->slug = Str::slug($job->name);
            }
        });
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function databaseConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(BackupJobRun::class)->latest('id');
    }

    public function latestRun(): ?BackupJobRun
    {
        return $this->runs()->first();
    }
}
