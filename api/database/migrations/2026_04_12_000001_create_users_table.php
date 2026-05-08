<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->string('nama', 255);
            $table->string('username', 100)->unique();
            $table->string('email', 255)->nullable()->unique();
            $table->string('password', 255);
            $table->string('role', 30);
            $table->string('mahasiswa_id')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamp('last_login_pada')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->string('dibuat_oleh_user_id', 36)->nullable();
            $table->timestamp('diubah_pada')->nullable();
            $table->string('diubah_oleh_user_id', 36)->nullable();
            $table->timestamp('dihapus_pada')->nullable();
            $table->string('dihapus_oleh_user_id', 36)->nullable();

            $table->foreign('mahasiswa_id')
                ->references('mahasiswa_id')
                ->on('mahasiswa')
                ->nullOnDelete();

            $table->index(['role', 'status_aktif']);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'dosen', 'mahasiswa'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
