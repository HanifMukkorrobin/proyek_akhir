<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visitasi_rute', function (Blueprint $table) {
            $table->uuid('visitasi_rute_id')->primary();
            $table->uuid('visitasi_rencana_id');
            $table->string('metode_kalkulasi', 100)->default('osrm_nearest_neighbor');
            $table->string('osrm_profile', 50)->nullable();
            $table->decimal('total_jarak_km', 10, 2)->nullable();
            $table->integer('total_estimasi_menit')->nullable();
            $table->json('parameter_input')->nullable();
            $table->json('hasil_osrm_raw')->nullable();
            $table->string('status', 50)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('dibuat_pada')->nullable();

            $table->foreign('visitasi_rencana_id')
                ->references('visitasi_rencana_id')
                ->on('visitasi_rencana')
                ->cascadeOnDelete();

            $table->index(['visitasi_rencana_id', 'dibuat_pada']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitasi_rute');
    }
};
