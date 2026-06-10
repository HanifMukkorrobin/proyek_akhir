<?php

namespace App\Http\Controllers;

use App\Repositories\RouteSimulationRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class RouteSimulationController extends Controller
{
    private RouteSimulationRepository $repository;

    public function __construct(RouteSimulationRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if ($authUser !== null && $authUser->usergroup && $authUser->usergroup->kode === 'mahasiswa') {
            return $this->errorResponse('Akses ditolak. Mahasiswa tidak diizinkan mengakses fitur simulasi rute.', 403);
        }

        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'q' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'profile' => 'nullable|string|max:50',
            'kendaraan' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        try {
            $filters = $validator->validated();
            if ($authUser !== null) {
                $filters['pemilik_user_id'] = $authUser->user_id;
            }

            return $this->successResponse(
                $this->repository->paginate($filters),
                'Daftar simulasi rute visitasi berhasil diambil.'
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            return $this->errorResponse('Gagal mengambil daftar simulasi rute visitasi.', 500, [
                'detail' => env('APP_DEBUG') ? $exception->getMessage() : null,
            ]);
        }
    }

    public function show(string $simulationId)
    {
        $request = app('request');
        $authUser = $request->attributes->get('auth_user');
        if ($authUser !== null && $authUser->usergroup && $authUser->usergroup->kode === 'mahasiswa') {
            return $this->errorResponse('Akses ditolak. Mahasiswa tidak diizinkan mengakses fitur simulasi rute.', 403);
        }

        $dibuatOlehUserId = $authUser !== null ? $authUser->user_id : null;
        $result = $this->repository->find($simulationId, $dibuatOlehUserId);

        if ($result === null) {
            return $this->errorResponse('Simulasi rute visitasi tidak ditemukan.', 404);
        }

        return $this->successResponse($result, 'Detail simulasi rute visitasi berhasil diambil.');
    }

    public function destroy(string $simulationId)
    {
        $request = app('request');
        $authUser = $request->attributes->get('auth_user');

        if ($authUser === null) {
            return $this->errorResponse('Token tidak valid atau sudah kedaluwarsa.', 401);
        }

        if ($authUser->usergroup && $authUser->usergroup->kode === 'mahasiswa') {
            return $this->errorResponse('Akses ditolak. Mahasiswa tidak diizinkan menghapus simulasi rute.', 403);
        }

        try {
            $result = $this->repository->deleteOwned($simulationId, $authUser->user_id);

            if ($result === null) {
                return $this->errorResponse('Simulasi rute visitasi tidak ditemukan atau bukan milik pengguna.', 404);
            }

            return $this->successResponse($result, 'Simulasi rute visitasi berhasil dihapus.');
        } catch (Throwable $exception) {
            return $this->errorResponse('Gagal menghapus simulasi rute visitasi.', 500, [
                'detail' => env('APP_DEBUG') ? $exception->getMessage() : null,
            ]);
        }
    }

    public function simulate(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if ($authUser !== null && $authUser->usergroup && $authUser->usergroup->kode === 'mahasiswa') {
            return $this->errorResponse('Akses ditolak. Mahasiswa tidak diizinkan mengakses fitur simulasi rute.', 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_rencana' => 'nullable|string|max:255',
            'judul' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'mahasiswa_ids' => 'required|array|min:1',
            'mahasiswa_ids.*' => 'required|string|max:255',
            'titik_awal' => 'nullable|array',
            'titik_awal.nama' => 'nullable|string|max:255',
            'titik_awal.name' => 'nullable|string|max:255',
            'titik_awal.latitude' => 'nullable|numeric|between:-90,90',
            'titik_awal.lat' => 'nullable|numeric|between:-90,90',
            'titik_awal.longitude' => 'nullable|numeric|between:-180,180',
            'titik_awal.lon' => 'nullable|numeric|between:-180,180',
            'titik_awal.lng' => 'nullable|numeric|between:-180,180',
            'titik_akhir' => 'nullable|array',
            'titik_akhir.nama' => 'nullable|string|max:255',
            'titik_akhir.name' => 'nullable|string|max:255',
            'titik_akhir.latitude' => 'nullable|numeric|between:-90,90',
            'titik_akhir.lat' => 'nullable|numeric|between:-90,90',
            'titik_akhir.longitude' => 'nullable|numeric|between:-180,180',
            'titik_akhir.lon' => 'nullable|numeric|between:-180,180',
            'titik_akhir.lng' => 'nullable|numeric|between:-180,180',
            'profile' => 'nullable|string|max:50',
            'kendaraan' => 'nullable|string|max:50',
            'service' => 'nullable|in:route,trip',
            'optimize_order' => 'nullable|boolean',
            'kembali_ke_titik_awal' => 'nullable|boolean',
            'bandingkan_jalur' => 'nullable|boolean',
            'simpan' => 'nullable|boolean',
            'dosen_user_id' => 'nullable|string|max:36',
            'dibuat_oleh_user_id' => 'nullable|string|max:36',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        try {
            $payload = $validator->validated();
            $authUser = $request->attributes->get('auth_user');

            if ($authUser !== null) {
                $payload['dibuat_oleh_user_id'] = $payload['dibuat_oleh_user_id'] ?? $authUser->user_id;
                $payload['dosen_user_id'] = $payload['dosen_user_id'] ?? $authUser->user_id;
            }

            return $this->successResponse(
                $this->repository->simulate($payload),
                'Simulasi rute visitasi berhasil dihitung.'
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 502);
        } catch (Throwable $exception) {
            return $this->errorResponse('Gagal menghitung simulasi rute visitasi.', 500, [
                'detail' => env('APP_DEBUG') ? $exception->getMessage() : null,
            ]);
        }
    }
}
