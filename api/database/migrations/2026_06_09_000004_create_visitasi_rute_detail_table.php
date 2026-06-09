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
        Schema::create('visitasi_rute_detail', function (Blueprint $table) {
            $table->uuid('visitasi_rute_detail_id')->primary();
            $table->uuid('visitasi_rute_id');
            $table->uuid('visitasi_rencana_id');
            $table->uuid('visitasi_peserta_id')->nullable(); // NULL = titik_awal atau kembali
            $table->string('tipe_titik', 50)->default('mahasiswa');
            $table->integer('urutan_kunjungan');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('label', 255)->nullable();
            $table->integer('estimasi_ke_sini_menit')->default(0);
            $table->decimal('jarak_dari_sebelumnya_km', 10, 2)->default(0);
            $table->integer('estimasi_kumulatif_menit')->default(0);
            $table->text('geometri_polyline')->nullable(); // encoded polyline segmen dari OSRM

            $table->foreign('visitasi_rute_id')
                ->references('visitasi_rute_id')
                ->on('visitasi_rute')
                ->cascadeOnDelete();

            $table->foreign('visitasi_rencana_id')
                ->references('visitasi_rencana_id')
                ->on('visitasi_rencana')
                ->cascadeOnDelete();

            $table->foreign('visitasi_peserta_id')
                ->references('visitasi_peserta_id')
                ->on('visitasi_peserta')
                ->nullOnDelete();

            $table->index(['visitasi_rute_id', 'urutan_kunjungan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitasi_rute_detail');
    }
};
