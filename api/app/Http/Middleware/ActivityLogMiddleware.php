<?php

namespace App\Http\Middleware;

use App\Repositories\ActivityLogRepository;
use Closure;
use Throwable;

class ActivityLogMiddleware
{
    private ActivityLogRepository $activityLogRepository;

    public function __construct(ActivityLogRepository $activityLogRepository)
    {
        $this->activityLogRepository = $activityLogRepository;
    }

    public function handle($request, Closure $next)
    {
        $startedAt = microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            try {
                $this->activityLogRepository->recordException($request, $exception, $durationMs);
            } catch (Throwable $logException) {
                // Activity logging must never block the main API response.
            }

            throw $exception;
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        try {
            $this->activityLogRepository->recordHttp($request, $response, $durationMs);
        } catch (Throwable $exception) {
            // Activity logging must never block the main API response.
        }

        return $response;
    }
}
