<?php

namespace App\Http\Controllers;

use App\Repositories\WilayahRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WilayahController extends Controller
{
    private WilayahRepository $wilayahRepository;

    public function __construct(WilayahRepository $wilayahRepository)
    {
        $this->wilayahRepository = $wilayahRepository;
    }

    public function index(Request $request)
    {
        $parentId = trim((string) $request->query('parent_id', ''));
        $data = $this->wilayahRepository->getTree($parentId);
        
        return $this->successResponse($data, 'Data wilayah berhasil diambil.');
    }

    public function store(Request $request)
    {
        $payload = $request->all();
        $validator = Validator::make($payload, [
            'parent_id' => 'nullable|string',
            'nama' => 'required|string|max:255',
            'kode_dukcapil' => 'nullable|string|max:50',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'dibuat_oleh_user_id' => 'nullable|integer',
            'diubah_oleh_user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        $result = $this->wilayahRepository->create($validator->validated());

        return $this->successResponse($result, 'Wilayah berhasil ditambahkan.', 201);
    }

    public function update(string $wilayahId, Request $request)
    {
        $payload = $request->all();
        $validator = Validator::make($payload, [
            'nama' => 'sometimes|required|string|max:255',
            'kode_dukcapil' => 'nullable|string|max:50',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'diubah_oleh_user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        $validated = $validator->validated();
        
        $updatableFields = ['nama', 'kode_dukcapil', 'latitude', 'longitude', 'diubah_oleh_user_id'];
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

        $result = $this->wilayahRepository->update($wilayahId, $validated);

        if ($result === null) {
            return $this->errorResponse('Wilayah tidak ditemukan.', 404);
        }

        return $this->successResponse($result, 'Wilayah berhasil diperbarui.');
    }

    public function destroy(string $wilayahId, Request $request)
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

        $deleted = $this->wilayahRepository->delete($wilayahId, $deletedByUserId);

        if (!$deleted) {
            return $this->errorResponse('Wilayah tidak ditemukan.', 404);
        }

        return $this->successResponse((object) [], 'Wilayah berhasil dihapus.');
    }
}
