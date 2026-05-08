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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('username', 100)->nullable();
            $table->string('nama_user', 255)->nullable();
            $table->string('usergroup_kode', 30)->nullable();
            $table->string('modul', 80);
            $table->string('aksi', 80);
            $table->string('target_tipe', 80)->nullable();
            $table->string('target_id', 255)->nullable();
            $table->string('status', 20);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('path', 255)->nullable();
            $table->text('deskripsi')->nullable();
            $table->text('response_message')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('dibuat_pada')->nullable();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['modul', 'aksi']);
            $table->index(['status', 'dibuat_pada']);
            $table->index(['user_id', 'dibuat_pada']);
            $table->index('target_id');
            $table->index('dibuat_pada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
