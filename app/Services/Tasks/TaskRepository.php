<?php

namespace App\Services\Tasks;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tareas = notas .md de una carpeta del vault de Obsidian (un archivo por tarea).
 *
 * Los archivos son la única fuente de verdad: no hay tabla en la BD. Cada escritura relee el
 * archivo y cambia solo las claves del frontmatter que se tocaron, así una edición hecha en
 * Obsidian mientras tanto no se pierde. El cuerpo se toca solo en tres casos acotados: al
 * editarlo explícitamente (exige que la nota no haya cambiado desde que se cargó: hash), al
 * marcar una subtarea (`- [ ]` → `- [x]`, solo esa línea) y al completar una tarea recurrente
 * (reinicia sus subtareas y agrega una línea a "## Registro").
 */
class TaskRepository
{
    public const DONE = ['hecha', 'cancelada'];

    public const REGISTRO = '## Registro';

    private const CHECKBOX = '/^(\s*[-*+]\s+\[)([ xX])(\]\s+)(.*)$/';

    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = rtrim($path ?? config('tasks.path'), '/');
    }

    public function path(): string
    {
        return $this->path;
    }

    public function available(): bool
    {
        return is_dir($this->path) && is_writable($this->path);
    }

    /** "Hoy" en la zona de darius (la app corre en UTC: de 18:00 en adelante UTC ya es mañana). */
    public static function today(): string
    {
        return now(config('backups.timezone'))->toDateString();
    }

    /** @return Collection<int, array> */
    public function all(): Collection
    {
        if (! is_dir($this->path)) {
            return collect();
        }

        return collect(glob($this->path.'/*.md') ?: [])
            ->map(fn ($file) => $this->load($file))
            ->filter()
            ->sortBy([
                fn ($a, $b) => ($a['orden'] ?? PHP_INT_MAX) <=> ($b['orden'] ?? PHP_INT_MAX),
                fn ($a, $b) => strnatcasecmp($a['titulo'], $b['titulo']),
            ])
            ->values();
    }

    public function find(string $id): ?array
    {
        return $this->load($this->file($id));
    }

    public function findOrFail(string $id): array
    {
        return $this->find($id) ?? abort(404, 'La tarea no existe (¿se renombró o borró en Obsidian?).');
    }

    public function create(string $titulo, array $attrs = [], string $cuerpo = ''): array
    {
        return $this->locked(function () use ($titulo, $attrs, $cuerpo) {
            $id = $this->uniqueId($this->sanitizeTitle($titulo));
            $estado = $attrs['estado'] ?? 'inbox';
            $repite = Recurrence::parse($attrs['repite'] ?? null)?->rule();

            $data = [
                'estado' => $estado,
                'prioridad' => $attrs['prioridad'] ?? 'media',
                'area' => $attrs['area'] ?? 'personal',
                'proyecto' => $attrs['proyecto'] ?? null,
                'vence' => $attrs['vence'] ?? null,
            ];
            // Claves opcionales: solo se escriben si se usan, para no llenar todas las notas de vacíos.
            foreach (['inicio' => $attrs['inicio'] ?? null, 'repite' => $repite] as $key => $value) {
                if ($value) {
                    $data[$key] = $value;
                }
            }
            if ($repite) {
                $data['repite_desde'] = ($attrs['repite_desde'] ?? 'vence') === 'completada' ? 'completada' : 'vence';
                if (! empty($attrs['repite_hasta'])) {
                    $data['repite_hasta'] = $attrs['repite_hasta'];
                }
            }
            $data += [
                'creada' => self::today(),
                'completada' => in_array($estado, self::DONE, true) ? self::today() : null,
                'orden' => $this->nextOrder($estado),
                'tags' => self::tags($attrs['etiquetas'] ?? []),
            ];

            $this->write($this->file($id), Frontmatter::build($data, $cuerpo === '' ? '' : rtrim($cuerpo)."\n"));

            return $this->find($id);
        });
    }

    /**
     * Cambia propiedades (y opcionalmente título y cuerpo) de una tarea.
     *
     * Si se marca `hecha` una tarea con `repite`, no se cierra: vuelve a `pendiente` con la
     * próxima fecha en `vence`, sube `veces`, reinicia sus subtareas y anota la vez en el
     * registro. El resultado trae `recurrio` = próxima fecha en ese caso.
     *
     * @param  array<string, mixed>  $changes  solo las claves que cambian (`etiquetas` → `tags`)
     * @param  string|null  $hash  hash con el que se cargó la nota; obligatorio si se cambia el cuerpo
     */
    public function update(string $id, array $changes, ?string $cuerpo = null, ?string $hash = null, ?string $titulo = null): array
    {
        return $this->locked(function () use ($id, $changes, $cuerpo, $hash, $titulo) {
            $file = $this->file($id);
            $content = $this->read($file);

            if ($cuerpo !== null && $hash !== null && ! hash_equals(sha1($content), $hash)) {
                throw new TaskConflictException('La nota cambió en Obsidian mientras la editabas. Recarga para ver la versión nueva.');
            }

            [$current, $body] = Frontmatter::parse($content);

            if (array_key_exists('etiquetas', $changes)) {
                $changes['tags'] = self::tags($changes['etiquetas']);
                unset($changes['etiquetas']);
            }
            if (array_key_exists('repite', $changes)) {
                $changes['repite'] = Recurrence::parse($changes['repite'])?->rule();
            }

            [$changes, $body, $recurrio] = $this->recur($current, $changes, $cuerpo ?? $body);
            $changes = $this->withCompletion($current, $changes);

            if (array_key_exists('estado', $changes) && $changes['estado'] !== ($current['estado'] ?? null) && ! array_key_exists('orden', $changes)) {
                $changes['orden'] = $this->nextOrder($changes['estado']);
            }

            $new = Frontmatter::patch($content, $changes);
            if ($cuerpo !== null || $recurrio) {
                $new = $this->replaceBody($new, $body, explicit: $cuerpo !== null);
            }

            if ($new !== $content) {
                $this->write($file, $new);
            }

            if ($titulo !== null && ($titulo = $this->sanitizeTitle($titulo)) !== $id) {
                $newId = $this->uniqueId($titulo);
                if (! rename($file, $this->file($newId))) {
                    throw new RuntimeException("No se pudo renombrar «{$id}».");
                }
                $id = $newId;
            }

            return $this->find($id) + ['recurrio' => $recurrio];
        });
    }

    public function move(string $id, string $estado, ?int $orden = null): array
    {
        return $this->update($id, ['estado' => $estado] + ($orden === null ? [] : ['orden' => $orden]));
    }

    /**
     * Deja $ids en $estado con ese orden (10, 20, 30…). Solo escribe las notas que cambian.
     *
     * @param  list<string>  $ids
     * @return list<array> tareas recurrentes que se reprogramaron en vez de cerrarse
     */
    public function reorder(string $estado, array $ids): array
    {
        $recurrieron = [];

        foreach (array_values($ids) as $i => $id) {
            $task = $this->findOrFail($id);
            $changes = [];
            if ($task['estado'] !== $estado) {
                $changes['estado'] = $estado;
            }
            // Con cambio de estado el orden va siempre explícito (si no, update lo manda al final).
            if ($changes || $task['orden'] !== ($i + 1) * 10) {
                $changes['orden'] = ($i + 1) * 10;
            }
            if ($changes && ($updated = $this->update($id, $changes))['recurrio']) {
                $recurrieron[] = $updated;
            }
        }

        return $recurrieron;
    }

    /** Marca o desmarca la subtarea número $index (0 = la primera `- [ ]` del cuerpo). */
    public function toggleSubtask(string $id, int $index, ?bool $done = null): array
    {
        return $this->locked(function () use ($id, $index, $done) {
            $file = $this->file($id);
            $content = $this->read($file);
            [, $body] = Frontmatter::parse($content);

            $n = 0;
            $lines = explode("\n", $body);
            foreach ($lines as $i => $line) {
                if (rtrim($line, "\r") === self::REGISTRO) {
                    break;
                }
                if (preg_match(self::CHECKBOX, $line, $m)) {
                    if ($n++ === $index) {
                        $mark = ($done ?? $m[2] === ' ') ? 'x' : ' ';
                        $lines[$i] = $m[1].$mark.$m[3].$m[4];
                        $this->write($file, $this->replaceBody($content, implode("\n", $lines)));

                        return $this->find($id);
                    }
                }
            }

            abort(404, 'La subtarea ya no existe (¿se editó la nota en Obsidian?).');
        });
    }

    public function delete(string $id): void
    {
        $file = $this->file($id);
        $this->findOrFail($id);
        unlink($file);
    }

    /** "[[Nota|alias]]" → "alias"; "[[Nota]]" → "Nota". */
    public static function linkLabel(?string $link): ?string
    {
        if (! $link) {
            return null;
        }

        return preg_match('/^\[\[([^\]|#]+)(?:#[^\]|]*)?(?:\|([^\]]+))?\]\]$/', $link, $m)
            ? ($m[2] ?? '') ?: basename($m[1])
            : $link;
    }

    private function load(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }

        $content = $this->read($file);
        [$data, $body] = Frontmatter::parse($content);

        // Notas de la carpeta que no son tareas (p. ej. "00 - Cómo funcionan las tareas").
        if (! isset($data['estado'])) {
            return null;
        }

        $date = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $hoy = self::today();
        $estado = (string) $data['estado'];
        $vence = $date($data['vence'] ?? null);
        $inicio = $date($data['inicio'] ?? null);
        $recurrence = Recurrence::parse(is_string($data['repite'] ?? null) ? $data['repite'] : null);
        $tags = is_array($data['tags'] ?? null) ? $data['tags'] : array_filter([(string) ($data['tags'] ?? '')]);
        $subtareas = $this->subtasks($body);

        return [
            'id' => basename($file, '.md'),
            'titulo' => basename($file, '.md'),
            'estado' => $estado,
            'cerrada' => in_array($estado, self::DONE, true),
            'prioridad' => $data['prioridad'] ?? null,
            'area' => $data['area'] ?? null,
            'area_label' => Sections::areaLabel($data['area'] ?? null),
            'seccion' => Sections::of($data['area'] ?? null),
            'proyecto' => $data['proyecto'] ?? null,
            'proyecto_label' => self::linkLabel($data['proyecto'] ?? null),
            'vence' => $vence,
            'vencida' => $vence !== null && $vence < $hoy && ! in_array($estado, self::DONE, true),
            'inicio' => $inicio,
            'pospuesta' => $inicio !== null && $inicio > $hoy && ! in_array($estado, self::DONE, true),
            'repite' => $recurrence?->rule(),
            'repite_label' => $recurrence?->describe(),
            'repite_desde' => ($data['repite_desde'] ?? 'vence') === 'completada' ? 'completada' : 'vence',
            'repite_hasta' => $date($data['repite_hasta'] ?? null),
            'veces' => (int) ($data['veces'] ?? 0),
            'ultima' => $date($data['ultima'] ?? null),
            'etiquetas' => array_values(array_diff(array_map('strval', $tags), ['tarea'])),
            'subtareas' => $subtareas,
            'progreso' => $subtareas ? [count(array_filter($subtareas, fn ($s) => $s['hecha'])), count($subtareas)] : null,
            'creada' => $data['creada'] ?? null,
            'completada' => $data['completada'] ?? null,
            'orden' => is_int($data['orden'] ?? null) ? $data['orden'] : null,
            'cuerpo' => trim($body, "\r\n"),
            'hash' => sha1($content),
            'modificada' => filemtime($file),
        ];
    }

    /** @return list<array{indice: int, texto: string, hecha: bool}> */
    private function subtasks(string $body): array
    {
        $items = [];
        foreach (explode("\n", $body) as $line) {
            if (rtrim($line, "\r") === self::REGISTRO) {
                break;
            }
            if (preg_match(self::CHECKBOX, rtrim($line, "\r"), $m)) {
                $items[] = ['indice' => count($items), 'texto' => $m[4], 'hecha' => $m[2] !== ' '];
            }
        }

        return $items;
    }

    /** @return array{0: array, 1: string, 2: ?string} [$changes, $body, próxima fecha si se reprogramó] */
    private function recur(array $current, array $changes, string $body): array
    {
        $rule = array_key_exists('repite', $changes) ? $changes['repite'] : ($current['repite'] ?? null);

        if (($changes['estado'] ?? null) !== 'hecha' || ($current['estado'] ?? null) === 'hecha' || ! is_string($rule) || ! Recurrence::parse($rule)) {
            return [$changes, $body, null];
        }

        $hoy = self::today();
        $vence = $changes['vence'] ?? $current['vence'] ?? null;
        $next = Recurrence::nextOccurrence($rule, is_string($vence) && $vence !== '' ? $vence : null, $hoy, $hoy, $current['repite_desde'] ?? 'vence');
        $hasta = $current['repite_hasta'] ?? null;

        // Última repetición (pasó la fecha límite): se cierra como cualquier tarea.
        if ($next === null || (is_string($hasta) && $hasta !== '' && $next > $hasta)) {
            return [$changes, $body, null];
        }

        $changes = array_merge($changes, [
            'estado' => 'pendiente',
            'vence' => $next,
            'completada' => null,
            'ultima' => $hoy,
            'veces' => ((int) ($current['veces'] ?? 0)) + 1,
        ]);
        if (! empty($current['inicio'])) {
            $changes['inicio'] = null;
        }

        return [$changes, $this->logCompletion($this->resetSubtasks($body), $hoy), $next];
    }

    private function resetSubtasks(string $body): string
    {
        $lines = explode("\n", $body);
        foreach ($lines as $i => $line) {
            if (rtrim($line, "\r") === self::REGISTRO) {
                break;
            }
            $lines[$i] = preg_replace('/^(\s*[-*+]\s+\[)[xX](\])/', '$1 $2', $line);
        }

        return implode("\n", $lines);
    }

    /** Agrega "- ✓ fecha" al principio de "## Registro" (lo crea al final si no existe). */
    private function logCompletion(string $body, string $date): string
    {
        $entry = "- ✓ {$date}";
        $lines = explode("\n", $body);

        foreach ($lines as $i => $line) {
            if (rtrim($line, "\r") === self::REGISTRO) {
                $insertAt = $i + 1;
                while (isset($lines[$insertAt]) && trim($lines[$insertAt]) === '') {
                    $insertAt++;
                }
                array_splice($lines, $insertAt, 0, [$entry]);

                return implode("\n", $lines);
            }
        }

        return rtrim($body, "\r\n").($body === '' ? '' : "\n")."\n".self::REGISTRO."\n\n{$entry}\n";
    }

    /** Reemplaza el cuerpo de $content. $explicit: viene del formulario (se normaliza el borde). */
    private function replaceBody(string $content, string $body, bool $explicit = false): string
    {
        [, $old] = Frontmatter::parse($content);
        $head = substr($content, 0, strlen($content) - strlen($old));

        if ($explicit) {
            $body = $body === '' ? '' : "\n".trim($body, "\n")."\n";
        }

        return $head.$body;
    }

    private function withCompletion(array $current, array $changes): array
    {
        if (! array_key_exists('estado', $changes) || array_key_exists('completada', $changes)) {
            return $changes;
        }

        $wasDone = in_array($current['estado'] ?? null, self::DONE, true);
        $isDone = in_array($changes['estado'], self::DONE, true);

        if ($isDone && ! $wasDone) {
            $changes['completada'] = self::today();
        } elseif (! $isDone && $wasDone) {
            $changes['completada'] = null;
        }

        return $changes;
    }

    /** @param  list<string>|string  $etiquetas */
    private static function tags(array|string $etiquetas): array
    {
        $list = is_array($etiquetas) ? $etiquetas : preg_split('/[\s,]+/', $etiquetas);
        $list = array_map(fn ($t) => Str::lower(ltrim(trim((string) $t), '#')), $list);

        return array_values(array_unique(array_filter(['tarea', ...$list], fn ($t) => $t !== '')));
    }

    private function nextOrder(string $estado): int
    {
        $max = $this->all()->where('estado', $estado)->max('orden');

        return ((int) $max) + 10;
    }

    private function file(string $id): string
    {
        if ($id === '' || str_contains($id, '/') || str_contains($id, '\\') || str_starts_with($id, '.')) {
            abort(404);
        }

        return $this->path.'/'.$id.'.md';
    }

    /** Quita lo que rompe nombres de archivo o links de Obsidian ([ ] # ^ | : y separadores). */
    private function sanitizeTitle(string $titulo): string
    {
        $titulo = preg_replace('/[\/\\\\:*?"<>|#^\[\]]+/u', ' ', $titulo);
        $titulo = trim(preg_replace('/\s+/u', ' ', $titulo), ' .');

        return Str::limit($titulo, 120, '') ?: 'Tarea sin título';
    }

    private function uniqueId(string $base): string
    {
        $id = $base;
        for ($n = 2; is_file($this->file($id)); $n++) {
            $id = "{$base} ({$n})";
        }

        return $id;
    }

    private function read(string $file): string
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            abort(404, 'La tarea no existe (¿se renombró o borró en Obsidian?).');
        }

        return $content;
    }

    /** Escritura atómica (temporal + rename) y con el dueño que usa Syncthing. */
    private function write(string $file, string $content): void
    {
        if (! is_dir($this->path)) {
            throw new RuntimeException("No existe la carpeta de tareas: {$this->path}");
        }

        $tmp = $this->path.'/.bakman-tmp-'.Str::random(8);
        if (file_put_contents($tmp, $content) === false) {
            throw new RuntimeException("No se pudo escribir en {$this->path}");
        }

        @chmod($tmp, 0664);
        $uid = config('tasks.file_uid');
        $gid = config('tasks.file_gid');
        if (filled($uid) && function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($tmp, (int) $uid);
            @chgrp($tmp, (int) $gid);
        }

        if (! rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException("No se pudo guardar {$file}");
        }
    }

    /** Reentrante: create/update/toggle se llaman entre sí sin bloquearse. */
    private function locked(callable $callback): mixed
    {
        static $depth = 0;
        if ($depth > 0) {
            return $callback();
        }

        // Un lock por carpeta; web, scheduler y queue corren en el mismo contenedor y comparten /tmp.
        $lock = fopen(sys_get_temp_dir().'/bakman-tasks-'.md5($this->path).'.lock', 'c');
        flock($lock, LOCK_EX);
        $depth++;

        try {
            return $callback();
        } finally {
            $depth--;
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
