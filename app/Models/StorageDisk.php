<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageDisk extends Model
{
    protected $fillable = ['name', 'path'];

    /**
     * Ruta real del disco, o null si no está montado. Nunca devuelve una ruta prohibida
     * (ver self::isForbidden), aunque la fila de la BD la apunte.
     */
    public function root(): ?string
    {
        $real = realpath($this->path);

        return $real !== false && ! self::isForbidden($real) ? $real : null;
    }

    /**
     * El explorador no puede apuntar a "/", al código de la app ni a la carpeta con la clave SSH
     * (ni a nada que las contenga o esté dentro de ellas).
     */
    public static function isForbidden(string $real): bool
    {
        $protected = array_filter([realpath(base_path()), realpath(dirname(config('backups.ssh_key_path')))]);

        foreach ($protected as $dir) {
            if ($real === '/' || $real === $dir
                || str_starts_with($real, $dir.'/') || str_starts_with($dir, rtrim($real, '/').'/')) {
                return true;
            }
        }

        return false;
    }
}
