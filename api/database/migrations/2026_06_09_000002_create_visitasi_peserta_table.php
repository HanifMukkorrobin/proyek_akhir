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
        Schema::create('visitasi_peserta', function (Blueprint $table) {
            $table->uuid('visitasi_peserta_id')->primary();
            $table->uuid('visitasi_rencana_id');
            $table->string('mahasiswa_id', 255);
            $table->integer('prioritas')->default(0);
            $table->integer('urutan')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('dihapus_pada')->nullable();

            $table->foreign('visitasi_rencana_id')
                ->references('visitasi_rencana_id')
                ->on('visitasi_rencana')
                ->cascadeOnDelete();

            $table->foreign('mahasiswa_id')
                ->references('mahasiswa_id')
                ->on('mahasiswa')
                ->restrictOnDelete();

            // Cegah duplikat mahasiswa dalam satu rencana (diabaikan jika soft-deleted)
            $table->unique(['visitasi_rencana_id', 'mahasiswa_id']);

            $table->index(['visitasi_rencana_id', 'dihapus_pada']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitasi_peserta');
    }
};
