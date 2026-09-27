<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_projects', function (Blueprint $table) {
            $table->id();
            $table->string('module')->index();
            $table->string('name');
            $table->string('scheme')->default('http');
            $table->string('host');
            $table->unsignedInteger('port');
            $table->string('path')->nullable();
            $table->text('description')->nullable();
            $table->string('color')->default('red');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_projects');
    }
};
