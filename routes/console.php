<?php

use App\Jobs\RunBackupJob;
use App\Models\BackupJob;
use App\Services\Notify\JobAlerts;
use App\Services\Notify\Notifier;
use App\Services\ServiceDiscovery;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Los jobs se registran directamente al cargar el schedule. Antes se registraban
// dentro de un Schedule::call(), pero para cuando ese closure corre schedule:run
// ya decidió qué eventos tocan, así que ningún job programado se disparaba nunca.
try {
    if (Schema::hasTable('backup_jobs')) {
        // Un job puede llevar varias expresiones separadas por "|" para ritmos que
        // un solo cron no expresa (p. ej. cada 40 min exactos = 3 expresiones).
        BackupJob::query()->where('enabled', true)->each(function (BackupJob $job) {
            foreach (array_filter(array_map('trim', explode('|', $job->schedule_cron))) as $i => $cron) {
                Schedule::job(new RunBackupJob($job))
                    ->cron($cron)
                    ->timezone(config('backups.timezone'))
                    ->name('backup-job-'.$job->id.($i ? '-'.$i : ''))
                    ->withoutOverlapping();
            }
        });
    }
} catch (\Throwable $e) {
    // Sin BD disponible (build, migraciones) no hay nada que programar.
}

// Lab → Homelab: tarjetas y estado de contenedores desde Portainer.
Schedule::call(function () {
    $discovery = app(ServiceDiscovery::class);
    if ($discovery->configured()) {
        $discovery->sync();
    }
})->everyFiveMinutes()->name('lab-discovery')->withoutOverlapping();

// Avisos por ntfy (config/notify.php).
Artisan::command('jobs:vigilar', function (JobAlerts $alerts) {
    $this->line($alerts->watch().' avisos enviados');
})->purpose('Avisa por ntfy de jobs atrasados o colgados');

Artisan::command('avisos:probar {topic=jobs : jobs}', function (Notifier $notifier) {
    if (! $notifier->enabled()) {
        return $this->error('ntfy no está configurado (NTFY_URL / NTFY_TOKEN).');
    }
    $ok = $notifier->send($this->argument('topic'), 'Prueba de bakman', 'Si ves esto, los avisos funcionan 👍', ['tags' => ['tada'], 'click' => Notifier::link('monitor')]);
    $ok ? $this->info('Enviado.') : $this->error('No se pudo enviar (ver log).');
})->purpose('Manda un aviso de prueba por ntfy');

Schedule::command('jobs:vigilar')->everyFiveMinutes()->name('avisos-jobs')->withoutOverlapping();

