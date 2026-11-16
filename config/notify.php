<?php

return [
    // Servidor ntfy (url y token se editan en Ajustes). Desde el contenedor se publica por la red
    // interna; los links de las notificaciones usan la URL pública de bakman (la abre el teléfono).
    'ntfy' => [
        'url' => env('NTFY_URL'),
        'token' => env('NTFY_TOKEN'),
        'public_url' => env('NTFY_PUBLIC_URL'),
        'timeout' => 5,
    ],

    // Topics (el usuario `bakman` de ntfy solo puede publicar en bakman-*).
    'topics' => [
        'jobs' => env('NTFY_TOPIC_JOBS', 'bakman-jobs'),
    ],

    // URL de bakman para los links "Abrir…" de cada aviso.
    'app_url' => env('NOTIFY_APP_URL', env('APP_URL')),

    'jobs' => [
        // Tras la primera falla se avisa de nuevo cada N fallas seguidas (no una por corrida).
        'recordar_cada' => (int) env('JOBS_NOTIFY_REPEAT_EVERY', 5),
    ],
];
