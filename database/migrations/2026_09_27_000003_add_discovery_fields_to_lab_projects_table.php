<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_projects', function (Blueprint $table) {
            // Contenedor al que corresponde la tarjeta (se cruza por sección + nombre).
            $table->string('container', 150)->nullable()->after('monitor_only');
            // true = la creó el descubrimiento automático; false = la creó darius a mano.
            $table->boolean('discovered')->default(false)->after('container');
            // Oculta sin borrar: si se borrara, la siguiente sincronización la volvería a crear.
            $table->boolean('hidden')->default(false)->after('discovered');
            $table->string('container_state', 30)->nullable()->after('hidden');
            $table->string('container_health', 20)->nullable()->after('container_state');
            $table->string('container_status', 150)->nullable()->after('container_health');
            $table->timestamp('last_seen_at')->nullable()->after('container_status');
        });
    }

    public function down(): void
    {
        Schema::table('lab_projects', function (Blueprint $table) {
            $table->dropColumn(['container', 'discovered', 'hidden', 'container_state', 'container_health', 'container_status', 'last_seen_at']);
        });
    }
};
