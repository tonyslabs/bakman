<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\RunController;
use Illuminate\Support\Facades\Route;

/*
 * API para bakman-mobile. Solo suma rutas: la app web no pasa por aquí.
 * Autenticación con tokens de Sanctum (Authorization: Bearer ...).
 */
Route::prefix('v1')->group(function () {
    Route::post('auth/token', [AuthController::class, 'store'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::delete('auth/token', [AuthController::class, 'destroy']);

        Route::get('dashboard', DashboardController::class);

        Route::get('jobs', [JobController::class, 'index']);
        Route::get('jobs/{backupJob}', [JobController::class, 'show']);
        Route::get('jobs/{backupJob}/runs', [JobController::class, 'runs']);
        Route::post('jobs/{backupJob}/run', [JobController::class, 'run']);

        Route::get('runs/{run}/log', [RunController::class, 'log']);
    });
});
