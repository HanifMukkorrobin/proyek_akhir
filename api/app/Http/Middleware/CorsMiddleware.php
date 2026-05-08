<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        $allowedOrigins = $this->resolveAllowedOrigins();
        $origin = $request->headers->get('Origin');

        if (strtoupper($request->method()) === 'OPTIONS') {
            $response = response('', 204);

            return $this->withCorsHeaders($response, $origin, $allowedOrigins);
        }

        $response = $next($request);

        return $this->withCorsHeaders($response, $origin, $allowedOrigins);
    }

    private function withCorsHeaders($response, ?string $origin, array $allowedOrigins)
    {
        if ($origin !== null && $this->isOriginAllowed($origin, $allowedOrigins)) {
            if (in_array('*', $allowedOrigins, true)) {
                $response->headers->set('Access-Control-Allow-Origin', '*');
            } else {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Vary', 'Origin');
            }
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }

    private function resolveAllowedOrigins(): array
    {
        $rawOrigins = (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:3001');
        $items = array_map('trim', explode(',', $rawOrigins));

        $filtered = array_values(array_filter($items, static function ($origin) {
            return $origin !== '';
        }));

        if (empty($filtered)) {
            return ['http://localhost:3000', 'http://localhost:3001'];
        }

        return $filtered;
    }

    private function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $allowedOrigins, true);
    }
}