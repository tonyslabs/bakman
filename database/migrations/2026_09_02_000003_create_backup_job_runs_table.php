<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_job_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_job_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('output_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_job_runs');
    }
};
