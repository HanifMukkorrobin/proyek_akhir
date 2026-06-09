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
        Schema::create('visitasi_rencana', function (Blueprint $table) {
            $table->uuid('visitasi_rencana_id')->primary();
            $table->uuid('dosen_id');
            $table->uuid('dibuat_oleh_user_id')->nullable();
            $table->string('nama_rencana', 255);
            $table->text('deskripsi')->nullable();
            $table->decimal('titik_awal_latitude', 10, 8)->nullable();
            $table->decimal('titik_awal_longitude', 11, 8)->nullable();
            $table->string('titik_awal_label', 255)->nullable();
            $table->string('jenis_kendaraan', 50)->default('motor');
            $table->boolean('lewat_tol')->default(false);
            $table->decimal('perkiraan_total_jarak_km', 10, 2)->nullable();
            $table->integer('perkiraan_total_menit')->nullable();
            $table->string('status', 50)->default('draft');
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diubah_pada')->nullable();
            $table->timestamp('dihapus_pada')->nullable();

            $table->foreign('dosen_id')
                ->references('user_id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('dibuat_oleh_user_id')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['dosen_id', 'status']);
            $table->index(['dosen_id', 'dibuat_pada']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitasi_rencana');
    }
};
