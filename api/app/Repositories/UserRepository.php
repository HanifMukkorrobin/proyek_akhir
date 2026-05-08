<?php

namespace App\Repositories;

use App\Models\AuthToken;
use App\Models\Mahasiswa;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UserRepository
{
    public function paginate(array $filters = []): array
    {
        $query = User::query()
            ->with(['usergroup', 'mahasiswa'])
            ->orderByDesc('dibuat_pada')
            ->orderBy('nama');

        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('nama', 'ILIKE', '%' . $search . '%')
                    ->orWhere('username', 'ILIKE', '%' . $search . '%')
                    ->orWhere('email', 'ILIKE', '%' . $search . '%')
                    ->orWhereHas('usergroup', function (Builder $groupQuery) use ($search) {
                        $groupQuery
                            ->where('kode', 'ILIKE', '%' . $search . '%')
                            ->orWhere('nama', 'ILIKE', '%' . $search . '%');
                    });
            });
        }

        if (array_key_exists('status_aktif', $filters)) {
            $statusAktif = filter_var($filters['status_aktif'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($statusAktif !== null) {
                $query->where('status_aktif', $statusAktif);
            }
        }

        if (!empty($filters['usergroup_id'])) {
            $query->where('usergroup_id', (string) $filters['usergroup_id']);
        }

        $usergroupKode = trim((string) ($filters['usergroup_kode'] ?? $filters['role'] ?? ''));

        if ($usergroupKode !== '') {
            $query->whereHas('usergroup', function (Builder $groupQuery) use ($usergroupKode) {
                $groupQuery->whereRaw('LOWER(kode) = LOWER(?)', [$usergroupKode]);
            });
        }

        if (!empty($filters['mahasiswa_id'])) {
            $query->where('mahasiswa_id', (string) $filters['mahasiswa_id']);
        }

        $this->applySorting($query, $filters);

        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 10);

        $pagination = paginate_builder($query, $page, $perPage);

        return [
            'data' => array_map(function (User $user) {
                return $this->transform($user);
            }, $pagination['data']->all()),
            'halaman_sekarang' => $pagination['halaman_sekarang'],
            'per_halaman' => $pagination['per_halaman'],
            'total_data' => $pagination['total_data'],
            'total_halaman' => $pagination['total_halaman'],
        ];
    }

    public function find(string $userId): ?array
    {
        $user = User::query()
            ->with(['usergroup', 'mahasiswa'])
            ->where('user_id', $userId)
            ->first();

        if ($user === null) {
            return null;
        }

        return $this->transform($user);
    }

    public function create(array $payload, ?string $actorUserId = null): array
    {
        $this->ensureUniqueUsername((string) $payload['username']);
        $this->ensureUniqueEmail($payload['email'] ?? null);

        $usergroup = $this->resolveUserGroup($payload);
        $mahasiswaId = $this->resolveMahasiswaId($payload['mahasiswa_id'] ?? null);
        $statusAktif = $this->resolveBoolean($payload['status_aktif'] ?? null, true);

        $user = User::query()->create([
            'user_id' => (string) Str::uuid(),
            'nama' => trim((string) $payload['nama']),
            'username' => trim((string) $payload['username']),
            'email' => $this->normalizeNullableString($payload['email'] ?? null),
            'password' => Hash::make((string) $payload['password']),
            'usergroup_id' => $usergroup->usergroup_id,
            'mahasiswa_id' => $mahasiswaId,
            'status_aktif' => $statusAktif,
            'last_login_pada' => null,
            'dibuat_oleh_user_id' => $actorUserId,
            'diubah_oleh_user_id' => $actorUserId,
        ]);

        $user->load(['usergroup', 'mahasiswa']);

        return $this->transform($user);
    }

    public function update(string $userId, array $payload, ?string $actorUserId = null): ?array
    {
        $user = User::query()
            ->where('user_id', $userId)
            ->first();

        if ($user === null) {
            return null;
        }

        if (array_key_exists('username', $payload)) {
            $username = trim((string) $payload['username']);
            $this->ensureUniqueUsername($username, $userId);
            $user->username = $username;
        }

        if (array_key_exists('email', $payload)) {
            $email = $this->normalizeNullableString($payload['email']);
            $this->ensureUniqueEmail($email, $userId);
            $user->email = $email;
        }

        if (array_key_exists('nama', $payload)) {
            $user->nama = trim((string) $payload['nama']);
        }

        if ($this->hasUserGroupPayload($payload)) {
            $user->usergroup_id = $this->resolveUserGroup($payload)->usergroup_id;
        }

        if (array_key_exists('mahasiswa_id', $payload)) {
            $user->mahasiswa_id = $this->resolveMahasiswaId($payload['mahasiswa_id']);
        }

        if (array_key_exists('status_aktif', $payload)) {
            $user->status_aktif = $this->resolveBoolean($payload['status_aktif'], (bool) $user->status_aktif);
        }

        $user->diubah_oleh_user_id = $actorUserId;
        $user->save();
        $user->load(['usergroup', 'mahasiswa']);

        return $this->transform($user);
    }

    public function delete(string $userId, ?string $actorUserId = null): bool
    {
        $user = User::query()
            ->where('user_id', $userId)
            ->first();

        if ($user === null) {
            return false;
        }

        $user->dihapus_oleh_user_id = $actorUserId;
        $user->save();
        $user->delete();

        AuthToken::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_pada')
            ->update([
                'revoked_pada' => Carbon::now(),
                'diubah_pada' => Carbon::now(),
            ]);

        return true;
    }

    public function resetPassword(string $userId, ?string $newPassword = null, bool $revokeTokens = true, ?string $actorUserId = null): ?array
    {
        $user = User::query()
            ->where('user_id', $userId)
            ->first();

        if ($user === null) {
            return null;
        }

        $generated = false;
        $plainPassword = $newPassword;

        if ($plainPassword === null || trim($plainPassword) === '') {
            $plainPassword = sprintf('Gv%s!%d', Str::random(10), random_int(10, 99));
            $generated = true;
        }

        $user->password = Hash::make($plainPassword);
        $user->diubah_oleh_user_id = $actorUserId;
        $user->save();

        if ($revokeTokens) {
            AuthToken::query()
                ->where('user_id', $userId)
                ->whereNull('revoked_pada')
                ->update([
                    'revoked_pada' => Carbon::now(),
                    'diubah_pada' => Carbon::now(),
                ]);
        }

        $user->load(['usergroup', 'mahasiswa']);

        $result = [
            'user' => $this->transform($user),
            'password_generated' => $generated,
            'tokens_revoked' => $revokeTokens,
        ];

        if ($generated) {
            $result['temporary_password'] = $plainPassword;
        }

        return $result;
    }

    public function transform(User $user): array
    {
        $usergroup = $user->usergroup;
        $mahasiswa = $user->mahasiswa;

        return [
            'user_id' => $user->user_id,
            'nama' => $user->nama,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $usergroup?->kode,
            'usergroup_id' => $user->usergroup_id,
            'usergroup' => $usergroup !== null ? [
                'usergroup_id' => $usergroup->usergroup_id,
                'kode' => $usergroup->kode,
                'nama' => $usergroup->nama,
                'deskripsi' => $usergroup->deskripsi,
                'status_aktif' => (bool) $usergroup->status_aktif,
            ] : null,
            'mahasiswa_id' => $user->mahasiswa_id,
            'mahasiswa' => $mahasiswa !== null ? [
                'mahasiswa_id' => $mahasiswa->mahasiswa_id,
                'nama' => $mahasiswa->nama,
                'alamat' => $mahasiswa->alamat,
            ] : null,
            'status_aktif' => (bool) $user->status_aktif,
            'last_login_pada' => $user->last_login_pada,
            'dibuat_pada' => $user->dibuat_pada,
            'dibuat_oleh_user_id' => $user->dibuat_oleh_user_id,
            'diubah_pada' => $user->diubah_pada,
            'diubah_oleh_user_id' => $user->diubah_oleh_user_id,
        ];
    }

    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = (string) ($filters['sort_by'] ?? '');
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['nama', 'username', 'email', 'last_login_pada', 'dibuat_pada'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            return;
        }

        $query->reorder($sortBy, $sortDir)->orderBy('nama');
    }

    private function resolveUserGroup(array $payload): UserGroup
    {
        if (!empty($payload['usergroup_id'])) {
            $usergroup = UserGroup::query()
                ->where('usergroup_id', (string) $payload['usergroup_id'])
                ->where('status_aktif', true)
                ->first();

            if ($usergroup !== null) {
                return $usergroup;
            }
        }

        $kode = trim((string) ($payload['usergroup_kode'] ?? $payload['role'] ?? ''));

        if ($kode !== '') {
            $usergroup = UserGroup::query()
                ->whereRaw('LOWER(kode) = LOWER(?)', [$kode])
                ->where('status_aktif', true)
                ->first();

            if ($usergroup !== null) {
                return $usergroup;
            }
        }

        throw new InvalidArgumentException('Usergroup tidak ditemukan atau tidak aktif.');
    }

    private function hasUserGroupPayload(array $payload): bool
    {
        return array_key_exists('usergroup_id', $payload)
            || array_key_exists('usergroup_kode', $payload)
            || array_key_exists('role', $payload);
    }

    private function resolveMahasiswaId($value): ?string
    {
        $mahasiswaId = $this->normalizeNullableString($value);

        if ($mahasiswaId === null) {
            return null;
        }

        $exists = Mahasiswa::query()
            ->withTrashed()
            ->where('mahasiswa_id', $mahasiswaId)
            ->exists();

        if (!$exists) {
            throw new InvalidArgumentException('Mahasiswa tidak ditemukan.');
        }

        return $mahasiswaId;
    }

    private function ensureUniqueUsername(string $username, ?string $ignoreUserId = null): void
    {
        $exists = User::query()
            ->whereRaw('LOWER(username) = LOWER(?)', [$username])
            ->when($ignoreUserId !== null, function (Builder $query) use ($ignoreUserId) {
                $query->where('user_id', '!=', $ignoreUserId);
            })
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Username sudah digunakan.');
        }
    }

    private function ensureUniqueEmail($email, ?string $ignoreUserId = null): void
    {
        $normalizedEmail = $this->normalizeNullableString($email);

        if ($normalizedEmail === null) {
            return;
        }

        $exists = User::query()
            ->whereRaw('LOWER(email) = LOWER(?)', [$normalizedEmail])
            ->when($ignoreUserId !== null, function (Builder $query) use ($ignoreUserId) {
                $query->where('user_id', '!=', $ignoreUserId);
            })
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Email sudah digunakan.');
        }
    }

    private function resolveBoolean($value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($resolved === null) {
            return $default;
        }

        return $resolved;
    }

    private function normalizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return $normalized;
    }
}
