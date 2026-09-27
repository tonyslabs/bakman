<?php

return [
    // Descubrimiento de servicios vía la API de Portainer (ve las 4 máquinas por sus agentes).
    'portainer' => [
        'url' => env('PORTAINER_URL', 'http://portainer:9000'),
        'token' => env('PORTAINER_TOKEN'),
    ],

    // Host del endpoint "local" de Portainer (socket de Docker del homelab): no trae IP en su URL.
    'local_host' => env('LAB_LOCAL_HOST', '100.76.255.29'),

    // IP de cada máquina → sección de Lab. Las que no estén aquí usan el nombre del endpoint en Portainer.
    'sections_by_host' => [
        '100.76.255.29' => 'Homelab',
        '100.108.203.15' => 'Workstation',
        '100.99.131.25' => 'Raspberry Pi',
        '100.84.80.22' => 'VPS (Up)',
    ],

    // Puertos que se sirven por HTTPS.
    'https_ports' => [443, 8443, 9443],

    // Puertos internos que no son web (BD, caché, colas): la tarjeta queda "solo estado".
    'non_web_ports' => [3306, 5432, 6379, 27017, 11211, 5672, 1883, 9100],

    // Nombre de servicio de compose → nombre a mostrar (si no está, se capitaliza).
    'display_names' => [
        'mariadb' => 'MariaDB', 'mysql' => 'MySQL', 'postgres' => 'PostgreSQL', 'redis' => 'Redis',
        'redis_service' => 'Redis', 'cadvisor' => 'cAdvisor', 'node-exporter' => 'Node Exporter',
        'cloudflared' => 'Cloudflared', 'portainer-agent' => 'Portainer Agent', 'portainer_agent' => 'Portainer Agent',
    ],
];
