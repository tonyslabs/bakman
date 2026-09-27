<?php

namespace App\Services;

use App\Exceptions\ScriptFailedException;
use App\Models\BackupJob;
use App\Models\Target;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BackupService
{
    /**
     * Una carpeta por servidor y, dentro, una por base de datos:
     * {raíz}/{conexión}/{base}/ para jobs de BD y {raíz}/{target}/{job}/ para archivos.
     */
    public function jobDirectory(BackupJob $job): string
    {
        $root = $this->rootFor($job);

        if ($job->type === 'database' && $job->databaseConnection) {
            return $root.'/'.Str::slug($job->databaseConnection->name).'/'.$this->pathSegment($job->db_name);
        }

        if ($job->target) {
            return $root.'/'.Str::slug($job->target->name).'/'.$job->slug;
        }

        return $root.'/'.$job->slug;
    }

    /**
     * Los jobs de script guardan logs, no backups: van a su propia raíz.
     */
    public function rootFor(BackupJob $job): string
    {
        return rtrim(config($job->type === 'script' ? 'backups.logs_path' : 'backups.path'), '/');
    }

    /**
     * Los archivos llevan el slug del job como prefijo: así dos jobs que compartan
     * carpeta (p. ej. completo y solo-esquema de la misma base) no se pisan la retención.
     */
    public function fileName(BackupJob $job, string $extension): string
    {
        return $job->slug.'_'.now(config('backups.timezone'))->format('Y-m-d_His').'.'.$extension;
    }

    private function pathSegment(string $name): string
    {
        return trim(preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name), '-.') ?: 'db';
    }

    public function runFileBackup(BackupJob $job): string
    {
        $target = $job->target;
        abort_if(! $target, 500, 'El job no tiene target asociado.');

        $paths = collect($job->paths ?? [])->filter()->values();
        abort_if($paths->isEmpty(), 500, 'El job no tiene rutas configuradas.');

        $dir = $this->jobDirectory($job);
        File::ensureDirectoryExists($dir);

        $outputPath = $dir.'/'.$this->fileName($job, 'tar.gz');

        $remotePaths = $paths->map(fn ($p) => escapeshellarg($p))->implode(' ');
        $remoteCommand = "tar czf - {$remotePaths} 2>/dev/null";

        $sshCommand = $this->sshCommand($target, $remoteCommand);

        $outHandle = fopen($outputPath, 'w');
        $process = new Process($sshCommand);
        $process->setTimeout(3600);
        $process->run(function ($type, $buffer) use ($outHandle) {
            if ($type === Process::OUT) {
                fwrite($outHandle, $buffer);
            }
        });
        fclose($outHandle);

        if (! $process->isSuccessful()) {
            @unlink($outputPath);
            throw new \RuntimeException('ssh/tar falló: '.$process->getErrorOutput());
        }

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);
            throw new \RuntimeException('El backup se generó vacío.');
        }

        return $outputPath;
    }

    /**
     * Corre el comando del job en su target por SSH y guarda stdout+stderr en un
     * log por ejecución. El `timeout` remoto mata el proceso allá si se pasa del
     * límite (cortar solo el ssh lo dejaría corriendo huérfano en el servidor).
     */
    public function runScript(BackupJob $job): string
    {
        $target = $job->target;
        abort_if(! $target, 500, 'El job no tiene target asociado.');
        abort_if(blank($job->command), 500, 'El job no tiene comando.');

        $dir = $this->jobDirectory($job);
        File::ensureDirectoryExists($dir);
        $logPath = $dir.'/'.$this->fileName($job, 'log');

        $timeout = $job->timeout_seconds ?: config('backups.script_default_timeout');
        $remoteCommand = 'timeout '.(int) $timeout.' sh -c '.escapeshellarg($job->command).' 2>&1';

        $log = fopen($logPath, 'w');
        fwrite($log, '# '.$target->ssh_user.'@'.$target->name.' $ '.$job->command."\n");
        fwrite($log, '# inicio: '.now(config('backups.timezone'))->format('Y-m-d H:i:s')."\n\n");

        $process = new Process($this->sshCommand($target, $remoteCommand));
        $process->setTimeout($timeout + 60);
        $process->run(function ($type, $buffer) use ($log) {
            fwrite($log, $buffer);
        });

        $code = $process->getExitCode();
        fwrite($log, "\n# fin: ".now(config('backups.timezone'))->format('Y-m-d H:i:s').", código de salida {$code}\n");
        fclose($log);

        if ($code !== 0) {
            $reason = match ($code) {
                124 => "Tiempo agotado ({$timeout}s)",
                255 => 'Falló la conexión SSH',
                default => "El script salió con código {$code}",
            };

            throw new ScriptFailedException($reason.': '.$this->tail($logPath, 5), $logPath);
        }

        return $logPath;
    }

    private function sshCommand(Target $target, string $remoteCommand): array
    {
        $knownHosts = config('backups.ssh_known_hosts_path');
        File::ensureDirectoryExists(dirname($knownHosts));

        return [
            'ssh',
            '-i', config('backups.ssh_key_path'),
            '-p', (string) $target->ssh_port,
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'UserKnownHostsFile='.$knownHosts,
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout=15',
            '-o', 'ServerAliveInterval=30',
            "{$target->ssh_user}@{$target->hostname}",
            $remoteCommand,
        ];
    }

    private function tail(string $path, int $lines): string
    {
        $content = collect(file($path, FILE_IGNORE_NEW_LINES) ?: [])
            ->reject(fn ($l) => str_starts_with($l, '# ') || trim($l) === '');

        return $content->slice(-$lines)->implode(' | ');
    }

    public function runDatabaseBackup(BackupJob $job): string
    {
        $connection = $job->databaseConnection;
        abort_if(! $connection, 500, 'El job no tiene conexión de base de datos asociada.');

        $dir = $this->jobDirectory($job);
        File::ensureDirectoryExists($dir);

        $outputPath = $dir.'/'.$this->fileName($job, 'sql.gz');

        $dumpCommand = [
            'mysqldump',
            '-h', $connection->host,
            '-P', (string) $connection->port,
            '-u', $connection->username,
            '--single-transaction',
            '--quick',
        ];

        if ($job->schema_only) {
            $dumpCommand[] = '--no-data';
        }

        $dumpCommand[] = $job->db_name;

        foreach ($job->tables ?? [] as $table) {
            $dumpCommand[] = $table;
        }

        $process = new Process($dumpCommand, null, [
            'MYSQL_PWD' => $connection->password,
        ]);
        $process->setTimeout(3600);

        $gz = null;

        $process->run(function ($type, $buffer) use (&$gz, $outputPath) {
            if ($type !== Process::OUT) {
                return;
            }
            if ($gz === null) {
                $gz = gzopen($outputPath, 'wb9');
            }
            gzwrite($gz, $buffer);
        });
        if ($gz) {
            gzclose($gz);
        }

        if (! $process->isSuccessful()) {
            @unlink($outputPath);
            throw new \RuntimeException('mysqldump falló: '.$process->getErrorOutput());
        }

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);
            throw new \RuntimeException('El dump se generó vacío.');
        }

        return $outputPath;
    }

    public function applyRetention(BackupJob $job): void
    {
        if (! $job->retention_count) {
            return;
        }

        $dir = $this->jobDirectory($job);
        if (! is_dir($dir)) {
            return;
        }

        $prefix = $job->slug.'_';

        $files = collect(File::files($dir))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), $prefix))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        $files->slice($job->retention_count)->each(fn ($f) => @unlink($f->getPathname()));
    }
}
