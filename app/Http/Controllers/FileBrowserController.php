<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Explorador de solo lectura del disco de la Pi. Nunca escribe ni borra: el
 * volumen además está montado :ro en el contenedor.
 */
class FileBrowserController extends Controller
{
    public function index(Request $request)
    {
        $root = $this->root();
        $absolute = $this->resolve($root, (string) $request->query('path', ''));

        if (is_file($absolute)) {
            return redirect()->route('files.download', ['path' => $this->relative($root, $absolute)]);
        }

        abort_unless(is_dir($absolute), 404);

        $entries = [];
        $readable = is_readable($absolute);

        foreach ($readable ? (@scandir($absolute) ?: []) : [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $full = $absolute.'/'.$name;
            $isDir = is_dir($full);
            $entryReadable = is_readable($full);

            $entries[] = [
                'name' => $name,
                'path' => $this->relative($root, $full),
                'is_dir' => $isDir,
                'readable' => $entryReadable,
                'size' => $isDir ? null : @filesize($full),
                'items' => $isDir && $entryReadable ? max(count(@scandir($full) ?: []) - 2, 0) : null,
                'modified' => @filemtime($full) ?: null,
            ];
        }

        usort($entries, fn ($a, $b) => [$b['is_dir'], strtolower($a['name'])] <=> [$a['is_dir'], strtolower($b['name'])]);

        $current = $this->relative($root, $absolute);
        $crumbs = [];
        $acc = '';
        foreach (array_filter(explode('/', $current), 'strlen') as $part) {
            $acc = ltrim($acc.'/'.$part, '/');
            $crumbs[] = ['name' => $part, 'path' => $acc];
        }

        return view('files.index', [
            'label' => config('backups.browse_label'),
            'current' => $current,
            'parent' => $current === '' ? null : (str_contains($current, '/') ? dirname($current) : ''),
            'crumbs' => $crumbs,
            'entries' => $entries,
            'readable' => $readable,
            'total' => @disk_total_space($root) ?: null,
            'free' => @disk_free_space($root) ?: null,
        ]);
    }

    public function download(Request $request)
    {
        $root = $this->root();
        $absolute = $this->resolve($root, (string) $request->query('path', ''));

        abort_unless(is_file($absolute) && is_readable($absolute), 404);

        return response()->download($absolute);
    }

    private function root(): string
    {
        $root = realpath(config('backups.browse_root'));
        abort_if($root === false, 503, 'El disco no está montado.');

        return $root;
    }

    /**
     * Resuelve la ruta pedida (siguiendo symlinks) y exige que quede dentro de la raíz.
     */
    private function resolve(string $root, string $relative): string
    {
        $real = realpath($root.'/'.ltrim($relative, '/'));

        abort_if($real === false, 404);
        abort_unless($real === $root || str_starts_with($real, $root.'/'), 404);

        return $real;
    }

    private function relative(string $root, string $absolute): string
    {
        return ltrim(substr($absolute, strlen($root)), '/');
    }
}
