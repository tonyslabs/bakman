<?php

use App\Models\DatabaseConnection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_jobs', function (Blueprint $table) {
            $table->foreignId('database_connection_id')->nullable()->after('target_id')
                ->constrained()->nullOnDelete();
            $table->json('tables')->nullable()->after('db_password');
            $table->boolean('schema_only')->default(false)->after('tables');
        });

        // Backfill: cada backup_jobs.type=database existente embebe sus propias credenciales;
        // se extrae una DatabaseConnection reutilizable por cada una y se enlaza el job.
        $jobs = DB::table('backup_jobs')->where('type', 'database')->get();

        foreach ($jobs as $job) {
            if (! $job->db_host || ! $job->db_username) {
                continue;
            }

            // db_password se guardó vía el cast `encrypted` de BackupJob (Crypt::encryptString
            // por debajo); se desencripta explícito acá porque esta consulta es DB::table() cruda,
            // sin pasar por el cast del modelo Eloquent.
            $plainPassword = $job->db_password ? Crypt::decryptString($job->db_password) : null;

            $connection = DatabaseConnection::create([
                'name' => $job->name.' (migrado)',
                'engine' => 'mysql',
                'host' => $job->db_host,
                'port' => $job->db_port ?? 3306,
                'username' => $job->db_username,
                'password' => $plainPassword,
            ]);

            DB::table('backup_jobs')->where('id', $job->id)->update([
                'database_connection_id' => $connection->id,
            ]);
        }

        $backfilled = DB::table('backup_jobs')->where('type', 'database')->whereNotNull('database_connection_id')->count();
        $expected = DB::table('backup_jobs')->where('type', 'database')->count();
        abort_unless($backfilled === $expected, 500, "Backfill de database_connection_id incompleto: {$backfilled}/{$expected}");

        Schema::table('backup_jobs', function (Blueprint $table) {
            $table->dropColumn(['db_host', 'db_port', 'db_username', 'db_password']);
        });
    }

    public function down(): void
    {
        Schema::table('backup_jobs', function (Blueprint $table) {
            $table->string('db_host')->nullable();
            $table->unsignedSmallInteger('db_port')->nullable()->default(3306);
            $table->string('db_username')->nullable();
            $table->text('db_password')->nullable();
        });

        foreach (DB::table('backup_jobs')->whereNotNull('database_connection_id')->get() as $job) {
            $connection = DB::table('database_connections')->find($job->database_connection_id);
            if ($connection) {
                DB::table('backup_jobs')->where('id', $job->id)->update([
                    'db_host' => $connection->host,
                    'db_port' => $connection->port,
                    'db_username' => $connection->username,
                    'db_password' => $connection->password,
                ]);
            }
        }

        Schema::table('backup_jobs', function (Blueprint $table) {
            $table->dropForeign(['database_connection_id']);
            $table->dropColumn(['database_connection_id', 'tables', 'schema_only']);
        });
    }
};
