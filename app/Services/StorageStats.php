<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Uso del disco y lo que ocupan backups y logs. Misma clave de caché y misma
 * forma que DashboardController::storage(), así web y API comparten el cálculo.
 */
class StorageStats
{
    public function get(): array
    {
        return Cache::remember('dashboard.storage', 600, function () {
            $root = config('backups.browse_root');

            return [
                'total' => @disk_total_space($root) ?: null,
                'free' => @disk_free_space($root) ?: null,
                'backups' => $this->directoryStats(config('backups.path')),
                'logs' => $this->directoryStats(config('backups.logs_path')),
            ];
        });
    }

    private function directoryStats(string $dir): array
    {
        $bytes = 0;
        $files = 0;

        if (is_dir($dir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $bytes += $file->getSize();
                    $files++;
                }
            }
        }

        return ['bytes' => $bytes, 'files' => $files];
    }
}
