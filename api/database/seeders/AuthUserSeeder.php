<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $existingUsernames = DB::table('users')
            ->whereIn('username', ['admin', 'dosen', 'mahasiswa'])
            ->pluck('username')
            ->all();

        $userGroups = $this->ensureDefaultUserGroups();

        $mahasiswaId = DB::table('mahasiswa')
            ->orderBy('mahasiswa_id')
            ->value('mahasiswa_id');

        $now = Carbon::now();
        $defaultPassword = 'P@ssw0rd';
        $passwordHash = Hash::make($defaultPassword);
        $rows = [];

        if (!empty($existingUsernames)) {
            DB::table('users')
                ->whereIn('username', $existingUsernames)
                ->update([
                    'password' => $passwordHash,
                    'diubah_pada' => $now,
                    'diubah_oleh_user_id' => null,
                ]);
        }

        if (!in_array('admin', $existingUsernames, true)) {
            $rows[] = [
                'user_id' => (string) Str::uuid(),
                'nama' => 'Administrator',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => $passwordHash,
                'usergroup_id' => $userGroups['admin'],
                'mahasiswa_id' => null,
                'status_aktif' => true,
                'last_login_pada' => null,
                'dibuat_pada' => $now,
                'dibuat_oleh_user_id' => null,
                'diubah_pada' => $now,
                'diubah_oleh_user_id' => null,
                'dihapus_pada' => null,
                'dihapus_oleh_user_id' => null,
            ];
        }

        if (!in_array('dosen', $existingUsernames, true)) {
            $rows[] = [
                'user_id' => (string) Str::uuid(),
                'nama' => 'Dosen Demo',
                'username' => 'dosen',
                'email' => 'dosen@example.com',
                'password' => $passwordHash,
                'usergroup_id' => $userGroups['dosen'],
                'mahasiswa_id' => null,
                'status_aktif' => true,
                'last_login_pada' => null,
                'dibuat_pada' => $now,
                'dibuat_oleh_user_id' => null,
                'diubah_pada' => $now,
                'diubah_oleh_user_id' => null,
                'dihapus_pada' => null,
                'dihapus_oleh_user_id' => null,
            ];
        }

        if (!in_array('mahasiswa', $existingUsernames, true)) {
            $rows[] = [
                'user_id' => (string) Str::uuid(),
                'nama' => 'Mahasiswa Demo',
                'username' => 'mahasiswa',
                'email' => 'mahasiswa@example.com',
                'password' => $passwordHash,
                'usergroup_id' => $userGroups['mahasiswa'],
                'mahasiswa_id' => $mahasiswaId,
                'status_aktif' => true,
                'last_login_pada' => null,
                'dibuat_pada' => $now,
                'dibuat_oleh_user_id' => null,
                'diubah_pada' => $now,
                'diubah_oleh_user_id' => null,
                'dihapus_pada' => null,
                'dihapus_oleh_user_id' => null,
            ];
        }

        if (!empty($rows)) {
            DB::table('users')->insert($rows);
        }
    }

    private function ensureDefaultUserGroups(): array
    {
        $existing = DB::table('usergroups')
            ->whereIn('kode', ['admin', 'dosen', 'mahasiswa'])
            ->pluck('usergroup_id', 'kode')
            ->all();

        $now = Carbon::now();
        $defaults = [
            'admin' => [
                'nama' => 'Administrator',
                'deskripsi' => 'Akses penuh untuk administrasi sistem.',
            ],
            'dosen' => [
                'nama' => 'Dosen',
                'deskripsi' => 'Akses dosen untuk workflow visitasi dan simulasi.',
            ],
            'mahasiswa' => [
                'nama' => 'Mahasiswa',
                'deskripsi' => 'Akses mahasiswa untuk data pribadi dan konteks domisili.',
            ],
        ];

        $rows = [];

        foreach ($defaults as $kode => $definition) {
            if (isset($existing[$kode])) {
                continue;
            }

            $rows[] = [
                'usergroup_id' => (string) Str::uuid(),
                'kode' => $kode,
                'nama' => $definition['nama'],
                'deskripsi' => $definition['deskripsi'],
                'status_aktif' => true,
                'dibuat_pada' => $now,
                'dibuat_oleh_user_id' => null,
                'diubah_pada' => $now,
                'diubah_oleh_user_id' => null,
                'dihapus_pada' => null,
                'dihapus_oleh_user_id' => null,
            ];
        }

        if (!empty($rows)) {
            DB::table('usergroups')->insert($rows);
        }

        return DB::table('usergroups')
            ->whereIn('kode', ['admin', 'dosen', 'mahasiswa'])
            ->pluck('usergroup_id', 'kode')
            ->all();
    }
}
