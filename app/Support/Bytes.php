<?php

namespace App\Support;

/**
 * Tamaños legibles sin depender de la extensión intl (Number::fileSize la
 * requiere y la imagen no la trae).
 */
class Bytes
{
    public static function human(int|float|null $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (int) $bytes : number_format($bytes, 1)).' '.$units[$i];
    }

    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        return $seconds < 60 ? $seconds.' s' : intdiv($seconds, 60).' min '.($seconds % 60).' s';
    }
}
