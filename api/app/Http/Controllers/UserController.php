<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class UserController extends Controller
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function index(Request $request)
    {
        $result = $this->userRepository->paginate($request->all());

        return $this->paginationResponse($result);
    }

    public function show(string $userId)
    {
        $user = $this->userRepository->find($userId);

        if ($user === null) {
            return $this->errorResponse('User tidak ditemukan.', 404);
        }

        return $this->successResponse($user, 'Detail user berhasil diambil.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
            'username' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'password' => 'required|string|min:8|max:255',
            'usergroup_id' => 'nullable|uuid|required_without_all:usergroup_kode,role',
            'usergroup_kode' => 'nullable|string|max:30',
            'role' => 'nullable|string|max:30',
            'mahasiswa_id' => 'nullable|string|max:255',
            'status_aktif' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        try {
            $result = $this->userRepository->create($validator->validated(), $this->actorUserId($request));

            return $this->successResponse($result, 'User berhasil ditambahkan.', 201);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            return $this->errorResponse('Terjadi kesalahan saat menambahkan user.', 500);
        }
    }

    public function update(string $userId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|nullable|email|max:255',
            'usergroup_id' => 'sometimes|nullable|uuid',
            'usergroup_kode' => 'sometimes|nullable|string|max:30',
            'role' => 'sometimes|nullable|string|max:30',
            'mahasiswa_id' => 'sometimes|nullable|string|max:255',
            'status_aktif' => 'sometimes|nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        $validated = $validator->validated();

        if (empty($validated)) {
            return $this->errorResponse('Tidak ada field yang dapat diperbarui.', 422);
        }

        try {
            $result = $this->userRepository->update($userId, $validated, $this->actorUserId($request));

            if ($result === null) {
                return $this->errorResponse('User tidak ditemukan.', 404);
            }

            return $this->successResponse($result, 'User berhasil diperbarui.');
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            return $this->errorResponse('Terjadi kesalahan saat memperbarui user.', 500);
        }
    }

    public function destroy(string $userId, Request $request)
    {
        $deleted = $this->userRepository->delete($userId, $this->actorUserId($request));

        if (!$deleted) {
            return $this->errorResponse('User tidak ditemukan.', 404);
        }

        return $this->successResponse((object) [], 'User berhasil dihapus.');
    }

    public function resetPassword(string $userId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'nullable|string|min:8|max:255',
            'revoke_tokens' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        $validated = $validator->validated();
        $revokeTokens = filter_var($validated['revoke_tokens'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        try {
            $result = $this->userRepository->resetPassword(
                $userId,
                $validated['password'] ?? null,
                $revokeTokens !== false,
                $this->actorUserId($request)
            );

            if ($result === null) {
                return $this->errorResponse('User tidak ditemukan.', 404);
            }

            return $this->successResponse($result, 'Password user berhasil direset.');
        } catch (Throwable $exception) {
            return $this->errorResponse('Terjadi kesalahan saat reset password user.', 500);
        }
    }

    private function actorUserId(Request $request): ?string
    {
        $user = $request->attributes->get('auth_user');

        if ($user === null || !isset($user->user_id)) {
            return null;
        }

        return (string) $user->user_id;
    }
}
