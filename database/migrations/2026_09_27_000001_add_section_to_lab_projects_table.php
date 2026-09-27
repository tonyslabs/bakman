<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_projects', function (Blueprint $table) {
            // Agrupa las tarjetas (p. ej. por máquina). Nullable: los proyectos existentes quedan "Sin sección".
            $table->string('section', 100)->nullable()->after('module');
        });
    }

    public function down(): void
    {
        Schema::table('lab_projects', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }
};
