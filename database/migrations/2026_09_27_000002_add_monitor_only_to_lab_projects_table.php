<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_projects', function (Blueprint $table) {
            // Servicios sin interfaz web (p. ej. agentes de Portainer): se muestra su estado, no se enlazan.
            $table->boolean('monitor_only')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('lab_projects', function (Blueprint $table) {
            $table->dropColumn('monitor_only');
        });
    }
};
