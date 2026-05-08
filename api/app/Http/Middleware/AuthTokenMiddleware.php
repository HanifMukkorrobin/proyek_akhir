<?php

namespace App\Http\Middleware;

use Closure;

class AuthTokenMiddleware
{
    public function handle($request, Closure $next)
    {
        $plainToken = resolve_request_token($request);

        if ($plainToken === null) {
            return $this->unauthorizedResponse('Token tidak ditemukan.');
        }

        $validation = validate_login_token($plainToken);

        if ($validation === null) {
            return $this->unauthorizedResponse('Token tidak valid atau sudah kedaluwarsa.');
        }

        $request->attributes->set('auth_user', $validation['user']);
        $request->attributes->set('auth_token', $validation['auth_token']);

        return $next($request);
    }

    private function unauthorizedResponse(string $message)
    {
        return response()->json([
            'code' => 401,
            'data' => (object) [],
            'message' => $message,
            'errors' => (object) [],
        ], 401);
    }
}
