<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_diff_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_a_id')->constrained('database_connections')->cascadeOnDelete();
            $table->string('db_a');
            $table->foreignId('connection_b_id')->constrained('database_connections')->cascadeOnDelete();
            $table->string('db_b');
            $table->string('section'); // tables|views|procedures|functions|data
            $table->string('source_side'); // a|b: cuál lado es la fuente de la verdad
            $table->string('status')->default('pending'); // pending|running|success|failed
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_diff_syncs');
    }
};
