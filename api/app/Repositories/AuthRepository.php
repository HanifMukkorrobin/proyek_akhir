<?php

namespace App\Repositories;

use App\Models\AuthToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AuthRepository
{
    public function login(string $identifier, string $password, ?string $ipAddress = null, ?string $userAgent = null, bool $rememberMe = false): array
    {
        $normalizedIdentifier = trim($identifier);

        if ($normalizedIdentifier === '') {
            throw new InvalidArgumentException('Username atau email wajib diisi.');
        }

        $user = User::query()
            ->with('usergroup')
            ->where(function ($builder) use ($normalizedIdentifier) {
                $builder
                    ->whereRaw('LOWER(username) = LOWER(?)', [$normalizedIdentifier])
                    ->orWhereRaw('LOWER(email) = LOWER(?)', [$normalizedIdentifier]);
            })
            ->where('status_aktif', true)
            ->whereHas('usergroup', function ($builder) {
                $builder->where('status_aktif', true);
            })
            ->first();

        if ($user === null || !Hash::check($password, (string) $user->password)) {
            throw new InvalidArgumentException('Kredensial login tidak valid.');
        }

        $plainToken = $this->generatePlainToken();
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = $this->resolveTokenExpiry($rememberMe);

        AuthToken::query()->create([
            'token_id' => (string) Str::uuid(),
            'user_id' => $user->user_id,
            'token_hash' => $tokenHash,
            'token_prefix' => substr($plainToken, 0, 12),
            'ip_address' => $this->normalizeNullableString($ipAddress),
            'user_agent' => $this->normalizeNullableString($userAgent),
            'kedaluwarsa_pada' => $expiresAt,
            'revoked_pada' => null,
        ]);

        $user->last_login_pada = Carbon::now();
        $user->save();

        return [
            'access_token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt !== null ? $expiresAt->toDateTimeString() : null,
            'user' => [
                'user_id' => $user->user_id,
                'nama' => $user->nama,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->usergroup?->kode,
                'usergroup_id' => $user->usergroup_id,
                'usergroup' => $user->usergroup !== null ? [
                    'usergroup_id' => $user->usergroup->usergroup_id,
                    'kode' => $user->usergroup->kode,
                    'nama' => $user->usergroup->nama,
                    'status_aktif' => (bool) $user->usergroup->status_aktif,
                ] : null,
                'mahasiswa_id' => $user->mahasiswa_id,
                'status_aktif' => (bool) $user->status_aktif,
                'last_login_pada' => $user->last_login_pada,
            ],
        ];
    }

    private function generatePlainToken(): string
    {
        return sprintf('pta_%s%s', Str::lower(Str::random(20)), Str::lower(Str::random(40)));
    }

    private function resolveTokenExpiry(bool $rememberMe)
    {
        $minutes = $rememberMe
            ? (int) env('AUTH_TOKEN_REMEMBER_TTL_MINUTES', 43200)
            : (int) env('AUTH_TOKEN_TTL_MINUTES', 1440);

        if ($minutes <= 0) {
            return null;
        }

        return Carbon::now()->addMinutes($minutes);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        return $normalized;
    }
}
