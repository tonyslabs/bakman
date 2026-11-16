<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Ajustes editables desde la web. Cada ajuste pisa una clave de config() (que sigue teniendo su
 * valor por defecto / de .env): si no hay nada guardado, manda el .env; si lo hay, manda la BD.
 * Los secretos se guardan cifrados con APP_KEY.
 */
class AppSettings
{
    private const CACHE_KEY = 'app.settings';

    /**
     * Definición de cada ajuste: grupo, etiqueta, tipo y la clave de config() que pisa.
     * Tipos: text, secret, int, timezone, pairs (líneas "clave = valor"), ports (lista separada por comas).
     *
     * @return array<string, array{group: string, label: string, type: string, config: string, help?: string, placeholder?: string}>
     */
    public static function definitions(): array
    {
        return [
            'portainer.url' => ['group' => 'Portainer', 'label' => 'URL de la API', 'type' => 'text', 'config' => 'lab.portainer.url', 'placeholder' => 'http://portainer:9000', 'help' => 'Desde el contenedor de bakman (nombre en la red Docker o IP).'],
            'portainer.token' => ['group' => 'Portainer', 'label' => 'Token de acceso', 'type' => 'secret', 'config' => 'lab.portainer.token', 'help' => 'Portainer → My account → Access tokens. Con él, Lab descubre los servicios de todas tus máquinas.'],
            'lab.local_host' => ['group' => 'Lab', 'label' => 'IP o host de la máquina local', 'type' => 'text', 'config' => 'lab.local_host', 'help' => 'El endpoint "local" de Portainer (socket de Docker) no trae IP: se usa esta.'],
            'lab.sections' => ['group' => 'Lab', 'label' => 'Nombre de cada máquina', 'type' => 'pairs', 'config' => 'lab.sections_by_host', 'placeholder' => "192.168.1.10 = Homelab\n192.168.1.20 = Servidor", 'help' => 'Una por línea: IP = nombre de la sección en Lab. Las que falten usan el nombre del endpoint en Portainer.'],
            'lab.https_ports' => ['group' => 'Lab', 'label' => 'Puertos HTTPS', 'type' => 'ports', 'config' => 'lab.https_ports', 'placeholder' => '443, 8443, 9443', 'help' => 'Los servicios en estos puertos se abren con https.'],
            'lab.non_web_ports' => ['group' => 'Lab', 'label' => 'Puertos que no son web', 'type' => 'ports', 'config' => 'lab.non_web_ports', 'placeholder' => '3306, 5432, 6379', 'help' => 'Bases de datos, cachés y colas: la tarjeta queda en "solo estado".'],
            'lab.display_names' => ['group' => 'Lab', 'label' => 'Nombres a mostrar', 'type' => 'pairs', 'config' => 'lab.display_names', 'placeholder' => "mariadb = MariaDB\nredis = Redis", 'help' => 'Una por línea: nombre del servicio en compose = nombre a mostrar.'],
            'ntfy.url' => ['group' => 'Notificaciones (ntfy)', 'label' => 'URL del servidor ntfy', 'type' => 'text', 'config' => 'notify.ntfy.url', 'placeholder' => 'http://ntfy:80', 'help' => 'Desde el contenedor de bakman. Sin URL y token los avisos quedan apagados.'],
            'ntfy.token' => ['group' => 'Notificaciones (ntfy)', 'label' => 'Token', 'type' => 'secret', 'config' => 'notify.ntfy.token'],
            'ntfy.topic' => ['group' => 'Notificaciones (ntfy)', 'label' => 'Topic de avisos de jobs', 'type' => 'text', 'config' => 'notify.topics.jobs', 'placeholder' => 'bakman-jobs'],
            'ntfy.app_url' => ['group' => 'Notificaciones (ntfy)', 'label' => 'URL pública de bakman', 'type' => 'text', 'config' => 'notify.app_url', 'placeholder' => 'http://mi-servidor:8091', 'help' => 'Para los botones "Abrir…" de cada aviso: la URL con la que abres bakman desde el teléfono.'],
            'general.timezone' => ['group' => 'General', 'label' => 'Zona horaria', 'type' => 'timezone', 'config' => 'backups.timezone', 'help' => 'En ella se interpretan los cron de los jobs y se nombran los archivos de backup.'],
            'paths.backups' => ['group' => 'Rutas del contenedor', 'label' => 'Carpeta de backups', 'type' => 'text', 'config' => 'backups.path', 'placeholder' => '/data/backups', 'help' => 'Ruta dentro del contenedor (el volumen se monta en docker-compose).'],
            'paths.logs' => ['group' => 'Rutas del contenedor', 'label' => 'Carpeta de logs de scripts', 'type' => 'text', 'config' => 'backups.logs_path', 'placeholder' => '/data/logs'],
            'jobs.timeout' => ['group' => 'Jobs', 'label' => 'Tiempo máximo por defecto (s)', 'type' => 'int', 'config' => 'backups.script_default_timeout', 'help' => 'Para jobs de tipo script que no definen el suyo.'],
            'jobs.max_timeout' => ['group' => 'Jobs', 'label' => 'Tiempo máximo permitido (s)', 'type' => 'int', 'config' => 'backups.script_max_timeout', 'help' => 'Tope que se puede poner a un job. Debe quedar por debajo del --timeout del queue worker (3600 s).'],
        ];
    }

    /** Valor guardado ya decodificado (secretos descifrados), clave => valor. */
    public function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $values = [];
            foreach (Setting::all() as $row) {
                try {
                    $values[$row->key] = $row->encrypted ? Crypt::decryptString($row->value) : $row->value;
                } catch (Throwable) {
                    // APP_KEY cambió: el secreto es ilegible, se ignora y vuelve a mandar el .env.
                }
            }

            return $values;
        });
    }

    public function set(string $key, ?string $value): void
    {
        $definition = self::definitions()[$key] ?? null;
        abort_if($definition === null, 422, "Ajuste desconocido: {$key}");

        if ($value === null || trim($value) === '') {
            Setting::whereKey($key)->delete();
        } else {
            $secret = $definition['type'] === 'secret';
            Setting::updateOrCreate(['key' => $key], [
                'value' => $secret ? Crypt::encryptString($value) : $value,
                'encrypted' => $secret,
            ]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** Pisa config() con lo guardado. Llamar al arrancar y antes de cada job de la cola. */
    public function apply(): void
    {
        try {
            $stored = $this->stored();
        } catch (Throwable) {
            return; // la tabla todavía no existe (primer migrate)
        }

        foreach (self::definitions() as $key => $definition) {
            if (isset($stored[$key])) {
                config([$definition['config'] => self::cast($definition['type'], $stored[$key])]);
            }
        }
    }

    /** Valor tal como se muestra en el formulario (config ya aplicado). */
    public function display(string $key): string
    {
        $definition = self::definitions()[$key];
        $value = config($definition['config']);

        return match ($definition['type']) {
            'pairs' => collect((array) $value)->map(fn ($v, $k) => "{$k} = {$v}")->implode("\n"),
            'ports' => implode(', ', (array) $value),
            default => (string) ($value ?? ''),
        };
    }

    public static function cast(string $type, string $raw): mixed
    {
        return match ($type) {
            'int' => (int) $raw,
            'pairs' => self::parsePairs($raw),
            'ports' => array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY)))),
            default => $raw,
        };
    }

    /** "a = b" por línea → ['a' => 'b']. Ignora líneas vacías o sin "=". */
    public static function parsePairs(string $raw): array
    {
        $pairs = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if ($k !== '' && $v !== '') {
                    $pairs[$k] = $v;
                }
            }
        }

        return $pairs;
    }
}
