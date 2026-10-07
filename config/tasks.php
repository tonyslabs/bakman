<?php

return [
    // Carpeta del vault de Obsidian con las tareas (un .md por tarea). El vault se sincroniza
    // laptop ↔ homelab con Syncthing; el compose monta /home/darius/obsidian-vault en /data/vault.
    'path' => env('TASKS_PATH', '/data/vault/05 - Tareas'),

    // El contenedor corre como root: los archivos que escribe se pasan a este dueño para que
    // Syncthing (usuario darius) los pueda seguir modificando. null = no tocar el dueño.
    'file_uid' => env('TASKS_FILE_UID', 1000),
    'file_gid' => env('TASKS_FILE_GID', 1000),

    // Orden = orden de las columnas del tablero.
    'estados' => [
        'inbox' => 'Inbox',
        'pendiente' => 'Pendiente',
        'en-curso' => 'En curso',
        'bloqueada' => 'Bloqueada',
        'hecha' => 'Hecha',
        'cancelada' => 'Cancelada',
    ],

    'prioridades' => [
        'alta' => 'Alta',
        'media' => 'Media',
        'baja' => 'Baja',
    ],

    // Secciones → áreas. Trabajo = las empresas (carpetas 02–04 del vault); Personal = 01.
    // La clave del área es lo que va en el frontmatter (`area: up`).
    'secciones' => [
        'trabajo' => [
            'label' => 'Trabajo',
            'areas' => [
                'up' => 'Up Digital',
                'campos' => 'Campos Law',
                'strivex' => 'Strivex Labs',
            ],
        ],
        'personal' => [
            'label' => 'Personal',
            'areas' => [
                'personal' => 'General',
                'homelab' => 'Homelab e infra',
                'finanzas' => 'Finanzas',
                'casa' => 'Casa',
                'aprendizaje' => 'Aprendizaje',
            ],
        ],
    ],

    // Atajos para posponer (botón ⏱ de cada tarea): etiqueta => modificador de Carbon sobre hoy.
    'posponer' => [
        'Hoy' => 'today',
        'Mañana' => '+1 day',
        'En 3 días' => '+3 days',
        'Próximo lunes' => 'next monday',
        'En una semana' => '+1 week',
        'En un mes' => '+1 month',
    ],
];
