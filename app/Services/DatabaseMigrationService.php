<?php

namespace App\Services;

use App\Models\DatabaseMigration;
use Symfony\Component\Process\Process;

class DatabaseMigrationService
{
    /**
     * Dump la base origen a un archivo temporal local y la restaura en el destino.
     *
     * No se usa un pipe `dump | restore` en vivo: Symfony Process tiene un bug conocido
     * (symfony/symfony#20114) que hace que alimentar el stdin de un proceso con el stdout de
     * otro en marcha, vía un generador, caiga a ~20 bytes/seg cuando el proceso destino casi no
     * produce su propio stdout — exactamente el caso de `mysql`/`mariadb` restaurando. Por eso
     * se pasa por un archivo temporal (un solo host, sin comprimir, se descarta al terminar).
     */
    public function run(DatabaseMigration $migration): void
    {
        $source = $migration->sourceConnection;
        $target = $migration->targetConnection;

        $tmpPath = tempnam(sys_get_temp_dir(), 'dbmig_');

        try {
            $this->dumpToFile($migration, $source, $tmpPath);
            $this->ensureDatabaseExists($target, $migration->target_db_name);
            $this->restoreFromFile($migration, $target, $tmpPath);
        } finally {
            @unlink($tmpPath);
        }
    }

    private function dumpToFile(DatabaseMigration $migration, $connection, string $tmpPath): void
    {
        $command = [
            'mysqldump',
            '-h', $connection->host,
            '-P', (string) $connection->port,
            '-u', $connection->username,
            '--single-transaction',
            '--quick',
            $migration->source_db_name,
        ];

        foreach ($migration->tables ?? [] as $table) {
            $command[] = $table;
        }

        $process = new Process($command, null, ['MYSQL_PWD' => $connection->password]);
        $process->setTimeout(null);

        $fh = fopen($tmpPath, 'wb');
        $process->run(function ($type, $buffer) use ($fh) {
            if ($type === Process::OUT) {
                fwrite($fh, $buffer);
            }
        });
        fclose($fh);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysqldump (origen) falló: '.$process->getErrorOutput());
        }

        if (filesize($tmpPath) === 0) {
            throw new \RuntimeException('El dump de origen se generó vacío.');
        }
    }

    private function ensureDatabaseExists($connection, string $dbName): void
    {
        $process = new Process([
            'mysql',
            '-h', $connection->host,
            '-P', (string) $connection->port,
            '-u', $connection->username,
            '-e', "CREATE DATABASE IF NOT EXISTS `{$dbName}`",
        ], null, ['MYSQL_PWD' => $connection->password]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('No se pudo crear la base destino: '.$process->getErrorOutput());
        }
    }

    private function restoreFromFile(DatabaseMigration $migration, $connection, string $tmpPath): void
    {
        $process = new Process([
            'mysql',
            '-h', $connection->host,
            '-P', (string) $connection->port,
            '-u', $connection->username,
            $migration->target_db_name,
        ], null, ['MYSQL_PWD' => $connection->password]);
        $process->setTimeout(null);
        $process->setInput(fopen($tmpPath, 'rb'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Restore (destino) falló: '.$process->getErrorOutput());
        }
    }
}
