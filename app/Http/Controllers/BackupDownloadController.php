<?php

namespace App\Http\Controllers;

use App\Models\BackupJobRun;
use App\Services\BackupService;

class BackupDownloadController extends Controller
{
    public function __invoke(BackupJobRun $run, BackupService $service)
    {
        abort_unless($run->output_path, 404);

        $fullPath = $service->rootFor($run->backupJob).'/'.$run->output_path;

        abort_unless(file_exists($fullPath), 404);

        return response()->download($fullPath);
    }
}
