<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use App\Models\DatabaseDiffSync;
use PDO;
use PDOStatement;
use Symfony\Component\Process\Process;

/**
 * Aplica el resultado de una comparación: iguala el destino a la fuente de la verdad
 * para una sección puntual (tables|data|views|procedures|functions). "Tables" hace
 * espejo exacto de estructura (incluye DROP de lo que sobre en destino); "data" hace
 * reemplazo completo (TRUNCATE + copia) por tabla común; vistas/procedimientos/funciones
 * se recrean (DROP + CREATE) cuando difieren, ya que no contienen datos propios.
 */
class DatabaseDiffSyncService
{
    public function sync(DatabaseDiffSync $sync): array
    {
        [$source, $sourceDb, $destination, $destinationDb] = $this->resolveDirection($sync);

        return match ($sync->section) {
            'tables' => $this->syncTables($source, $sourceDb, $destination, $destinationDb),
            'data' => $this->syncData($source, $sourceDb, $destination, $destinationDb),
            'views' => $this->syncDefinitions($source, $sourceDb, $destination, $destinationDb, 'view'),
            'procedures' => $this->syncDefinitions($source, $sourceDb, $destination, $destinationDb, 'PROCEDURE'),
            'functions' => $this->syncDefinitions($source, $sourceDb, $destination, $destinationDb, 'FUNCTION'),
            default => throw new \InvalidArgumentException("Sección desconocida: {$sync->section}"),
        };
    }

    /** @return array{0: DatabaseConnection, 1: string, 2: DatabaseConnection, 3: string} */
    private function resolveDirection(DatabaseDiffSync $sync): array
    {
        return $sync->source_side === 'a'
            ? [$sync->connectionA, $sync->db_a, $sync->connectionB, $sync->db_b]
            : [$sync->connectionB, $sync->db_b, $sync->connectionA, $sync->db_a];
    }

    // ---------- Tablas (estructura, espejo exacto) ----------

    private function syncTables(DatabaseConnection $source, string $sourceDb, DatabaseConnection $destination, string $destinationDb): array
    {
        $pdoSource = $source->pdo($sourceDb);
        $pdoDestination = $destination->pdo($destinationDb);

        $sourceTables = $this->tableNames($pdoSource, $sourceDb);
        $destinationTables = $this->tableNames($pdoDestination, $destinationDb);

        $summary = [];

        foreach (array_diff($destinationTables, $sourceTables) as $table) {
            $pdoDestination->exec("DROP TABLE `{$destinationDb}`.`{$table}`");
            $summary[] = "DROP TABLE {$table}";
        }

        $missing = array_values(array_diff($sourceTables, $destinationTables));
        if ($missing !== []) {
            $this->dumpAndRestore($source, $sourceDb, $destination, $destinationDb, $missing, noData: true);
            foreach ($missing as $table) {
                $summary[] = "CREATE TABLE {$table}";
            }
        }

        foreach (array_intersect($sourceTables, $destinationTables) as $table) {
            $columnsSource = $this->columns($pdoSource, $sourceDb, $table);
            $columnsDestination = $this->columns($pdoDestination, $destinationDb, $table);

            $clauses = [];

            foreach (array_diff(array_keys($columnsDestination), array_keys($columnsSource)) as $column) {
                $clauses[] = "DROP COLUMN `{$column}`";
            }
            foreach (array_diff(array_keys($columnsSource), array_keys($columnsDestination)) as $column) {
                $clauses[] = "ADD COLUMN `{$column}` {$columnsSource[$column]}";
            }
            foreach (array_intersect(array_keys($columnsSource), array_keys($columnsDestination)) as $column) {
                if ($columnsSource[$column] !== $columnsDestination[$column]) {
                    $clauses[] = "MODIFY COLUMN `{$column}` {$columnsSource[$column]}";
                }
            }

            if ($clauses !== []) {
                $pdoDestination->exec("ALTER TABLE `{$destinationDb}`.`{$table}` ".implode(', ', $clauses));
                $summary[] = "ALTER TABLE {$table} (".count($clauses).' cambio(s) de columna)';
            }
        }

        return $summary ?: ['Sin cambios: la estructura ya coincidía.'];
    }

