<?php

namespace App\Http\Controllers;

use App\Repositories\DashboardMapRepository;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class DashboardMapController extends Controller
{
    private DashboardMapRepository $repository;

    public function __construct(DashboardMapRepository $repository)
    {
        $this->repository = $repository;
    }

    public function wilayahPoints(Request $request)
    {
        try {
            return $this->successResponse(
                $this->repository->getWilayahPoints($request->all())
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            return $this->errorResponse('Gagal memuat titik wilayah 3D map.', 500, [
                'detail' => env('APP_DEBUG') ? $exception->getMessage() : null,
            ]);
        }
    }

    public function mahasiswaByWilayah(Request $request, string $wilayahId)
    {
        try {
            return $this->successResponse(
                $this->repository->getMahasiswaByWilayah($wilayahId, $request->all())
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            return $this->errorResponse('Gagal memuat mahasiswa pada wilayah.', 500, [
                'detail' => env('APP_DEBUG') ? $exception->getMessage() : null,
            ]);
        }
    }

    public function searchMahasiswa(Request $request)
    {
        try {
            return $this->successResponse(
                $this->repository->searchMahasiswa($request->all())
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            return $this->errorResponse('Gagal mencari mahasiswa untuk 3D map.', 500, [
                'detail' => env('APP_DEBUG') ? $exception->getMessage() : null,
            ]);
        }
    }
}
