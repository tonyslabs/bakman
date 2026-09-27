<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_migrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_connection_id')->constrained('database_connections')->cascadeOnDelete();
            $table->string('source_db_name');
            $table->foreignId('target_connection_id')->constrained('database_connections')->cascadeOnDelete();
            $table->string('target_db_name');
            $table->json('tables')->nullable();
            $table->string('status')->default('pending'); // pending|running|success|failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_migrations');
    }
};
