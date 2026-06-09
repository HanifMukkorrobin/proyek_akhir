<?php

namespace App\Repositories;

use App\Models\Mahasiswa;
use App\Models\VisitasiPeserta;
use App\Models\VisitasiRencana;
use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * VisitasiRepository
 *
 * Menangani CRUD rencana visitasi dan manajemen peserta.
 * Semua operasi divalidasi terhadap dosen_id — dosen hanya dapat
 * mengakses rencana milik sendiri.
 */
class VisitasiRepository
{
    private const MAX_PESERTA = 5;

    // -------------------------------------------------------------------------
    // Rencana
    // -------------------------------------------------------------------------

    /**
     * Daftar rencana milik dosen tertentu dengan pagination.
     */
    public function paginate(string $dosenId, array $filters = []): array
    {
        $query = VisitasiRencana::query()
            ->where('dosen_id', $dosenId)
            ->whereNull('dihapus_pada')
            ->withCount(['peserta' => fn($q) => $q->whereNull('dihapus_pada')])
            ->orderByDesc('dibuat_pada');

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where('nama_rencana', 'ILIKE', '%' . $search . '%');
        }

        $status = trim((string) ($filters['status'] ?? ''));

        if ($status !== '') {
            $query->where('status', $status);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($filters['per_page'] ?? 10)));

        $pagination = paginate_builder($query, $page, $perPage);

        return [
            'data' => array_map(
                fn(VisitasiRencana $r) => $this->transformRencana($r),
                $pagination['data']->all()
            ),
            'halaman_sekarang' => $pagination['halaman_sekarang'],
            'per_halaman' => $pagination['per_halaman'],
            'total_data' => $pagination['total_data'],
            'total_halaman' => $pagination['total_halaman'],
        ];
    }

    /**
     * Detail rencana + peserta + rute terakhir.
     */
    public function find(string $rencanaId, string $dosenId): ?array
    {
        $rencana = VisitasiRencana::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->where('dosen_id', $dosenId)
            ->whereNull('dihapus_pada')
            ->with([
                'peserta.mahasiswa.wilayah',
                'rute' => fn($q) => $q->where('status', 'success')->limit(1),
                'rute.detail',
            ])
            ->first();

        if ($rencana === null) {
            return null;
        }

        return $this->transformRencanaDetail($rencana);
    }

    /**
     * Buat rencana baru (status = draft).
     */
    public function create(array $payload, string $dosenId): array
    {
        $this->validateRencanaPayload($payload);

        $rencana = VisitasiRencana::query()->create([
            'visitasi_rencana_id' => (string) Str::uuid(),
            'dosen_id' => $dosenId,
            'dibuat_oleh_user_id' => $dosenId,
            'nama_rencana' => trim((string) ($payload['nama_rencana'] ?? '')),
            'deskripsi' => trim((string) ($payload['deskripsi'] ?? '')) ?: null,
            'titik_awal_latitude' => $payload['titik_awal_latitude'] ?? null,
            'titik_awal_longitude' => $payload['titik_awal_longitude'] ?? null,
            'titik_awal_label' => trim((string) ($payload['titik_awal_label'] ?? '')) ?: null,
            'jenis_kendaraan' => $payload['jenis_kendaraan'] ?? 'motor',
            'lewat_tol' => (bool) ($payload['lewat_tol'] ?? false),
            'status' => 'draft',
            'dibuat_pada' => Carbon::now(),
        ]);

        return $this->transformRencana($rencana);
    }

    /**
     * Update rencana (nama, deskripsi, titik awal, kendaraan).
     * Tidak dapat mengubah rencana yang sudah selesai.
     */
    public function update(string $rencanaId, array $payload, string $dosenId): ?array
    {
        $rencana = $this->findModel($rencanaId, $dosenId);

        if ($rencana === null) {
            return null;
        }

        if ($rencana->status === 'selesai') {
            throw new RuntimeException('Rencana yang sudah selesai tidak dapat diubah.');
        }

        if (array_key_exists('nama_rencana', $payload)) {
            $nama = trim((string) $payload['nama_rencana']);

            if ($nama === '') {
                throw new InvalidArgumentException('Nama rencana tidak boleh kosong.');
            }

            $rencana->nama_rencana = $nama;
        }

        if (array_key_exists('deskripsi', $payload)) {
            $rencana->deskripsi = trim((string) $payload['deskripsi']) ?: null;
        }

        if (array_key_exists('titik_awal_latitude', $payload)) {
            $rencana->titik_awal_latitude = $payload['titik_awal_latitude'];
        }

        if (array_key_exists('titik_awal_longitude', $payload)) {
            $rencana->titik_awal_longitude = $payload['titik_awal_longitude'];
        }

        if (array_key_exists('titik_awal_label', $payload)) {
            $rencana->titik_awal_label = trim((string) $payload['titik_awal_label']) ?: null;
        }

        if (array_key_exists('jenis_kendaraan', $payload)) {
            $jenis = $payload['jenis_kendaraan'];

            if (!in_array($jenis, ['motor', 'mobil'], true)) {
                throw new InvalidArgumentException('Jenis kendaraan harus motor atau mobil.');
            }

            $rencana->jenis_kendaraan = $jenis;
        }

        if (array_key_exists('lewat_tol', $payload)) {
            $rencana->lewat_tol = (bool) $payload['lewat_tol'];
        }

        $rencana->diubah_pada = Carbon::now();
        $rencana->save();

        return $this->transformRencana($rencana);
    }

    /**
     * Soft delete rencana beserta peserta terkait.
     */
    public function delete(string $rencanaId, string $dosenId): bool
    {
        $rencana = $this->findModel($rencanaId, $dosenId);

        if ($rencana === null) {
            return false;
        }

        // Soft delete semua peserta
        VisitasiPeserta::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->whereNull('dihapus_pada')
            ->update(['dihapus_pada' => Carbon::now()]);

        $rencana->dihapus_pada = Carbon::now();
        $rencana->save();

        return true;
    }

    /**
     * Tandai rencana sebagai selesai (hanya dari status 'siap').
     */
    public function markSelesai(string $rencanaId, string $dosenId): bool
    {
        $rencana = $this->findModel($rencanaId, $dosenId);

        if ($rencana === null) {
            return false;
        }

        if ($rencana->status !== 'siap') {
            throw new RuntimeException('Hanya rencana dengan status "siap" yang dapat ditandai selesai.');
        }

        $rencana->status = 'selesai';
        $rencana->diubah_pada = Carbon::now();
        $rencana->save();

        return true;
    }

    // -------------------------------------------------------------------------
    // Peserta
    // -------------------------------------------------------------------------

    /**
     * List peserta dari satu rencana.
     */
    public function getPeserta(string $rencanaId, string $dosenId): array
    {
        $rencana = $this->findModel($rencanaId, $dosenId);

        if ($rencana === null) {
            return [];
        }

        $peserta = VisitasiPeserta::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->whereNull('dihapus_pada')
            ->with('mahasiswa.wilayah')
            ->orderBy('urutan')
            ->orderBy('prioritas')
            ->get();

        return $peserta->map(fn(VisitasiPeserta $p) => $this->transformPeserta($p))->all();
    }

    /**
     * Tambah mahasiswa ke rencana.
     * Validasi: maks 5, mahasiswa harus is_valid_address=true, tidak duplikat.
     */
    public function addPeserta(string $rencanaId, string $mahasiswaId, string $dosenId, array $extra = []): array
    {
        $rencana = $this->findModel($rencanaId, $dosenId);

        if ($rencana === null) {
            throw new RuntimeException('Rencana tidak ditemukan.');
        }

        if ($rencana->status === 'selesai') {
            throw new RuntimeException('Tidak dapat menambah peserta pada rencana yang sudah selesai.');
        }

        // Cek batas maksimal peserta aktif
        $currentCount = VisitasiPeserta::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->whereNull('dihapus_pada')
            ->count();

        if ($currentCount >= self::MAX_PESERTA) {
            throw new RuntimeException('Maksimal ' . self::MAX_PESERTA . ' mahasiswa per rencana visitasi.');
        }

        // Cek mahasiswa ada dan memiliki alamat valid
        $mahasiswa = Mahasiswa::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->first();

        if ($mahasiswa === null) {
            throw new RuntimeException('Mahasiswa tidak ditemukan.');
        }

        if (!$mahasiswa->is_valid_address) {
            throw new RuntimeException('Mahasiswa tidak dapat ditambahkan: data lokasi tidak valid (is_valid_address=false).');
        }

        // Cek duplikat (termasuk yang soft-deleted — restore jika perlu)
        $existing = VisitasiPeserta::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->where('mahasiswa_id', $mahasiswaId)
            ->first();

        if ($existing !== null) {
            if ($existing->dihapus_pada !== null) {
                // Restore soft-deleted peserta
                $existing->dihapus_pada = null;
                $existing->catatan = trim((string) ($extra['catatan'] ?? '')) ?: null;
                $existing->save();

                $existing->load('mahasiswa.wilayah');

                return $this->transformPeserta($existing);
            }

            throw new RuntimeException('Mahasiswa sudah terdaftar dalam rencana ini.');
        }

        $peserta = VisitasiPeserta::query()->create([
            'visitasi_peserta_id' => (string) Str::uuid(),
            'visitasi_rencana_id' => $rencanaId,
            'mahasiswa_id' => $mahasiswaId,
            'prioritas' => (int) ($extra['prioritas'] ?? 0),
            'catatan' => trim((string) ($extra['catatan'] ?? '')) ?: null,
            'dibuat_pada' => Carbon::now(),
        ]);

        $peserta->load('mahasiswa.wilayah');

        return $this->transformPeserta($peserta);
    }

    /**
     * Hapus peserta dari rencana (soft delete).
     */
    public function removePeserta(string $rencanaId, string $pesertaId, string $dosenId): bool
    {
        $rencana = $this->findModel($rencanaId, $dosenId);

        if ($rencana === null) {
            return false;
        }

        $peserta = VisitasiPeserta::query()
            ->where('visitasi_peserta_id', $pesertaId)
            ->where('visitasi_rencana_id', $rencanaId)
            ->whereNull('dihapus_pada')
            ->first();

        if ($peserta === null) {
            return false;
        }

        $peserta->dihapus_pada = Carbon::now();
        $peserta->save();

        return true;
    }

    /**
     * Update prioritas/catatan peserta.
     */
    public function updatePeserta(string $rencanaId, string $pesertaId, array $payload, string $dosenId): ?array
    {
        $rencana = $this->findModel($rencanaId, $dosenId);

        if ($rencana === null) {
            return null;
        }

        $peserta = VisitasiPeserta::query()
            ->where('visitasi_peserta_id', $pesertaId)
            ->where('visitasi_rencana_id', $rencanaId)
            ->whereNull('dihapus_pada')
            ->with('mahasiswa.wilayah')
            ->first();

        if ($peserta === null) {
            return null;
        }

        if (array_key_exists('prioritas', $payload)) {
            $peserta->prioritas = (int) $payload['prioritas'];
        }

        if (array_key_exists('catatan', $payload)) {
            $peserta->catatan = trim((string) $payload['catatan']) ?: null;
        }

        $peserta->save();

        return $this->transformPeserta($peserta);
    }

    // -------------------------------------------------------------------------
    // Transform helpers
    // -------------------------------------------------------------------------

    public function transformRencana(VisitasiRencana $rencana): array
    {
        return [
            'visitasi_rencana_id' => $rencana->visitasi_rencana_id,
            'dosen_id' => $rencana->dosen_id,
            'nama_rencana' => $rencana->nama_rencana,
            'deskripsi' => $rencana->deskripsi,
            'titik_awal_latitude' => $rencana->titik_awal_latitude,
            'titik_awal_longitude' => $rencana->titik_awal_longitude,
            'titik_awal_label' => $rencana->titik_awal_label,
            'jenis_kendaraan' => $rencana->jenis_kendaraan,
            'lewat_tol' => (bool) $rencana->lewat_tol,
            'perkiraan_total_jarak_km' => $rencana->perkiraan_total_jarak_km,
            'perkiraan_total_menit' => $rencana->perkiraan_total_menit,
            'status' => $rencana->status,
            'jumlah_peserta' => $rencana->peserta_count ?? null,
            'dibuat_pada' => $rencana->dibuat_pada,
            'diubah_pada' => $rencana->diubah_pada,
        ];
    }

    public function transformRencanaDetail(VisitasiRencana $rencana): array
    {
        $data = $this->transformRencana($rencana);
        $data['peserta'] = $rencana->peserta->map(fn($p) => $this->transformPeserta($p))->values()->all();

        // Rute terakhir yang berhasil
        $latestRute = $rencana->rute->first();
        $data['rute_terakhir'] = $latestRute ? $this->transformRute($latestRute) : null;

        return $data;
    }

    public function transformPeserta(VisitasiPeserta $peserta): array
    {
        $mahasiswa = $peserta->mahasiswa;

        return [
            'visitasi_peserta_id' => $peserta->visitasi_peserta_id,
            'visitasi_rencana_id' => $peserta->visitasi_rencana_id,
            'mahasiswa_id' => $peserta->mahasiswa_id,
            'prioritas' => $peserta->prioritas,
            'urutan' => $peserta->urutan,
            'catatan' => $peserta->catatan,
            'mahasiswa' => $mahasiswa ? [
                'mahasiswa_id' => $mahasiswa->mahasiswa_id,
                'nama' => $mahasiswa->nama,
                'alamat' => $mahasiswa->alamat,
                'latitude' => $mahasiswa->latitude,
                'longitude' => $mahasiswa->longitude,
                'wilayah' => $mahasiswa->wilayah ? [
                    'wilayah_id' => $mahasiswa->wilayah->wilayah_id,
                    'nama' => $mahasiswa->wilayah->nama,
                ] : null,
            ] : null,
        ];
    }

    public function transformRute(\App\Models\VisitasiRute $rute): array
    {
        return [
            'visitasi_rute_id' => $rute->visitasi_rute_id,
            'metode_kalkulasi' => $rute->metode_kalkulasi,
            'osrm_profile' => $rute->osrm_profile,
            'total_jarak_km' => $rute->total_jarak_km,
            'total_estimasi_menit' => $rute->total_estimasi_menit,
            'status' => $rute->status,
            'dibuat_pada' => $rute->dibuat_pada,
            'detail' => $rute->detail->map(fn($d) => [
                'visitasi_rute_detail_id' => $d->visitasi_rute_detail_id,
                'tipe_titik' => $d->tipe_titik,
                'urutan_kunjungan' => $d->urutan_kunjungan,
                'label' => $d->label,
                'latitude' => $d->latitude,
                'longitude' => $d->longitude,
                'estimasi_ke_sini_menit' => $d->estimasi_ke_sini_menit,
                'jarak_dari_sebelumnya_km' => $d->jarak_dari_sebelumnya_km,
                'estimasi_kumulatif_menit' => $d->estimasi_kumulatif_menit,
                'geometri_polyline' => $d->geometri_polyline,
                'visitasi_peserta_id' => $d->visitasi_peserta_id,
            ])->values()->all(),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function findModel(string $rencanaId, string $dosenId): ?VisitasiRencana
    {
        return VisitasiRencana::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->where('dosen_id', $dosenId)
            ->whereNull('dihapus_pada')
            ->first();
    }

    private function validateRencanaPayload(array $payload): void
    {
        $nama = trim((string) ($payload['nama_rencana'] ?? ''));

        if ($nama === '') {
            throw new InvalidArgumentException('Nama rencana wajib diisi.');
        }

        $jenis = $payload['jenis_kendaraan'] ?? 'motor';

        if (!in_array($jenis, ['motor', 'mobil'], true)) {
            throw new InvalidArgumentException('Jenis kendaraan harus motor atau mobil.');
        }
    }
}
