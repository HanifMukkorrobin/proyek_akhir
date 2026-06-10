<?php

namespace App\Http\Controllers;

use App\Repositories\MahasiswaRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MahasiswaController extends Controller
{
    private MahasiswaRepository $mahasiswaRepository;

    public function __construct(MahasiswaRepository $mahasiswaRepository)
    {
        $this->mahasiswaRepository = $mahasiswaRepository;
    }

    public function index(Request $request)
    {
        $result = $this->mahasiswaRepository->paginate($request->all());

        return $this->paginationResponse($result);
    }

    public function show(string $mahasiswaId)
    {
        $mahasiswa = $this->mahasiswaRepository->find($mahasiswaId);

        if ($mahasiswa === null) {
            return $this->errorResponse('Mahasiswa tidak ditemukan.', 404);
        }

        return $this->successResponse($mahasiswa, 'Detail mahasiswa berhasil diambil.');
    }

    public function store(Request $request)
    {
        $payload = $request->all();
        $validator = Validator::make($payload, [
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'angkatan' => 'required|integer|min:2000|max:' . ((int) date('Y') + 1),
            'dibuat_oleh_user_id' => 'nullable|integer',
            'diubah_oleh_user_id' => 'nullable|integer',
            'use_external_geocoding' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        $useExternalGeocoding = $this->resolveUseExternalFromRequest($request);

        $result = $this->mahasiswaRepository->create($validator->validated(), $useExternalGeocoding);

        return $this->successResponse($result, 'Mahasiswa berhasil ditambahkan.', 201);
    }

    public function update(string $mahasiswaId, Request $request)
    {
        $payload = $request->all();
        $validator = Validator::make($payload, [
            'nama' => 'sometimes|required|string|max:255',
            'alamat' => 'sometimes|nullable|string',
            'angkatan' => 'sometimes|required|integer|min:2000|max:' . ((int) date('Y') + 1),
            'diubah_oleh_user_id' => 'sometimes|nullable|integer',
            'use_external_geocoding' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        $validated = $validator->validated();

        $updatableFields = ['nama', 'alamat', 'angkatan', 'diubah_oleh_user_id'];
        $hasUpdatableField = false;

        foreach ($updatableFields as $field) {
            if (array_key_exists($field, $validated)) {
                $hasUpdatableField = true;
                break;
            }
        }

        if (!$hasUpdatableField) {
            return $this->errorResponse('Tidak ada field yang dapat diperbarui.', 422);
        }

        $useExternalGeocoding = $this->resolveUseExternalFromRequest($request);

        $result = $this->mahasiswaRepository->update($mahasiswaId, $validated, $useExternalGeocoding);

        if ($result === null) {
            return $this->errorResponse('Mahasiswa tidak ditemukan.', 404);
        }

        return $this->successResponse($result, 'Mahasiswa berhasil diperbarui.');
    }

    public function destroy(string $mahasiswaId, Request $request)
    {
        $payload = $request->all();
        $validator = Validator::make($payload, [
            'dihapus_oleh_user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        $deletedByUserId = null;

        if (array_key_exists('dihapus_oleh_user_id', $validator->validated())) {
            $deletedByUserId = (int) $validator->validated()['dihapus_oleh_user_id'];
        }

        $deleted = $this->mahasiswaRepository->delete($mahasiswaId, $deletedByUserId);

        if (!$deleted) {
            return $this->errorResponse('Mahasiswa tidak ditemukan.', 404);
        }

        return $this->successResponse((object) [], 'Mahasiswa berhasil dihapus.');
    }

    private function resolveUseExternalFromRequest(Request $request): ?bool
    {
        if (!$request->exists('use_external_geocoding')) {
            return null;
        }

        $resolved = filter_var(
            $request->input('use_external_geocoding'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        return $resolved;
    }
}
