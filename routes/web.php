<?php

use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\BackupJobController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseConnectionController;
use App\Http\Controllers\DatabaseDiffController;
use App\Http\Controllers\DatabaseDiffSyncController;
use App\Http\Controllers\DatabaseMigrationController;
use App\Http\Controllers\FileBrowserController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TargetController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/monitor', MonitorController::class)->name('monitor');

    Route::resource('targets', TargetController::class)->except(['show']);

    Route::resource('backup-jobs', BackupJobController::class)->except(['show']);
    Route::post('backup-jobs/{backup_job}/run', [BackupJobController::class, 'run'])->name('backup-jobs.run');
    Route::get('backup-jobs/{backup_job}/runs', [BackupJobController::class, 'runs'])->name('backup-jobs.runs');
    Route::get('backup-job-runs/{run}/download', BackupDownloadController::class)->name('backup-job-runs.download');

    Route::resource('database-connections', DatabaseConnectionController::class)->except(['show']);
    Route::post('database-connections/test', [DatabaseConnectionController::class, 'test'])->name('database-connections.test');
    Route::get('database-connections/{database_connection}/databases', [DatabaseConnectionController::class, 'databases'])->name('database-connections.databases');
    Route::post('database-connections/{database_connection}/env', [DatabaseConnectionController::class, 'env'])->name('database-connections.env');

    Route::get('database-migrations', [DatabaseMigrationController::class, 'index'])->name('database-migrations.index');
    Route::get('database-migrations/create', [DatabaseMigrationController::class, 'create'])->name('database-migrations.create');
    Route::post('database-migrations', [DatabaseMigrationController::class, 'store'])->name('database-migrations.store');

    Route::get('database-diff', [DatabaseDiffController::class, 'create'])->name('database-diff.create');
    Route::post('database-diff', [DatabaseDiffController::class, 'compare'])->name('database-diff.compare');

    Route::get('database-diff-syncs', [DatabaseDiffSyncController::class, 'index'])->name('database-diff-syncs.index');
    Route::post('database-diff-syncs', [DatabaseDiffSyncController::class, 'store'])->name('database-diff-syncs.store');

    Route::get('lab', fn () => redirect()->route('lab.show', 'develop'))->name('lab.index');
    Route::get('lab/{module}', [LabController::class, 'show'])->name('lab.show');
    Route::get('lab/{module}/projects/create', [LabController::class, 'create'])->name('lab.projects.create');
    Route::post('lab/{module}/projects', [LabController::class, 'store'])->name('lab.projects.store');
    Route::get('lab-projects/{lab_project}/edit', [LabController::class, 'edit'])->name('lab.projects.edit');
    Route::put('lab-projects/{lab_project}', [LabController::class, 'update'])->name('lab.projects.update');
    Route::delete('lab-projects/{lab_project}', [LabController::class, 'destroy'])->name('lab.projects.destroy');
    Route::get('lab-projects/{lab_project}/ping', [LabController::class, 'ping'])->name('lab.projects.ping');
    Route::post('lab-projects/{lab_project}/toggle-hidden', [LabController::class, 'toggleHidden'])->name('lab.projects.toggle-hidden');
    Route::post('lab/homelab/discover', [LabController::class, 'discover'])->name('lab.discover');

    Route::get('files', [FileBrowserController::class, 'index'])->name('files.index');
    Route::get('files/download', [FileBrowserController::class, 'download'])->name('files.download');

    Route::get('settings/ssh-key', [SettingsController::class, 'sshKey'])->name('settings.ssh-key');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
