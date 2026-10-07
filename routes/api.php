<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\RunController;
use App\Http\Controllers\Api\TaskController;
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

        // Tareas (notas del vault de Obsidian). {task} = nombre de la nota, URL-encoded.
        Route::get('tasks/config', [TaskController::class, 'config']);
        Route::get('tasks', [TaskController::class, 'index']);
        Route::post('tasks', [TaskController::class, 'store']);
        Route::post('tasks/reordenar', [TaskController::class, 'reordenar']);
        Route::get('tasks/{task}', [TaskController::class, 'show']);
        Route::put('tasks/{task}', [TaskController::class, 'update']);
        Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
        Route::patch('tasks/{task}/estado', [TaskController::class, 'estado']);
        Route::patch('tasks/{task}/fecha', [TaskController::class, 'fecha']);
        Route::patch('tasks/{task}/subtareas/{indice}', [TaskController::class, 'subtarea'])->whereNumber('indice');
    });
});
