<?php

namespace App\Http\Controllers;

use App\Repositories\VisitasiRepository;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class VisitasiController extends Controller
{
    private VisitasiRepository $visitasiRepository;

    public function __construct(VisitasiRepository $visitasiRepository)
    {
        $this->visitasiRepository = $visitasiRepository;
    }

    // -------------------------------------------------------------------------
    // Rencana
    // -------------------------------------------------------------------------

    public function index(Request $request)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Hanya dosen yang dapat mengakses fitur visitasi.', 403);
        }

        $result = $this->visitasiRepository->paginate($dosenId, $request->all());

        return $this->paginationResponse($result);
    }

    public function show(Request $request, string $rencanaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Hanya dosen yang dapat mengakses fitur visitasi.', 403);
        }

        $rencana = $this->visitasiRepository->find($rencanaId, $dosenId);

        if ($rencana === null) {
            return $this->errorResponse('Rencana visitasi tidak ditemukan.', 404);
        }

        return $this->successResponse($rencana, 'Detail rencana berhasil diambil.');
    }

    public function store(Request $request)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Hanya dosen yang dapat membuat rencana visitasi.', 403);
        }

        try {
            $result = $this->visitasiRepository->create($request->all(), $dosenId);

            return $this->successResponse($result, 'Rencana visitasi berhasil dibuat.', 201);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Gagal membuat rencana: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, string $rencanaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Hanya dosen yang dapat mengubah rencana visitasi.', 403);
        }

        try {
            $result = $this->visitasiRepository->update($rencanaId, $request->all(), $dosenId);

            if ($result === null) {
                return $this->errorResponse('Rencana visitasi tidak ditemukan.', 404);
            }

            return $this->successResponse($result, 'Rencana visitasi berhasil diperbarui.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (Throwable $e) {
            return $this->errorResponse('Gagal memperbarui rencana: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, string $rencanaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Hanya dosen yang dapat menghapus rencana visitasi.', 403);
        }

        $deleted = $this->visitasiRepository->delete($rencanaId, $dosenId);

        if (!$deleted) {
            return $this->errorResponse('Rencana visitasi tidak ditemukan.', 404);
        }

        return $this->successResponse((object) [], 'Rencana visitasi berhasil dihapus.');
    }

    public function markSelesai(Request $request, string $rencanaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        try {
            $done = $this->visitasiRepository->markSelesai($rencanaId, $dosenId);

            if (!$done) {
                return $this->errorResponse('Rencana visitasi tidak ditemukan.', 404);
            }

            return $this->successResponse((object) [], 'Rencana visitasi ditandai selesai.');
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }
    }

    // -------------------------------------------------------------------------
    // Peserta
    // -------------------------------------------------------------------------

    public function pesertaIndex(Request $request, string $rencanaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $peserta = $this->visitasiRepository->getPeserta($rencanaId, $dosenId);

        return $this->successResponse($peserta, 'Daftar peserta berhasil diambil.');
    }

    public function pesertaStore(Request $request, string $rencanaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $mahasiswaId = trim((string) $request->input('mahasiswa_id', ''));

        if ($mahasiswaId === '') {
            return $this->errorResponse('mahasiswa_id wajib diisi.', 422);
        }

        try {
            $peserta = $this->visitasiRepository->addPeserta(
                $rencanaId,
                $mahasiswaId,
                $dosenId,
                $request->only(['prioritas', 'catatan'])
            );

            return $this->successResponse($peserta, 'Peserta berhasil ditambahkan.', 201);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->errorResponse('Gagal menambahkan peserta: ' . $e->getMessage(), 500);
        }
    }

    public function pesertaUpdate(Request $request, string $rencanaId, string $pesertaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $result = $this->visitasiRepository->updatePeserta($rencanaId, $pesertaId, $request->all(), $dosenId);

        if ($result === null) {
            return $this->errorResponse('Peserta tidak ditemukan.', 404);
        }

        return $this->successResponse($result, 'Peserta berhasil diperbarui.');
    }

    public function pesertaDestroy(Request $request, string $rencanaId, string $pesertaId)
    {
        $dosenId = $this->resolveDosenId($request);

        if ($dosenId === null) {
            return $this->errorResponse('Akses ditolak.', 403);
        }

        $deleted = $this->visitasiRepository->removePeserta($rencanaId, $pesertaId, $dosenId);

        if (!$deleted) {
            return $this->errorResponse('Peserta tidak ditemukan.', 404);
        }

        return $this->successResponse((object) [], 'Peserta berhasil dihapus dari rencana.');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Ambil user_id dari auth_user dan pastikan role adalah dosen.
     * Admin tidak diizinkan membuat/mengelola rencana visitasi.
     */
    private function resolveDosenId(Request $request): ?string
    {
        /** @var \App\Models\User|null $authUser */
        $authUser = $request->attributes->get('auth_user');

        if ($authUser === null) {
            return null;
        }

        // Cek role — hanya dosen yang diizinkan
        $role = $authUser->usergroup->kode ?? ($authUser->role ?? null);

        if ($role !== 'dosen') {
            return null;
        }

        return (string) $authUser->user_id;
    }
}
