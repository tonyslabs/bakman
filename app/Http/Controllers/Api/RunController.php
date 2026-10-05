<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupJobRun;
use App\Services\BackupService;
use Illuminate\Http\Request;

class RunController extends Controller
{
    private const DEFAULT_BYTES = 64 * 1024;

    private const MAX_BYTES = 512 * 1024;

    /** Final del log de un run de tipo script (los logs pueden ser largos). */
    public function log(Request $request, BackupJobRun $run, BackupService $service)
    {
        $job = $run->backupJob;
        abort_unless($job?->type === 'script' && $run->output_path, 404, 'Este run no tiene log.');

        $root = realpath($service->rootFor($job));
        $path = realpath($root.'/'.$run->output_path);
        abort_unless($root && $path && str_starts_with($path, $root.'/') && is_file($path), 404, 'El log ya no existe.');

        $size = filesize($path);
        $bytes = min(max((int) $request->query('bytes', self::DEFAULT_BYTES), 1024), self::MAX_BYTES);
        $offset = max(0, $size - $bytes);
        $content = file_get_contents($path, false, null, $offset);

        return response()->json(['data' => [
            'run_id' => $run->id,
            'size_bytes' => $size,
            'truncated' => $offset > 0,
            'content' => mb_scrub($content, 'UTF-8'),
        ]]);
    }
}
