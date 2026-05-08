<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usergroups', function (Blueprint $table) {
            $table->uuid('usergroup_id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 100);
            $table->text('deskripsi')->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamp('dibuat_pada')->nullable();
            $table->string('dibuat_oleh_user_id', 36)->nullable();
            $table->timestamp('diubah_pada')->nullable();
            $table->string('diubah_oleh_user_id', 36)->nullable();
            $table->timestamp('dihapus_pada')->nullable();
            $table->string('dihapus_oleh_user_id', 36)->nullable();

            $table->index(['kode', 'status_aktif']);
        });

        $now = Carbon::now();
        $defaultGroups = [
            'admin' => [
                'usergroup_id' => (string) Str::uuid(),
                'kode' => 'admin',
                'nama' => 'Administrator',
                'deskripsi' => 'Akses penuh untuk administrasi sistem.',
            ],
            'dosen' => [
                'usergroup_id' => (string) Str::uuid(),
                'kode' => 'dosen',
                'nama' => 'Dosen',
                'deskripsi' => 'Akses dosen untuk workflow visitasi dan simulasi.',
            ],
            'mahasiswa' => [
                'usergroup_id' => (string) Str::uuid(),
                'kode' => 'mahasiswa',
                'nama' => 'Mahasiswa',
                'deskripsi' => 'Akses mahasiswa untuk data pribadi dan konteks domisili.',
            ],
        ];

        DB::table('usergroups')->insert(array_map(function (array $group) use ($now) {
            return [
                'usergroup_id' => $group['usergroup_id'],
                'kode' => $group['kode'],
                'nama' => $group['nama'],
                'deskripsi' => $group['deskripsi'],
                'status_aktif' => true,
                'dibuat_pada' => $now,
                'dibuat_oleh_user_id' => null,
                'diubah_pada' => $now,
                'diubah_oleh_user_id' => null,
                'dihapus_pada' => null,
                'dihapus_oleh_user_id' => null,
            ];
        }, $defaultGroups));

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('usergroup_id')->nullable();
            $table->index(['usergroup_id', 'status_aktif']);
        });

        foreach ($defaultGroups as $legacyRole => $group) {
            DB::table('users')
                ->where('role', $legacyRole)
                ->update(['usergroup_id' => $group['usergroup_id']]);
        }

        DB::statement('ALTER TABLE users ALTER COLUMN usergroup_id SET NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('usergroup_id')
                ->references('usergroup_id')
                ->on('usergroups')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->nullable();
        });

        DB::statement(
            'UPDATE users SET role = usergroups.kode FROM usergroups WHERE users.usergroup_id = usergroups.usergroup_id'
        );
        DB::statement("UPDATE users SET role = 'mahasiswa' WHERE role IS NULL");
        DB::statement('ALTER TABLE users ALTER COLUMN role SET NOT NULL');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'dosen', 'mahasiswa'))");

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['usergroup_id']);
            $table->dropIndex(['usergroup_id', 'status_aktif']);
            $table->dropColumn('usergroup_id');
        });

        Schema::dropIfExists('usergroups');
    }
};
