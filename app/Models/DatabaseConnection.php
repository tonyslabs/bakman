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

    /** Frameworks para los que se puede copiar el bloque del .env. */
    public const ENV_FRAMEWORKS = ['fastapi' => 'FastAPI', 'laravel' => 'Laravel'];

    /**
     * Bloque listo para pegar en el .env del framework. La base queda vacía:
     * una conexión es un servidor, no una base concreta.
     */
    public function envSnippet(string $framework): string
    {
        $password = (string) ($this->password ?? '');

        return match ($framework) {
            'laravel' => implode("\n", [
                'DB_CONNECTION=mysql',
                'DB_HOST='.$this->host,
                'DB_PORT='.$this->port,
                'DB_DATABASE=',
                'DB_USERNAME='.self::envValue($this->username),
                'DB_PASSWORD='.self::envValue($password),
            ]),
            'fastapi' => implode("\n", [
                'DB_HOST='.$this->host,
                'DB_PORT='.$this->port,
                'DB_NAME=',
                'DB_USER='.self::envValue($this->username),
                'DB_PASSWORD='.self::envValue($password),
                'DATABASE_URL='.self::envValue(sprintf(
                    'mysql+pymysql://%s:%s@%s:%s/',
                    rawurlencode($this->username),
                    rawurlencode($password),
                    $this->host,
                    $this->port,
                )),
            ]),
        };
    }

    /** Comillas solo si hacen falta; las simples no interpolan ni en phpdotenv ni en python-dotenv. */
    private static function envValue(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.\/:@+%-]*$/', $value)) {
            return $value;
        }

        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
