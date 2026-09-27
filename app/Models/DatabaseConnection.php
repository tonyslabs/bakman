<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use PDO;

class DatabaseConnection extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'engine', 'host', 'port', 'username', 'password', 'notes'];

    protected $casts = [
        'password' => 'encrypted',
    ];

    public function pdo(?string $database = null): PDO
    {
        $dsn = "mysql:host={$this->host};port={$this->port}";
        if ($database) {
            $dsn .= ";dbname={$database}";
        }

        return new PDO($dsn, $this->username, $this->password ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);
    }

    public function databaseNames(): array
    {
        $stmt = $this->pdo()->query('SHOW DATABASES');
        $reserved = ['information_schema', 'performance_schema', 'mysql', 'sys'];

        return array_values(array_diff($stmt->fetchAll(PDO::FETCH_COLUMN), $reserved));
    }
}
