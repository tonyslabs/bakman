<?php

namespace App\Services\Tasks;

/**
 * Frontmatter YAML plano (`clave: valor`) de las notas de tareas.
 *
 * No es un parser YAML completo a propósito: las tareas solo usan claves de un nivel, y al
 * escribir se reemplazan únicamente las líneas de las claves que cambian. Así se conserva todo
 * lo demás tal cual (orden, comentarios, claves que agregue darius en Obsidian) y el cuerpo.
 */
class Frontmatter
{
    /** @return array{0: array<string, mixed>, 1: string} [propiedades, cuerpo] */
    public static function parse(string $content): array
    {
        [$lines, $body] = self::split($content);
        $data = [];

        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z0-9_-]+):(?:\s+(.*))?$/', $line, $m)) {
                $data[$m[1]] = self::decode(trim($m[2] ?? ''));
            }
        }

        return [$data, $body];
    }

    /** Aplica $changes (clave => valor, null = vacío) sin tocar el resto del archivo. */
    public static function patch(string $content, array $changes): string
    {
        [$lines, $body, $hasFrontmatter] = self::split($content, withFlag: true);
        // Respeta los saltos de línea del frontmatter (CRLF si se editó en Windows); el cuerpo no se toca.
        $eol = preg_match('/\A[^\n]*\r\n/', $hasFrontmatter ? $content : $body) ? "\r\n" : "\n";

        foreach ($changes as $key => $value) {
            $encoded = $key.':'.(($v = self::encode($value)) === '' ? '' : ' '.$v);
            $found = false;
            foreach ($lines as $i => $line) {
                if (preg_match('/^'.preg_quote($key, '/').':(\s|$)/', $line)) {
                    $lines[$i] = $encoded;
                    $found = true;
                    break;
                }
            }
            // Vaciar una clave que no existe no agrega nada (no llenar la nota de claves vacías).
            if (! $found && $encoded !== $key.':') {
                $lines[] = $encoded;
            }
        }

        if (! $hasFrontmatter && $body !== '' && ! preg_match('/\A\r?\n/', $body)) {
            $body = $eol.$body;
        }

        return '---'.$eol.implode($eol, $lines).$eol.'---'.$eol.$body;
    }

    /** Nota nueva: todas las claves de $data, también las vacías (las ven las vistas de Bases). */
    public static function build(array $data, string $body): string
    {
        $lines = array_map(fn ($key, $value) => $key.':'.(($v = self::encode($value)) === '' ? '' : ' '.$v), array_keys($data), $data);

        return "---\n".implode("\n", $lines)."\n---\n".($body === '' ? '' : "\n".ltrim($body, "\n"));
    }

    /** @return array{0: list<string>, 1: string, 2?: bool} */
    private static function split(string $content, bool $withFlag = false): array
    {
        // El cuerpo se devuelve tal cual (sin normalizar saltos de línea) para no tocar sus bytes.
        if (preg_match('/\A---\r?\n(.*?)\r?\n?---(?:\r?\n)?(.*)\z/s', $content, $m)) {
            $lines = $m[1] === '' ? [] : preg_split('/\r?\n/', $m[1]);

            return $withFlag ? [$lines, $m[2], true] : [$lines, $m[2]];
        }

        return $withFlag ? [[], $content, false] : [[], $content];
    }

    private static function decode(string $raw): mixed
    {
        if ($raw === '' || $raw === '~' || $raw === 'null') {
            return null;
        }
        if (preg_match('/^"(.*)"$/s', $raw, $m)) {
            return stripcslashes($m[1]);
        }
        if (preg_match("/^'(.*)'$/s", $raw, $m)) {
            return str_replace("''", "'", $m[1]);
        }
        if (preg_match('/^\[(.*)\]$/s', $raw, $m)) {
            return array_values(array_filter(array_map(fn ($v) => self::decode(trim($v)), explode(',', $m[1])), fn ($v) => $v !== null));
        }
        if (preg_match('/^-?\d+$/', $raw)) {
            return (int) $raw;
        }

        return $raw;
    }

    private static function encode(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_array($value)) {
            return '['.implode(', ', array_map(fn ($v) => self::encode($v), $value)).']';
        }
        if (is_int($value)) {
            return (string) $value;
        }

        $value = (string) $value;
        // Comillas solo cuando hace falta: links [[...]], ":" , "#", o algo que YAML leería como otro tipo.
        if (preg_match('/^[\[\]{}&*!|>\'"%@`#,?-]|: | #|^(true|false|yes|no|null|~)$/i', $value) || trim($value) !== $value) {
            return '"'.addcslashes($value, "\"\\").'"';
        }

        return $value;
    }
}
