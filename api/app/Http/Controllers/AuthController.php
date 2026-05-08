<?php

namespace App\Http\Controllers;

use App\Repositories\AuthRepository;
use App\Repositories\ActivityLogRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class AuthController extends Controller
{
    private AuthRepository $authRepository;

    private ActivityLogRepository $activityLogRepository;

    public function __construct(AuthRepository $authRepository, ActivityLogRepository $activityLogRepository)
    {
        $this->authRepository = $authRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'remember_me' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            $this->activityLogRepository->recordLogin($request, null, 422, 'Validasi gagal.');

            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        $validated = $validator->validated();
        $rememberMe = filter_var($validated['remember_me'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        try {
            $result = $this->authRepository->login(
                (string) $validated['identifier'],
                (string) $validated['password'],
                $request->ip(),
                $request->header('User-Agent'),
                $rememberMe === true
            );

            $this->activityLogRepository->recordLogin($request, $result['user'] ?? null, 200, 'Login berhasil.');

            return $this->successResponse($result, 'Login berhasil.');
        } catch (InvalidArgumentException $exception) {
            $this->activityLogRepository->recordLogin($request, null, 401, $exception->getMessage());

            return $this->errorResponse($exception->getMessage(), 401);
        } catch (Throwable $exception) {
            $this->activityLogRepository->recordLogin($request, null, 500, 'Terjadi kesalahan saat proses login.');

            return $this->errorResponse('Terjadi kesalahan saat proses login.', 500);
        }
    }
}
