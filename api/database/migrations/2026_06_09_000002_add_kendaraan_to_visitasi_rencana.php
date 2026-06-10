<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visitasi_rencana') || Schema::hasColumn('visitasi_rencana', 'kendaraan')) {
            return;
        }

        Schema::table('visitasi_rencana', function (Blueprint $table) {
            $table->string('kendaraan', 50)->default('mobil');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('visitasi_rencana') || !Schema::hasColumn('visitasi_rencana', 'kendaraan')) {
            return;
        }

        Schema::table('visitasi_rencana', function (Blueprint $table) {
            $table->dropColumn('kendaraan');
        });
    }
};
