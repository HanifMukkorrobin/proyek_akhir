<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ActivityLogRepository
{
    private array $sensitiveKeys = [
        'password',
        'confirm_password',
        'password_confirmation',
        'token',
        'access_token',
        'auth_token',
        'authorization',
        'token_hash',
    ];

    public function paginate(array $filters): array
    {
        $query = ActivityLog::query()->with('user.usergroup');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('modul', 'ILIKE', '%' . $search . '%')
                    ->orWhere('aksi', 'ILIKE', '%' . $search . '%')
                    ->orWhere('target_id', 'ILIKE', '%' . $search . '%')
                    ->orWhere('path', 'ILIKE', '%' . $search . '%')
                    ->orWhere('deskripsi', 'ILIKE', '%' . $search . '%')
                    ->orWhere('response_message', 'ILIKE', '%' . $search . '%')
                    ->orWhere('username', 'ILIKE', '%' . $search . '%')
                    ->orWhere('nama_user', 'ILIKE', '%' . $search . '%');
            });
        }

        foreach (['modul', 'aksi', 'status', 'method', 'user_id'] as $field) {
            if (isset($filters[$field]) && trim((string) $filters[$field]) !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['status_code']) && trim((string) $filters['status_code']) !== '') {
            $query->where('status_code', (int) $filters['status_code']);
        }

        if (isset($filters['date_from']) && trim((string) $filters['date_from']) !== '') {
            $query->where('dibuat_pada', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (isset($filters['date_to']) && trim((string) $filters['date_to']) !== '') {
            $query->where('dibuat_pada', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $allowedSorts = ['dibuat_pada', 'modul', 'aksi', 'status', 'status_code', 'duration_ms'];
        $sortBy = in_array(($filters['sort_by'] ?? ''), $allowedSorts, true)
            ? (string) $filters['sort_by']
            : 'dibuat_pada';
        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDirection);

        $result = paginate_builder(
            $query,
            (int) ($filters['page'] ?? 1),
            (int) ($filters['per_page'] ?? 10)
        );

        $result['data'] = $result['data']
            ->map(fn (ActivityLog $log) => $this->formatLog($log))
            ->values();

        return $result;
    }

    public function find(string $logId): ?array
    {
        $log = ActivityLog::query()
            ->with('user.usergroup')
            ->where('log_id', $logId)
            ->first();

        return $log === null ? null : $this->formatLog($log, true);
    }

    public function summary(array $filters = []): array
    {
        $query = ActivityLog::query();

        if (isset($filters['date_from']) && trim((string) $filters['date_from']) !== '') {
            $query->where('dibuat_pada', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (isset($filters['date_to']) && trim((string) $filters['date_to']) !== '') {
            $query->where('dibuat_pada', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $baseQuery = clone $query;
        $todayQuery = clone $query;

        $byModule = (clone $query)
            ->select('modul', DB::raw('COUNT(*) as total'))
            ->groupBy('modul')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'modul' => $row->modul,
                'total' => (int) $row->total,
            ])
            ->values();

        return [
            'total_log' => (clone $baseQuery)->count(),
            'total_sukses' => (clone $baseQuery)->where('status', 'success')->count(),
            'total_gagal' => (clone $baseQuery)->where('status', 'failed')->count(),
            'total_hari_ini' => $todayQuery->whereDate('dibuat_pada', Carbon::today())->count(),
            'modul_teratas' => $byModule,
        ];
    }

    public function recordHttp(Request $request, Response $response, int $durationMs): ?ActivityLog
    {
        if ($this->shouldSkip($request)) {
            return null;
        }

        $statusCode = $response->getStatusCode();
        $responseMessage = $this->extractResponseMessage($response);
        $routeInfo = $this->resolveRouteInfo($request);
        $authUser = $request->attributes->get('auth_user');
        $status = $this->resolveStatus($statusCode);

        return $this->safeCreate([
            'user_id' => $authUser->user_id ?? null,
            'username' => $authUser->username ?? null,
            'nama_user' => $authUser->nama ?? null,
            'usergroup_kode' => $authUser?->usergroup?->kode ?? null,
            'modul' => $routeInfo['modul'],
            'aksi' => $routeInfo['aksi'],
            'target_tipe' => $routeInfo['target_tipe'],
            'target_id' => $routeInfo['target_id'],
            'status' => $status,
            'status_code' => $statusCode,
            'method' => $request->method(),
            'path' => '/' . trim($request->path(), '/'),
            'deskripsi' => $this->buildDescription($status, $routeInfo, $responseMessage),
            'response_message' => $responseMessage,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'request_payload' => $this->sanitizeRequestPayload($request),
            'metadata' => [
                'duration_ms' => $durationMs,
                'query' => $this->sanitizeArray($request->query->all()),
                'content_type' => $request->headers->get('Content-Type'),
            ],
            'duration_ms' => $durationMs,
        ]);
    }

    public function recordException(Request $request, Throwable $exception, int $durationMs): ?ActivityLog
    {
        if ($this->shouldSkip($request)) {
            return null;
        }

        $routeInfo = $this->resolveRouteInfo($request);
        $authUser = $request->attributes->get('auth_user');

        return $this->safeCreate([
            'user_id' => $authUser->user_id ?? null,
            'username' => $authUser->username ?? null,
            'nama_user' => $authUser->nama ?? null,
            'usergroup_kode' => $authUser?->usergroup?->kode ?? null,
            'modul' => $routeInfo['modul'],
            'aksi' => $routeInfo['aksi'],
            'target_tipe' => $routeInfo['target_tipe'],
            'target_id' => $routeInfo['target_id'],
            'status' => 'failed',
            'status_code' => 500,
            'method' => $request->method(),
            'path' => '/' . trim($request->path(), '/'),
            'deskripsi' => $this->buildDescription('failed', $routeInfo, 'Unhandled exception.'),
            'response_message' => 'Unhandled exception.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'request_payload' => $this->sanitizeRequestPayload($request),
            'metadata' => [
                'duration_ms' => $durationMs,
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
            ],
            'duration_ms' => $durationMs,
        ]);
    }

    public function recordLogin(Request $request, ?array $user, int $statusCode, string $message): ?ActivityLog
    {
        $status = $this->resolveStatus($statusCode);
        $identifier = trim((string) $request->input('identifier', ''));
        $username = $user['username'] ?? ($identifier !== '' ? $identifier : null);

        return $this->safeCreate([
            'user_id' => $user['user_id'] ?? null,
            'username' => $username,
            'nama_user' => $user['nama'] ?? null,
            'usergroup_kode' => $user['role'] ?? $user['usergroup']['kode'] ?? null,
            'modul' => 'auth',
            'aksi' => 'login',
            'target_tipe' => 'user',
            'target_id' => $user['user_id'] ?? null,
            'status' => $status,
            'status_code' => $statusCode,
            'method' => $request->method(),
            'path' => '/' . trim($request->path(), '/'),
            'deskripsi' => $status === 'success' ? 'Login berhasil.' : 'Login gagal.',
            'response_message' => $message,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'request_payload' => $this->sanitizeArray([
                'identifier' => $identifier,
                'remember_me' => $request->input('remember_me'),
            ]),
            'metadata' => [
                'source' => 'auth_controller',
            ],
            'duration_ms' => null,
        ]);
    }

    private function safeCreate(array $attributes): ?ActivityLog
    {
        try {
            $attributes['log_id'] = (string) Str::uuid();
            $attributes['dibuat_pada'] = Carbon::now();

            return ActivityLog::query()->create($attributes);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function formatLog(ActivityLog $log, bool $withPayload = false): array
    {
        $data = [
            'log_id' => $log->log_id,
            'user_id' => $log->user_id,
            'username' => $log->username,
            'nama_user' => $log->nama_user,
            'usergroup_kode' => $log->usergroup_kode,
            'modul' => $log->modul,
            'aksi' => $log->aksi,
            'target_tipe' => $log->target_tipe,
            'target_id' => $log->target_id,
            'status' => $log->status,
            'status_code' => $log->status_code,
            'method' => $log->method,
            'path' => $log->path,
            'deskripsi' => $log->deskripsi,
            'response_message' => $log->response_message,
            'ip_address' => $log->ip_address,
            'duration_ms' => $log->duration_ms,
            'dibuat_pada' => optional($log->dibuat_pada)->toISOString(),
            'user' => $log->user ? [
                'user_id' => $log->user->user_id,
                'nama' => $log->user->nama,
                'username' => $log->user->username,
                'email' => $log->user->email,
                'usergroup' => $log->user->usergroup ? [
                    'kode' => $log->user->usergroup->kode,
                    'nama' => $log->user->usergroup->nama,
                ] : null,
            ] : null,
        ];

        if ($withPayload) {
            $data['user_agent'] = $log->user_agent;
            $data['request_payload'] = $log->request_payload;
            $data['metadata'] = $log->metadata;
        }

        return $data;
    }

    private function shouldSkip(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return $request->method() === 'OPTIONS'
            || $path === ''
            || $path === 'auth/login';
    }

    private function resolveRouteInfo(Request $request): array
    {
        $path = trim($request->path(), '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $first = $segments[0] ?? 'api';
        $second = $segments[1] ?? null;
        $third = $segments[2] ?? null;

        $module = str_replace('-', '_', $first);
        $action = $this->resolveAction($request->method());
        $targetType = $this->resolveTargetType($module);
        $targetId = null;

        if ($first === 'public') {
            $module = $second === 'test-klasifikasi-alamat' ? 'public_klasifikasi_alamat' : 'public_wilayah';
            $action = $request->method() === 'POST' ? 'proses' : 'lihat';
        } elseif ($first === 'dashboard') {
            $module = 'dashboard';
            $action = 'lihat_' . str_replace('-', '_', (string) $second);
        } elseif ($first === 'mahasiswa' && $second === 'import') {
            $module = 'mahasiswa_import';
            $action = match ($third) {
                'scan' => 'scan_import',
                'confirm' => 'confirm_import',
                'template' => 'download_template',
                default => $action,
            };
        } elseif ($first === 'users' && $third === 'reset-password') {
            $module = 'users';
            $action = 'reset_password';
            $targetType = 'user';
            $targetId = $second;
        } elseif (in_array($first, ['users', 'wilayah', 'mahasiswa', 'activity-logs', 'log-aktivitas'], true)) {
            $targetId = $second;
            $module = in_array($first, ['activity-logs', 'log-aktivitas'], true) ? 'activity_logs' : $module;
        }

        return [
            'modul' => $module,
            'aksi' => $action,
            'target_tipe' => $targetType,
            'target_id' => $targetId,
        ];
    }

    private function resolveAction(string $method): string
    {
        return match (strtoupper($method)) {
            'GET' => 'lihat',
            'POST' => 'tambah',
            'PUT', 'PATCH' => 'ubah',
            'DELETE' => 'hapus',
            default => 'proses',
        };
    }

    private function resolveTargetType(string $module): string
    {
        return match ($module) {
            'users' => 'user',
            'mahasiswa' => 'mahasiswa',
            'wilayah' => 'wilayah',
            'activity_logs' => 'activity_log',
            default => $module,
        };
    }

    private function resolveStatus(int $statusCode): string
    {
        return $statusCode >= 200 && $statusCode < 400 ? 'success' : 'failed';
    }

    private function buildDescription(string $status, array $routeInfo, ?string $responseMessage): string
    {
        $prefix = $status === 'success' ? 'Berhasil' : 'Gagal';
        $description = sprintf(
            '%s menjalankan aksi %s pada modul %s.',
            $prefix,
            $routeInfo['aksi'],
            $routeInfo['modul']
        );

        if ($responseMessage !== null && trim($responseMessage) !== '') {
            return $description . ' ' . $responseMessage;
        }

        return $description;
    }

    private function extractResponseMessage(Response $response): ?string
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        if (!str_contains($contentType, 'application/json')) {
            return null;
        }

        $content = $response->getContent();

        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            return null;
        }

        return isset($decoded['message']) ? (string) $decoded['message'] : null;
    }

    private function sanitizeRequestPayload(Request $request): array
    {
        $payload = $request->request->all();

        if ($request->isMethod('GET')) {
            $payload = $request->query->all();
        }

        foreach ($request->allFiles() as $key => $file) {
            $payload[$key] = $this->formatFilePayload($file);
        }

        return $this->sanitizeArray($payload);
    }

    private function sanitizeArray(array $payload): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, $this->sensitiveKeys, true)) {
                $result[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->sanitizeArray($value);
                continue;
            }

            if ($value instanceof UploadedFile) {
                $result[$key] = $this->formatFilePayload($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[$key] = $value;
                continue;
            }

            $result[$key] = '[UNSERIALIZABLE]';
        }

        return $result;
    }

    private function formatFilePayload($file)
    {
        if (is_array($file)) {
            return array_map(fn ($item) => $this->formatFilePayload($item), $file);
        }

        if (!$file instanceof UploadedFile) {
            return '[FILE]';
        }

        return [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }
}
