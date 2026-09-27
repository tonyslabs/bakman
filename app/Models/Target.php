<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Target extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'hostname', 'ssh_port', 'ssh_user', 'notes'];

    public function backupJobs(): HasMany
    {
        return $this->hasMany(BackupJob::class);
    }
}
