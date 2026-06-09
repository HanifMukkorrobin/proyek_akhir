<?php

namespace App\Http\Controllers;

use App\Repositories\SimulasiRuteRepository;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class SimulasiRuteController extends Controller
{
    private SimulasiRuteRepository $simulasiRepository;

    public function __construct(SimulasiRuteRepository $simulasiRepository)
    {
        $this->simulasiRepository = $simulasiRepository;
    }

    // -------------------------------------------------------------------------
    // Simulasi (Dosen)
    // -------------------------------------------------------------------------

    public function simulate(Request $request, string $rencanaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Hanya dosen yang dapat menjalankan simulasi rute.', 403);
        }

        try {
            $result = $this->simulasiRepository->simulate($rencanaId, $dosenId);

            return $this->successResponse($result, 'Simulasi rute berhasil dijalankan.');
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Gagal menjalankan simulasi: ' . $e->getMessage(), 500);
        }
    }

    public function createAndSimulate(Request $request)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Hanya dosen yang dapat membuat rencana dan simulasi rute.', 403);
        }

        try {
            $result = $this->simulasiRepository->createAndSimulate($request->all(), $dosenId);

            return $this->successResponse($result, 'Rencana visitasi berhasil dibuat dan rute berhasil disimulasikan.', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Gagal membuat rencana & rute: ' . $e->getMessage(), 500);
        }
    }

    public function getRute(Request $request, string $rencanaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        try {
            $rute = $this->simulasiRepository->getLatestRute($rencanaId, $dosenId);

            if ($rute === null) {
                return $this->errorResponse('Belum ada simulasi yang berhasil untuk rencana ini.', 404);
            }

            return $this->successResponse($rute, 'Data rute berhasil diambil.');
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    public function getRuteHistory(Request $request, string $rencanaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        try {
            $history = $this->simulasiRepository->getRuteHistory($rencanaId, $dosenId);

            return $this->successResponse($history, 'Riwayat simulasi berhasil diambil.');
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * Kembalikan data untuk print-preview HTML (PDF via browser Ctrl+P).
     */
    public function getPrintData(Request $request, string $rencanaId, string $ruteId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        try {
            $data = $this->simulasiRepository->getPrintData($ruteId, $dosenId);

            return $this->successResponse($data, 'Data print berhasil diambil.');
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * Export rekap semua rencana dosen sebagai file Excel (.xlsx).
     */
    public function exportRekapExcel(Request $request)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        try {
            $xlsxContent = $this->simulasiRepository->exportRekapXlsx($dosenId);

            $filename = 'rekap-visitasi-' . date('Ymd-His') . '.xlsx';

            return response($xlsxContent, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => strlen($xlsxContent),
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse('Gagal mengekspor rekap: ' . $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // Log Simulasi (Admin only)
    // -------------------------------------------------------------------------

    public function logSimulasi(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');

        if ($authUser === null) {
            return $this->errorResponse('Tidak terautentikasi.', 401);
        }

        $role = $authUser->usergroup->kode ?? ($authUser->role ?? null);

        if ($role !== 'admin') {
            return $this->errorResponse('Hanya admin yang dapat mengakses log simulasi.', 403);
        }

        $result = $this->simulasiRepository->getLogSimulasi($request->all());

        return $this->paginationResponse($result);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveDosenId(Request $request): ?string
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = $request->attributes->get('auth_user');

        if ($authUser === null) {
            return null;
        }

        $role = $authUser->usergroup->kode ?? ($authUser->role ?? null);

        if ($role !== 'dosen') {
            return null;
        }

        return (string) $authUser->user_id;
    }
}
