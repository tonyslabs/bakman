<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseMigration extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_connection_id', 'source_db_name',
        'target_connection_id', 'target_db_name',
        'tables', 'status', 'started_at', 'finished_at',
        'duration_seconds', 'error_message',
    ];

    protected $casts = [
        'tables' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function sourceConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class, 'source_connection_id');
    }

    public function targetConnection(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class, 'target_connection_id');
    }
}
