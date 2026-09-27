<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'module', 'section', 'name', 'scheme', 'host', 'port', 'path', 'description', 'monitor_only',
        'container', 'discovered', 'hidden', 'container_state', 'container_health', 'container_status', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'monitor_only' => 'boolean',
            'discovered' => 'boolean',
            'hidden' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /** 127.0.0.1/localhost: apunta a la máquina de quien abre el enlace; el servidor no puede comprobarlo. */
    public function isLoopback(): bool
    {
        return in_array(strtolower($this->host), ['127.0.0.1', 'localhost', '::1', '0.0.0.0'], true);
    }

    public function getUrlAttribute(): string
    {
        $port = $this->port ? ':'.$this->port : '';
        $path = $this->path ? '/'.ltrim($this->path, '/') : '';

        return "{$this->scheme}://{$this->host}{$port}{$path}";
    }
}
