<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visitasi_rencana')) {
            Schema::create('visitasi_rencana', function (Blueprint $table) {
                $table->uuid('visitasi_rencana_id')->primary();
                $table->string('nama', 255);
                $table->uuid('dosen_user_id')->nullable();
                $table->string('titik_awal_nama', 255);
                $table->decimal('titik_awal_latitude', 10, 7);
                $table->decimal('titik_awal_longitude', 10, 7);
                $table->string('titik_akhir_nama', 255)->nullable();
                $table->decimal('titik_akhir_latitude', 10, 7)->nullable();
                $table->decimal('titik_akhir_longitude', 10, 7)->nullable();
                $table->string('profile', 50)->default('driving');
                $table->string('kendaraan', 50)->default('mobil');
                $table->boolean('optimize_order')->default(true);
                $table->string('status', 50)->default('simulated');
                $table->text('catatan')->nullable();
                $table->timestamp('dibuat_pada')->nullable();
                $table->string('dibuat_oleh_user_id', 36)->nullable();
                $table->timestamp('diubah_pada')->nullable();
                $table->string('diubah_oleh_user_id', 36)->nullable();
                $table->timestamp('dihapus_pada')->nullable();
                $table->string('dihapus_oleh_user_id', 36)->nullable();

                $table->foreign('dosen_user_id')
                    ->references('user_id')
                    ->on('users')
                    ->nullOnDelete();
                $table->index(['dosen_user_id', 'status']);
            });
        }

        if (!Schema::hasTable('visitasi_peserta')) {
            Schema::create('visitasi_peserta', function (Blueprint $table) {
                $table->uuid('visitasi_peserta_id')->primary();
                $table->uuid('visitasi_rencana_id');
                $table->string('mahasiswa_id');
                $table->unsignedInteger('urutan_input');
                $table->unsignedInteger('urutan_rute')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->string('status_lokasi', 100)->nullable();
                $table->timestamp('dibuat_pada')->nullable();
                $table->timestamp('diubah_pada')->nullable();

                $table->foreign('visitasi_rencana_id')
                    ->references('visitasi_rencana_id')
                    ->on('visitasi_rencana')
                    ->cascadeOnDelete();
                $table->foreign('mahasiswa_id')
                    ->references('mahasiswa_id')
                    ->on('mahasiswa')
                    ->restrictOnDelete();
                $table->unique(['visitasi_rencana_id', 'mahasiswa_id']);
                $table->index(['visitasi_rencana_id', 'urutan_rute']);
            });
        }

        if (!Schema::hasTable('visitasi_rute')) {
            Schema::create('visitasi_rute', function (Blueprint $table) {
                $table->uuid('visitasi_rute_id')->primary();
                $table->uuid('visitasi_rencana_id');
                $table->string('provider', 50)->default('osrm');
                $table->string('service', 30)->default('trip');
                $table->string('profile', 50)->default('driving');
                $table->decimal('distance_meters', 14, 2)->nullable();
                $table->decimal('duration_seconds', 14, 2)->nullable();
                $table->decimal('weight', 14, 2)->nullable();
                $table->json('geometry')->nullable();
                $table->json('legs')->nullable();
                $table->json('waypoints')->nullable();
                $table->json('osrm_response')->nullable();
                $table->string('status', 50)->default('success');
                $table->text('error_message')->nullable();
                $table->timestamp('dibuat_pada')->nullable();
                $table->timestamp('diubah_pada')->nullable();

                $table->foreign('visitasi_rencana_id')
                    ->references('visitasi_rencana_id')
                    ->on('visitasi_rencana')
                    ->cascadeOnDelete();
                $table->index(['visitasi_rencana_id', 'provider', 'service']);
            });
        }

        if (!Schema::hasTable('visitasi_rute_detail')) {
            Schema::create('visitasi_rute_detail', function (Blueprint $table) {
                $table->uuid('visitasi_rute_detail_id')->primary();
                $table->uuid('visitasi_rute_id');
                $table->uuid('visitasi_peserta_id')->nullable();
                $table->string('mahasiswa_id')->nullable();
                $table->unsignedInteger('urutan');
                $table->string('tipe', 30);
                $table->string('nama', 255);
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->unsignedInteger('leg_index')->nullable();
                $table->decimal('distance_meters', 14, 2)->nullable();
                $table->decimal('duration_seconds', 14, 2)->nullable();
                $table->json('steps')->nullable();
                $table->timestamp('dibuat_pada')->nullable();
                $table->timestamp('diubah_pada')->nullable();

                $table->foreign('visitasi_rute_id')
                    ->references('visitasi_rute_id')
                    ->on('visitasi_rute')
                    ->cascadeOnDelete();
                $table->foreign('visitasi_peserta_id')
                    ->references('visitasi_peserta_id')
                    ->on('visitasi_peserta')
                    ->nullOnDelete();
                $table->foreign('mahasiswa_id')
                    ->references('mahasiswa_id')
                    ->on('mahasiswa')
                    ->nullOnDelete();
                $table->index(['visitasi_rute_id', 'urutan']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visitasi_rute_detail');
        Schema::dropIfExists('visitasi_rute');
        Schema::dropIfExists('visitasi_peserta');
        Schema::dropIfExists('visitasi_rencana');
    }
};
