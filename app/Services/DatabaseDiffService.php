<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use PDO;

class DatabaseDiffService
{
    public const SECTIONS = ['tables', 'views', 'procedures', 'functions', 'data'];

    public function compare(DatabaseConnection $a, string $dbA, DatabaseConnection $b, string $dbB, array $sections): array
    {
        $pdoA = $a->pdo();
        $pdoB = $b->pdo();

        $needsTableNames = in_array('tables', $sections, true) || in_array('data', $sections, true);
        $common = [];

        if ($needsTableNames) {
            $tablesA = $this->tableNames($pdoA, $dbA);
            $tablesB = $this->tableNames($pdoB, $dbB);
            $tableDiff = [
                'only_in_a' => array_values(array_diff($tablesA, $tablesB)),
                'only_in_b' => array_values(array_diff($tablesB, $tablesA)),
            ];
            $common = array_values(array_intersect($tablesA, $tablesB));
        }

        $result = [];

        if (in_array('tables', $sections, true)) {
            $result['tables'] = $tableDiff + [
                'items' => array_map(fn ($table) => [
                    'name' => $table,
                    'columns_diff' => $this->columnsDiff($pdoA, $dbA, $pdoB, $dbB, $table),
                ], $common),
            ];
        }

        if (in_array('data', $sections, true)) {
            $result['data'] = $tableDiff + [
                'items' => array_map(fn ($table) => [
                    'name' => $table,
                    'count_a' => $this->rowCount($pdoA, $dbA, $table),
                    'count_b' => $this->rowCount($pdoB, $dbB, $table),
                    'identical' => $this->checksum($pdoA, $dbA, $table) === $this->checksum($pdoB, $dbB, $table),
                ], $common),
            ];
        }

        if (in_array('views', $sections, true)) {
            $result['views'] = $this->compareDefinitions(
                $this->viewDefinitions($pdoA, $dbA),
                $this->viewDefinitions($pdoB, $dbB),
            );
        }

        if (in_array('procedures', $sections, true)) {
            $result['procedures'] = $this->compareDefinitions(
                $this->routineDefinitions($pdoA, $dbA, 'PROCEDURE'),
                $this->routineDefinitions($pdoB, $dbB, 'PROCEDURE'),
            );
        }

        if (in_array('functions', $sections, true)) {
            $result['functions'] = $this->compareDefinitions(
                $this->routineDefinitions($pdoA, $dbA, 'FUNCTION'),
                $this->routineDefinitions($pdoB, $dbB, 'FUNCTION'),
            );
        }

        return $result;
    }

    private function tableNames(PDO $pdo, string $db): array
    {
        $stmt = $pdo->prepare(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'"
        );
        $stmt->execute([$db]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function checksum(PDO $pdo, string $db, string $table): ?string
    {
        $stmt = $pdo->query("CHECKSUM TABLE `{$db}`.`{$table}`");
        $row = $this->fetchLower($stmt);

        return $row['checksum'] ?? null;
    }

    private function rowCount(PDO $pdo, string $db, string $table): int
    {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `{$db}`.`{$table}`");

        return (int) $stmt->fetchColumn();
    }

    private function columnsDiff(PDO $pdoA, string $dbA, PDO $pdoB, string $dbB, string $table): array
    {
        $columnsA = $this->columns($pdoA, $dbA, $table);
        $columnsB = $this->columns($pdoB, $dbB, $table);

        $onlyInA = array_values(array_diff(array_keys($columnsA), array_keys($columnsB)));
        $onlyInB = array_values(array_diff(array_keys($columnsB), array_keys($columnsA)));

        $changed = [];
        foreach (array_intersect(array_keys($columnsA), array_keys($columnsB)) as $column) {
            if ($columnsA[$column] !== $columnsB[$column]) {
                $changed[] = ['column' => $column, 'a' => $columnsA[$column], 'b' => $columnsB[$column]];
            }
        }

        return ['only_in_a' => $onlyInA, 'only_in_b' => $onlyInB, 'changed' => $changed];
    }

    private function columns(PDO $pdo, string $db, string $table): array
    {
        $stmt = $pdo->prepare(
            'SELECT column_name, column_type, is_nullable FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ?'
        );
        $stmt->execute([$db, $table]);

        $columns = [];
        foreach ($this->fetchAllLower($stmt) as $row) {
            $columns[$row['column_name']] = $row['column_type'].($row['is_nullable'] === 'YES' ? ' NULL' : ' NOT NULL');
        }

        return $columns;
    }

    private function viewDefinitions(PDO $pdo, string $db): array
    {
        $stmt = $pdo->prepare('SELECT table_name, view_definition FROM information_schema.views WHERE table_schema = ?');
        $stmt->execute([$db]);

        $definitions = [];
        foreach ($this->fetchAllLower($stmt) as $row) {
            $definitions[$row['table_name']] = self::normalizeDefinition((string) $row['view_definition'], $db);
        }

        return $definitions;
    }

    private function routineDefinitions(PDO $pdo, string $db, string $type): array
    {
        $stmt = $pdo->prepare(
            'SELECT routine_name, routine_definition FROM information_schema.routines
             WHERE routine_schema = ? AND routine_type = ?'
        );
        $stmt->execute([$db, $type]);

        $definitions = [];
        foreach ($this->fetchAllLower($stmt) as $row) {
            $definitions[$row['routine_name']] = self::normalizeDefinition((string) $row['routine_definition'], $db);
        }

        return $definitions;
    }

    /**
     * information_schema.views/routines siempre devuelve las referencias a tablas calificadas
     * con el nombre de SU PROPIA base de datos (p.ej. `dvprod`.`productos`), incluso si el
     * CREATE original no las calificaba. Al comparar la misma vista/rutina entre dos bases con
     * distinto nombre (o incluso el mismo, según el server), ese prefijo nunca coincide aunque
     * la lógica sea idéntica. Se quita para poder comparar el contenido real.
     */
    public static function normalizeDefinition(string $definition, string $db): string
    {
        return str_replace('`'.$db.'`.', '', trim($definition));
    }

    private function compareDefinitions(array $mapA, array $mapB): array
    {
        $onlyInA = array_values(array_diff(array_keys($mapA), array_keys($mapB)));
        $onlyInB = array_values(array_diff(array_keys($mapB), array_keys($mapA)));

        $items = [];
        foreach (array_intersect(array_keys($mapA), array_keys($mapB)) as $name) {
            $items[] = [
                'name' => $name,
                'identical' => trim($mapA[$name]) === trim($mapB[$name]),
            ];
        }

        return ['only_in_a' => $onlyInA, 'only_in_b' => $onlyInB, 'items' => $items];
    }

    /**
     * information_schema column name casing is inconsistent across MySQL/MariaDB versions
     * and configurations, so results are normalized to lowercase keys before use.
     */
    private function fetchAllLower(\PDOStatement $stmt): array
    {
        return array_map(
            fn (array $row) => array_change_key_case($row, CASE_LOWER),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private function fetchLower(\PDOStatement $stmt): ?array
    {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? array_change_key_case($row, CASE_LOWER) : null;
    }
}
