<?php

return [
    'path' => env('BACKUPS_PATH', '/data/backups'),

    // Logs de los jobs de tipo script (uno por ejecución).
    'logs_path' => env('LOGS_PATH', '/data/logs'),
    'script_default_timeout' => 3000,
    'script_max_timeout' => 3300, // por debajo del --timeout=3600 del queue worker
    'ssh_key_path' => env('SSH_KEY_PATH', '/data/ssh/id_ed25519'),
    'ssh_known_hosts_path' => env('SSH_KNOWN_HOSTS_PATH', '/data/ssh/known_hosts'),

    // Zona horaria en la que se interpretan los cron de los jobs y se nombran
    // los archivos (la app corre en UTC).
    'timezone' => env('BACKUPS_TIMEZONE', 'America/Managua'),

    // Ruta con la que se crea el primer disco del explorador al migrar una instalación nueva.
    // Después los discos se gestionan desde Almacenamiento (botón +); esta variable ya no se usa.
    'browse_root' => env('FILES_ROOT', '/mnt/external'),
];
