<?php

return [
    // ntfy propio en el homelab (~/docker/apps/ntfy). Desde el contenedor se publica por la red
    // `core`; los links de las notificaciones usan las URLs de la tailnet (las abre el teléfono).
    'ntfy' => [
        'url' => env('NTFY_URL'),
        'token' => env('NTFY_TOKEN'),
        'public_url' => env('NTFY_PUBLIC_URL', 'http://100.76.255.29:8093'),
        'timeout' => 5,
    ],

    // Topics (el usuario `bakman` de ntfy solo puede publicar en bakman-*).
    'topics' => [
        'tareas' => env('NTFY_TOPIC_TAREAS', 'bakman-tareas'),
        'jobs' => env('NTFY_TOPIC_JOBS', 'bakman-jobs'),
    ],

    // URL de bakman para los links "Abrir…" de cada aviso.
    'app_url' => env('NOTIFY_APP_URL', env('APP_URL', 'http://100.76.255.29:8091')),

    'tareas' => [
        // Resumen de la mañana (vencidas + hoy) y recordatorio de la tarde (lo que sigue abierto para hoy).
        'manana' => env('TASKS_NOTIFY_MORNING', '07:30'),
        'tarde' => env('TASKS_NOTIFY_EVENING', '17:30'),
        // Máximo de tareas listadas por grupo en un aviso.
        'max_items' => 8,
    ],

    'jobs' => [
        // Tras la primera falla se avisa de nuevo cada N fallas seguidas (no una por corrida).
        'recordar_cada' => (int) env('JOBS_NOTIFY_REPEAT_EVERY', 5),
    ],
];
