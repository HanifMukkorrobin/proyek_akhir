<?php

if (!function_exists('resolve_request_token')) {
    function resolve_request_token($request): ?string
    {
        $authorizationHeader = trim((string) $request->header('Authorization', ''));

        if ($authorizationHeader !== ''
            && preg_match('/^Bearer\s+(.+)$/i', $authorizationHeader, $matches) === 1
            && isset($matches[1])) {
            $token = trim((string) $matches[1]);

            if ($token !== '') {
                return $token;
            }
        }

        $tokenHeader = trim((string) $request->header('token', ''));

        if ($tokenHeader !== '') {
            return $tokenHeader;
        }

        return null;
    }
}

if (!function_exists('validate_login_token')) {
    function validate_login_token(?string $plainToken): ?array
    {
        if ($plainToken === null || trim($plainToken) === '') {
            return null;
        }

        $tokenHash = hash('sha256', trim($plainToken));

        $authToken = \App\Models\AuthToken::query()
            ->where('token_hash', $tokenHash)
            ->first();

        if ($authToken === null) {
            return null;
        }

        if ($authToken->revoked_pada !== null) {
            return null;
        }

        $expiresAt = $authToken->kedaluwarsa_pada;

        if ($expiresAt !== null && \Carbon\Carbon::parse($expiresAt)->isPast()) {
            return null;
        }

        $user = \App\Models\User::query()
            ->with('usergroup')
            ->where('user_id', $authToken->user_id)
            ->where('status_aktif', true)
            ->whereHas('usergroup', function ($builder) {
                $builder->where('status_aktif', true);
            })
            ->first();

        if ($user === null) {
            return null;
        }

        return [
            'user' => $user,
            'auth_token' => $authToken,
        ];
    }
}
