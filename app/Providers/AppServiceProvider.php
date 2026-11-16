<?php

namespace App\Providers;

use App\Services\AppSettings;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ajustes editables desde la web (tabla settings) pisan a config()/.env. El queue worker vive
        // días: se reaplican antes de cada job para que un cambio no pida reiniciar el contenedor.
        $this->app->make(AppSettings::class)->apply();
        Queue::before(fn () => $this->app->make(AppSettings::class)->apply());
    }
}
