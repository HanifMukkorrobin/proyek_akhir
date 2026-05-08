<?php

namespace App\Http\Controllers;

use App\Repositories\ActivityLogRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ActivityLogController extends Controller
{
    private ActivityLogRepository $activityLogRepository;

    public function __construct(ActivityLogRepository $activityLogRepository)
    {
        $this->activityLogRepository = $activityLogRepository;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:255',
            'modul' => 'nullable|string|max:80',
            'aksi' => 'nullable|string|max:80',
            'status' => 'nullable|in:success,failed',
            'method' => 'nullable|string|max:10',
            'status_code' => 'nullable|integer|min:100|max:599',
            'user_id' => 'nullable|uuid',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:dibuat_pada,modul,aksi,status,status_code,duration_ms',
            'sort_direction' => 'nullable|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        return $this->paginationResponse(
            $this->activityLogRepository->paginate($validator->validated()),
            200,
            'Data log aktivitas berhasil diambil.'
        );
    }

    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validasi gagal.', 422, $validator->errors());
        }

        return $this->successResponse(
            $this->activityLogRepository->summary($validator->validated()),
            'Ringkasan log aktivitas berhasil diambil.'
        );
    }

    public function show(string $logId)
    {
        $log = $this->activityLogRepository->find($logId);

        if ($log === null) {
            return $this->errorResponse('Log aktivitas tidak ditemukan.', 404);
        }

        return $this->successResponse($log, 'Detail log aktivitas berhasil diambil.');
    }
}