    private function tableNames(PDO $pdo, string $db): array
    {
        $stmt = $pdo->prepare(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'"
        );
        $stmt->execute([$db]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function columns(PDO $pdo, string $db, string $table): array
    {
        $stmt = $pdo->prepare(
            'SELECT column_name, column_type, is_nullable FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position'
        );
        $stmt->execute([$db, $table]);

        $columns = [];
        foreach ($this->fetchAllLower($stmt) as $row) {
            $columns[$row['column_name']] = $row['column_type'].($row['is_nullable'] === 'YES' ? ' NULL' : ' NOT NULL');
        }

        return $columns;
    }

    // ---------- Datos (reemplazo completo por tabla común) ----------

    private function syncData(DatabaseConnection $source, string $sourceDb, DatabaseConnection $destination, string $destinationDb): array
    {
        $pdoSource = $source->pdo($sourceDb);
        $pdoDestination = $destination->pdo($destinationDb);

        $sourceTables = $this->tableNames($pdoSource, $sourceDb);
        $destinationTables = $this->tableNames($pdoDestination, $destinationDb);
        $common = array_values(array_intersect($sourceTables, $destinationTables));
        $skipped = array_values(array_diff($sourceTables, $destinationTables));

        if ($common === []) {
            return ['No hay tablas en común para sincronizar datos. Sincronizá primero la estructura (sección Tablas).'];
        }

        foreach ($common as $table) {
            $pdoDestination->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdoDestination->exec("TRUNCATE TABLE `{$destinationDb}`.`{$table}`");
            $pdoDestination->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->dumpAndRestore($source, $sourceDb, $destination, $destinationDb, $common, noCreateInfo: true);

        $summary = array_map(fn ($table) => "Datos reemplazados: {$table}", $common);

        foreach ($skipped as $table) {
            $summary[] = "Omitida (no existe en destino, sincronizá estructura primero): {$table}";
        }

        return $summary;
    }

    // ---------- Vistas / procedimientos / funciones (recrear si difieren) ----------

    private function syncDefinitions(DatabaseConnection $source, string $sourceDb, DatabaseConnection $destination, string $destinationDb, string $type): array
    {
        $pdoSource = $source->pdo($sourceDb);
        $pdoDestination = $destination->pdo($destinationDb);

        $sourceMap = $this->definitions($pdoSource, $sourceDb, $type);
        $destinationMap = $this->definitions($pdoDestination, $destinationDb, $type);

        $label = $type === 'view' ? 'VIEW' : $type;
        $summary = [];

        foreach (array_diff(array_keys($destinationMap), array_keys($sourceMap)) as $name) {
            $pdoDestination->exec("DROP {$label} IF EXISTS `{$destinationDb}`.`{$name}`");
            $summary[] = "DROP {$label} {$name}";
        }

        $toCreate = [];
        foreach (array_keys($sourceMap) as $name) {
            $isNew = ! array_key_exists($name, $destinationMap);
            $isDifferent = ! $isNew && trim($destinationMap[$name]) !== trim($sourceMap[$name]);

            if ($isNew || $isDifferent) {
                $toCreate[] = [$name, $isNew];
            }
        }

        if ($toCreate !== []) {
            // El CREATE reproduce el quoting/sintaxis del sql_mode de origen (p.ej.
            // ANSI_QUOTES usa comillas dobles para identificadores en vez de backticks);
            // se iguala el sql_mode de la sesión destino mientras se ejecutan estos CREATE
            // para que parseen igual que en origen, y se restaura al terminar.
            $sourceSqlMode = (string) $pdoSource->query('SELECT @@sql_mode')->fetchColumn();
            $destinationSqlMode = (string) $pdoDestination->query('SELECT @@sql_mode')->fetchColumn();
            $pdoDestination->exec('SET SESSION sql_mode = '.$pdoDestination->quote($sourceSqlMode));

            try {
                foreach ($toCreate as [$name, $isNew]) {
                    $createStatement = $this->createStatement($pdoSource, $sourceDb, $name, $type);

                    $pdoDestination->exec("DROP {$label} IF EXISTS `{$destinationDb}`.`{$name}`");
                    $pdoDestination->exec($createStatement);
                    $summary[] = ($isNew ? 'CREATE ' : 'REPLACE ').$label." {$name}";
                }
            } finally {
                $pdoDestination->exec('SET SESSION sql_mode = '.$pdoDestination->quote($destinationSqlMode));
            }
        }

        return $summary ?: ["Sin cambios: ya coincidían ({$label})."];
    }

    private function definitions(PDO $pdo, string $db, string $type): array
    {
        if ($type === 'view') {
            $stmt = $pdo->prepare('SELECT table_name, view_definition FROM information_schema.views WHERE table_schema = ?');
            $stmt->execute([$db]);
            [$keyField, $defField] = ['table_name', 'view_definition'];
        } else {
            $stmt = $pdo->prepare(
                'SELECT routine_name, routine_definition FROM information_schema.routines
                 WHERE routine_schema = ? AND routine_type = ?'
            );
            $stmt->execute([$db, $type]);
            [$keyField, $defField] = ['routine_name', 'routine_definition'];
        }

        $map = [];
        foreach ($this->fetchAllLower($stmt) as $row) {
            $map[$row[$keyField]] = DatabaseDiffService::normalizeDefinition((string) $row[$defField], $db);
        }

        return $map;
    }

    private function createStatement(PDO $pdoSource, string $db, string $name, string $type): string
    {
        if ($type === 'view') {
            $stmt = $pdoSource->query("SHOW CREATE VIEW `{$db}`.`{$name}`");
            $row = $this->fetchLower($stmt);
            $sql = $row['create view'];
        } else {
            $stmt = $pdoSource->query("SHOW CREATE {$type} `{$db}`.`{$name}`");
            $row = $this->fetchLower($stmt);
            $sql = $row['create '.strtolower($type)];
        }

        // El destino puede no tener el mismo usuario DEFINER; se quita para usar el usuario actual.
        // El origen puede tener sql_mode=ANSI_QUOTES, en cuyo caso los identificadores vienen
        // entre comillas dobles en vez de backticks.
        $sql = preg_replace('/DEFINER=(`[^`]*`|"[^"]*")@(`[^`]*`|"[^"]*")\s*/i', '', $sql, 1);

        // SHOW CREATE devuelve el nombre calificado con el schema de origen; si se ejecuta tal
        // cual contra el destino, termina creando/reemplazando el objeto en el schema de ORIGEN
        // (mismo servidor, visible desde cualquier conexión). Se quita la calificación para que
        // resuelva contra el default database de la conexión destino.
        $escapedDb = preg_quote($db, '/');
        $escapedName = preg_quote($name, '/');
        $qualifiedPattern = "/[`\"]{$escapedDb}[`\"]\\.[`\"]{$escapedName}[`\"]/";

        return preg_replace($qualifiedPattern, "`{$name}`", $sql, 1);
    }

    // ---------- mysqldump / mysql (misma herramienta que DatabaseMigrationService) ----------

    private function dumpAndRestore(
        DatabaseConnection $source,
        string $sourceDb,
        DatabaseConnection $destination,
        string $destinationDb,
        array $tables,
        bool $noData = false,
        bool $noCreateInfo = false,
    ): void {
        $tmpPath = tempnam(sys_get_temp_dir(), 'dbsync_');

        try {
            $command = [
                'mysqldump',
                '-h', $source->host,
                '-P', (string) $source->port,
                '-u', $source->username,
                '--single-transaction',
                '--quick',
                '--skip-add-locks',
            ];

            if ($noData) {
                $command[] = '--no-data';
            }
            if ($noCreateInfo) {
                $command[] = '--no-create-info';
                $command[] = '--skip-triggers';
            }

            $command[] = $sourceDb;
            array_push($command, ...$tables);

            $dump = new Process($command, null, ['MYSQL_PWD' => $source->password]);
            $dump->setTimeout(null);

            $fh = fopen($tmpPath, 'wb');
            $dump->run(function ($type, $buffer) use ($fh) {
                if ($type === Process::OUT) {
                    fwrite($fh, $buffer);
                }
            });
            fclose($fh);

            if (! $dump->isSuccessful()) {
                throw new \RuntimeException('mysqldump (origen) falló: '.$dump->getErrorOutput());
            }

            $restore = new Process([
                'mysql',
                '-h', $destination->host,
                '-P', (string) $destination->port,
                '-u', $destination->username,
                $destinationDb,
            ], null, ['MYSQL_PWD' => $destination->password]);
            $restore->setTimeout(null);
            $restore->setInput(fopen($tmpPath, 'rb'));
            $restore->run();

            if (! $restore->isSuccessful()) {
                throw new \RuntimeException('Restore (destino) falló: '.$restore->getErrorOutput());
            }
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * information_schema devuelve nombres de columna con casing inconsistente según
     * versión/configuración de MySQL/MariaDB; se normaliza a minúsculas antes de usar.
     */
    private function fetchAllLower(PDOStatement $stmt): array
    {
        return array_map(
            fn (array $row) => array_change_key_case($row, CASE_LOWER),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private function fetchLower(PDOStatement $stmt): array
    {
        return array_change_key_case($stmt->fetch(PDO::FETCH_ASSOC), CASE_LOWER);
    }
}
