<?php

return [
    // ntfy propio en el homelab (~/docker/apps/ntfy). Desde el contenedor se publica por la red
    // `core`; los links de las notificaciones usan las URLs de la tailnet (las abre el teléfono).
    'ntfy' => [
        'url' => env('NTFY_URL'),
        'token' => env('NTFY_TOKEN'),
        'public_url' => env('NTFY_PUBLIC_URL', 'http://192.168.1.10:8093'),
        'timeout' => 5,
    ],

    // Topics (el usuario `bakman` de ntfy solo puede publicar en bakman-*).
    'topics' => [
        'jobs' => env('NTFY_TOPIC_JOBS', 'bakman-jobs'),
    ],

    // URL de bakman para los links "Abrir…" de cada aviso.
    'app_url' => env('NOTIFY_APP_URL', env('APP_URL', 'http://192.168.1.10:8091')),

    'jobs' => [
        // Tras la primera falla se avisa de nuevo cada N fallas seguidas (no una por corrida).
        'recordar_cada' => (int) env('JOBS_NOTIFY_REPEAT_EVERY', 5),
    ],
];
