<?php

namespace Tests\Feature;

use App\Models\BackupJob;
use App\Models\DatabaseConnection;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_jobs_go_under_connection_then_database(): void
    {
        config(['backups.path' => '/data/backups']);
        $connection = DatabaseConnection::create([
            'name' => 'VPS', 'host' => 'x', 'port' => 3306, 'username' => 'root', 'password' => 'x',
        ]);
        $job = BackupJob::create([
            'name' => 'dvprod', 'type' => 'database', 'database_connection_id' => $connection->id,
            'db_name' => 'dvprod', 'schedule_cron' => '0 */3 * * *', 'enabled' => true,
        ]);

        $this->assertSame('/data/backups/vps/dvprod', app(BackupService::class)->jobDirectory($job));
        $this->assertMatchesRegularExpression('/^dvprod_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/', app(BackupService::class)->fileName($job, 'sql.gz'));
    }

    public function test_retention_only_touches_files_of_the_same_job(): void
    {
        $root = sys_get_temp_dir().'/retention-'.uniqid();
        config(['backups.path' => $root]);
        $connection = DatabaseConnection::create([
            'name' => 'VPS', 'host' => 'x', 'port' => 3306, 'username' => 'root', 'password' => 'x',
        ]);
        $job = BackupJob::create([
            'name' => 'dvprod', 'type' => 'database', 'database_connection_id' => $connection->id,
            'db_name' => 'dvprod', 'schedule_cron' => '0 */3 * * *', 'retention_count' => 2, 'enabled' => true,
        ]);

        $dir = $root.'/vps/dvprod';
        File::ensureDirectoryExists($dir);
        foreach ([1, 2, 3] as $i) {
            touch("{$dir}/dvprod_2026-09-2{$i}_000000.sql.gz", 1000 + $i);
        }
        touch("{$dir}/dvprod-esquema_2026-09-20_000000.sql.gz", 1);

        app(BackupService::class)->applyRetention($job);

        $left = collect(File::files($dir))->map->getFilename()->sort()->values()->all();
        $this->assertSame([
            'dvprod-esquema_2026-09-20_000000.sql.gz',
            'dvprod_2026-09-22_000000.sql.gz',
            'dvprod_2026-09-23_000000.sql.gz',
        ], $left);

        File::deleteDirectory($root);
    }
}
