<?php

namespace App\Services\Tasks;

/** Secciones (Trabajo / Personal) y sus áreas, de config/tasks.php. */
class Sections
{
    /** @return array<string, string> clave de área => etiqueta */
    public static function areas(?string $seccion = null): array
    {
        return collect(config('tasks.secciones'))
            ->when($seccion, fn ($c) => $c->only($seccion))
            ->flatMap(fn ($s) => $s['areas'])
            ->all();
    }

    /** @return array<string, string> clave de sección => etiqueta */
    public static function labels(): array
    {
        return collect(config('tasks.secciones'))->map(fn ($s) => $s['label'])->all();
    }

    public static function of(?string $area): ?string
    {
        foreach (config('tasks.secciones') as $key => $seccion) {
            if (array_key_exists((string) $area, $seccion['areas'])) {
                return $key;
            }
        }

        return null;
    }

    public static function areaLabel(?string $area): ?string
    {
        return $area === null ? null : (self::areas()[$area] ?? $area);
    }
}
