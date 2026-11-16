<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_disks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path');
            $table->timestamps();
        });

        // Instalaciones previas tenían un solo disco fijo (FILES_ROOT): se conserva como el primero.
        DB::table('storage_disks')->insert([
            'name' => 'Disco externo',
            'path' => config('backups.browse_root'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_disks');
    }
};
