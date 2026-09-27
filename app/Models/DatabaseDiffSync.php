<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseDiffSync extends Model
{
    protected $fillable = [
        'connection_a_id', 'db_a',
        'connection_b_id', 'db_b',
        'section', 'source_side', 'status',
        'summary', 'started_at', 'finished_at',
        'duration_seconds', 'error_message',
    ];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function connectionA(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class, 'connection_a_id');
    }

    public function connectionB(): BelongsTo
    {
        return $this->belongsTo(DatabaseConnection::class, 'connection_b_id');
    }
}
