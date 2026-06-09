<?php

namespace App\Repositories;

use App\Models\VisitasiPeserta;
use App\Models\VisitasiRencana;
use App\Models\VisitasiRute;
use App\Models\VisitasiRuteDetail;
use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SimulasiRuteRepository
 *
 * Menangani engine simulasi rute, penyimpanan hasil,
 * dan export (print-preview HTML untuk PDF, Excel rekap).
 */
class SimulasiRuteRepository
{
    private OsrmRoutingRepository $osrm;

    private VisitasiRepository $visitasiRepo;

    public function __construct(OsrmRoutingRepository $osrm, VisitasiRepository $visitasiRepo)
    {
        $this->osrm = $osrm;
        $this->visitasiRepo = $visitasiRepo;
    }

    // -------------------------------------------------------------------------
    // Simulasi
    // -------------------------------------------------------------------------

    public function createAndSimulate(array $payload, string $dosenId): array
    {
        // 1. Validasi input dasar
        $nama = trim((string) ($payload['nama_rencana'] ?? ''));
        if ($nama === '') {
            throw new \InvalidArgumentException('Nama rencana wajib diisi.');
        }

        $jenisKendaraan = trim((string) ($payload['jenis_kendaraan'] ?? 'motor'));
        if (!in_array($jenisKendaraan, ['motor', 'mobil'], true)) {
            throw new \InvalidArgumentException('Jenis kendaraan harus motor atau mobil.');
        }

        $lat = $payload['titik_awal_latitude'] ?? null;
        $lng = $payload['titik_awal_longitude'] ?? null;
        if ($lat === null || $lng === null) {
            throw new \InvalidArgumentException('Koordinat titik awal (latitude dan longitude) wajib diisi.');
        }

        $mhsIds = $payload['mahasiswa_ids'] ?? [];
        if (!is_array($mhsIds) || count($mhsIds) === 0) {
            throw new \InvalidArgumentException('Minimal harus memilih 1 mahasiswa peserta.');
        }
        if (count($mhsIds) > 5) {
            throw new \InvalidArgumentException('Maksimal mahasiswa per rencana adalah 5 orang.');
        }

        // 2. Buat rencana & peserta dalam transaksi
        $rencanaId = \Illuminate\Support\Facades\DB::transaction(function () use ($payload, $dosenId, $nama, $jenisKendaraan, $lat, $lng, $mhsIds) {
            $rencana = VisitasiRencana::query()->create([
                'visitasi_rencana_id' => (string) Str::uuid(),
                'dosen_id' => $dosenId,
                'dibuat_oleh_user_id' => $dosenId,
                'nama_rencana' => $nama,
                'deskripsi' => trim((string) ($payload['deskripsi'] ?? '')) ?: null,
                'titik_awal_latitude' => (float) $lat,
                'titik_awal_longitude' => (float) $lng,
                'titik_awal_label' => trim((string) ($payload['titik_awal_label'] ?? '')) ?: 'Titik Awal Terpilih',
                'jenis_kendaraan' => $jenisKendaraan,
                'lewat_tol' => (bool) ($payload['lewat_tol'] ?? false),
                'status' => 'draft',
                'dibuat_pada' => Carbon::now(),
            ]);

            foreach ($mhsIds as $mhsId) {
                // Cek apakah mahasiswa exist
                $exists = \Illuminate\Support\Facades\DB::table('mahasiswa')
                    ->where('mahasiswa_id', $mhsId)
                    ->whereNull('dihapus_pada')
                    ->exists();

                if (!$exists) {
                    throw new \InvalidArgumentException("Mahasiswa dengan ID {$mhsId} tidak ditemukan.");
                }

                VisitasiPeserta::query()->create([
                    'visitasi_peserta_id' => (string) Str::uuid(),
                    'visitasi_rencana_id' => $rencana->visitasi_rencana_id,
                    'mahasiswa_id' => $mhsId,
                    'urutan' => 0,
                    'dibuat_pada' => Carbon::now(),
                ]);
            }

            return $rencana->visitasi_rencana_id;
        });

        // 3. Jalankan simulasi rute langsung
        return $this->simulate($rencanaId, $dosenId);
    }

    /**
     * Jalankan simulasi rute untuk rencana tertentu.
     * Menggunakan OSRM /trip (round-trip TSP) dengan fallback Haversine.
     */
    public function simulate(string $rencanaId, string $dosenId): array
    {
        // Load rencana + validasi kepemilikan
        $rencana = VisitasiRencana::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->where('dosen_id', $dosenId)
            ->whereNull('dihapus_pada')
            ->first();

        if ($rencana === null) {
            throw new RuntimeException('Rencana tidak ditemukan.');
        }

        if ($rencana->status === 'selesai') {
            throw new RuntimeException('Rencana yang sudah selesai tidak dapat disimulasikan ulang.');
        }

        // Validasi titik awal
        if ($rencana->titik_awal_latitude === null || $rencana->titik_awal_longitude === null) {
            throw new RuntimeException('Titik awal belum diset. Harap tentukan lokasi keberangkatan dosen.');
        }

        // Load peserta aktif
        $peserta = VisitasiPeserta::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->whereNull('dihapus_pada')
            ->with('mahasiswa')
            ->get();

        if ($peserta->isEmpty()) {
            throw new RuntimeException('Tidak ada peserta dalam rencana ini.');
        }

        // Pastikan semua peserta memiliki koordinat valid
        foreach ($peserta as $p) {
            if ($p->mahasiswa === null || $p->mahasiswa->latitude === null || $p->mahasiswa->longitude === null) {
                throw new RuntimeException(
                    'Mahasiswa "' . ($p->mahasiswa->nama ?? $p->mahasiswa_id) . '" tidak memiliki koordinat valid.'
                );
            }
        }

        // Build waypoints: titik_awal dulu, lalu peserta
        $waypoints = [[
            'lat' => (float) $rencana->titik_awal_latitude,
            'lng' => (float) $rencana->titik_awal_longitude,
            'label' => $rencana->titik_awal_label ?? 'Titik Awal Dosen',
            'tipe' => 'titik_awal',
            'peserta_id' => null,
        ]];

        foreach ($peserta as $p) {
            $waypoints[] = [
                'lat' => (float) $p->mahasiswa->latitude,
                'lng' => (float) $p->mahasiswa->longitude,
                'label' => $p->mahasiswa->nama,
                'tipe' => 'mahasiswa',
                'peserta_id' => $p->visitasi_peserta_id,
                'mahasiswa_id' => $p->mahasiswa_id,
            ];
        }

        // Resolve OSRM profile
        $profileOptions = $this->osrm->resolveProfileOptions(
            $rencana->jenis_kendaraan,
            (bool) $rencana->lewat_tol
        );

        // Buat record visitasi_rute (status pending)
        $rute = VisitasiRute::query()->create([
            'visitasi_rute_id' => (string) Str::uuid(),
            'visitasi_rencana_id' => $rencanaId,
            'metode_kalkulasi' => 'osrm_nearest_neighbor',
            'osrm_profile' => $profileOptions['profile'],
            'status' => 'pending',
            'parameter_input' => [
                'jenis_kendaraan' => $rencana->jenis_kendaraan,
                'lewat_tol' => $rencana->lewat_tol,
                'exclude_motorway' => $profileOptions['exclude_motorway'],
                'jumlah_peserta' => $peserta->count(),
                'titik_awal' => [
                    'latitude' => $rencana->titik_awal_latitude,
                    'longitude' => $rencana->titik_awal_longitude,
                    'label' => $rencana->titik_awal_label,
                ],
            ],
            'dibuat_pada' => Carbon::now(),
        ]);

        try {
            // Panggil OSRM
            $result = $this->osrm->getTrip(
                $waypoints,
                $profileOptions['profile'],
                $profileOptions['exclude_motorway']
            );

            // Simpan hasil detail ke visitasi_rute_detail
            $this->saveRuteDetail($rute->visitasi_rute_id, $rencanaId, $result, $waypoints, $rencana);

            // Update visitasi_rute
            $rute->status = 'success';
            $rute->total_jarak_km = $result['total_distance_km'];
            $rute->total_estimasi_menit = $result['total_duration_minutes'];
            $rute->hasil_osrm_raw = $result['engine'] === 'haversine_fallback' ? null : ($result['raw'] ?? null);
            $rute->save();

            // Update rencana: status → siap, perkiraan total
            $rencana->status = 'siap';
            $rencana->perkiraan_total_jarak_km = $result['total_distance_km'];
            $rencana->perkiraan_total_menit = $result['total_duration_minutes'];
            $rencana->diubah_pada = Carbon::now();
            $rencana->save();

            // Update urutan di visitasi_peserta
            $this->updatePesertaUrutan($rute->visitasi_rute_id, $rencanaId);

            // Load detail untuk response
            $rute->load('detail');

             return [
                'visitasi_rute_id' => $rute->visitasi_rute_id,
                'visitasi_rencana_id' => $rencanaId,
                'engine' => $result['engine'],
                'metode_kalkulasi' => $rute->metode_kalkulasi,
                'osrm_profile' => $rute->osrm_profile,
                'total_jarak_km' => $rute->total_jarak_km,
                'total_estimasi_menit' => $rute->total_estimasi_menit,
                'status' => 'success',
                'hasil_osrm_raw' => $rute->hasil_osrm_raw,
                'detail' => $this->transformDetailList($rute->detail->all(), $peserta->keyBy('visitasi_peserta_id')->all()),
            ];
        } catch (\Throwable $e) {
            $rute->status = 'failed';
            $rute->error_message = $e->getMessage();
            $rute->save();

            throw new RuntimeException('Simulasi gagal: ' . $e->getMessage());
        }
    }

    /**
     * Ambil rute terakhir yang berhasil untuk satu rencana.
     */
    public function getLatestRute(string $rencanaId, string $dosenId): ?array
    {
        $this->assertDosenOwnsRencana($rencanaId, $dosenId);

        $rute = VisitasiRute::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->where('status', 'success')
            ->with(['detail'])
            ->orderByDesc('dibuat_pada')
            ->first();

        if ($rute === null) {
            return null;
        }

        $pesertaMap = VisitasiPeserta::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->with('mahasiswa.wilayah')
            ->get()
            ->keyBy('visitasi_peserta_id')
            ->all();

        return [
            'visitasi_rute_id' => $rute->visitasi_rute_id,
            'visitasi_rencana_id' => $rencanaId,
            'metode_kalkulasi' => $rute->metode_kalkulasi,
            'osrm_profile' => $rute->osrm_profile,
            'total_jarak_km' => $rute->total_jarak_km,
            'total_estimasi_menit' => $rute->total_estimasi_menit,
            'status' => $rute->status,
            'dibuat_pada' => $rute->dibuat_pada,
            'hasil_osrm_raw' => $rute->hasil_osrm_raw,
            'detail' => $this->transformDetailList($rute->detail->all(), $pesertaMap),
        ];
    }

    /**
     * Riwayat semua simulasi untuk satu rencana.
     */
    public function getRuteHistory(string $rencanaId, string $dosenId): array
    {
        $this->assertDosenOwnsRencana($rencanaId, $dosenId);

        $rutes = VisitasiRute::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->orderByDesc('dibuat_pada')
            ->get();

        return $rutes->map(fn($r) => [
            'visitasi_rute_id' => $r->visitasi_rute_id,
            'metode_kalkulasi' => $r->metode_kalkulasi,
            'osrm_profile' => $r->osrm_profile,
            'total_jarak_km' => $r->total_jarak_km,
            'total_estimasi_menit' => $r->total_estimasi_menit,
            'status' => $r->status,
            'error_message' => $r->error_message,
            'dibuat_pada' => $r->dibuat_pada,
        ])->values()->all();
    }

    // -------------------------------------------------------------------------
    // Log Simulasi (Admin)
    // -------------------------------------------------------------------------

    /**
     * List semua simulasi untuk tampilan admin /log-simulasi.
     */
    public function getLogSimulasi(array $filters = []): array
    {
        $query = VisitasiRute::query()
            ->join('visitasi_rencana', 'visitasi_rute.visitasi_rencana_id', '=', 'visitasi_rencana.visitasi_rencana_id')
            ->join('users', 'visitasi_rencana.dosen_id', '=', 'users.user_id')
            ->select([
                'visitasi_rute.visitasi_rute_id',
                'visitasi_rute.visitasi_rencana_id',
                'visitasi_rute.metode_kalkulasi',
                'visitasi_rute.osrm_profile',
                'visitasi_rute.total_jarak_km',
                'visitasi_rute.total_estimasi_menit',
                'visitasi_rute.status',
                'visitasi_rute.error_message',
                'visitasi_rute.dibuat_pada',
                'visitasi_rencana.nama_rencana',
                'visitasi_rencana.jenis_kendaraan',
                'visitasi_rencana.lewat_tol',
                'visitasi_rencana.dosen_id',
                'users.nama as dosen_nama',
                'users.username as dosen_username',
            ])
            ->whereNull('visitasi_rencana.dihapus_pada')
            ->orderByDesc('visitasi_rute.dibuat_pada');

        // Filter dosen
        $dosenId = trim((string) ($filters['dosen_id'] ?? ''));

        if ($dosenId !== '') {
            $query->where('visitasi_rencana.dosen_id', $dosenId);
        }

        // Filter status
        $status = trim((string) ($filters['status'] ?? ''));

        if ($status !== '') {
            $query->where('visitasi_rute.status', $status);
        }

        // Filter tanggal
        $tanggalMulai = trim((string) ($filters['tanggal_mulai'] ?? ''));

        if ($tanggalMulai !== '') {
            $query->where('visitasi_rute.dibuat_pada', '>=', $tanggalMulai . ' 00:00:00');
        }

        $tanggalAkhir = trim((string) ($filters['tanggal_akhir'] ?? ''));

        if ($tanggalAkhir !== '') {
            $query->where('visitasi_rute.dibuat_pada', '<=', $tanggalAkhir . ' 23:59:59');
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($filters['per_page'] ?? 15)));

        $pagination = paginate_builder($query, $page, $perPage);

        return [
            'data' => array_map(fn($row) => (array) $row, $pagination['data']->all()),
            'halaman_sekarang' => $pagination['halaman_sekarang'],
            'per_halaman' => $pagination['per_halaman'],
            'total_data' => $pagination['total_data'],
            'total_halaman' => $pagination['total_halaman'],
        ];
    }

    // -------------------------------------------------------------------------
    // Export
    // -------------------------------------------------------------------------

    /**
     * Generate data untuk halaman print-preview HTML (PDF via browser).
     */
    public function getPrintData(string $ruteId, string $dosenId): array
    {
        $rute = VisitasiRute::query()
            ->where('visitasi_rute_id', $ruteId)
            ->with(['detail', 'rencana'])
            ->first();

        if ($rute === null) {
            throw new RuntimeException('Data rute tidak ditemukan.');
        }

        // Validasi kepemilikan via rencana
        if ($rute->rencana === null || $rute->rencana->dosen_id !== $dosenId) {
            throw new RuntimeException('Akses ditolak.');
        }

        $pesertaMap = VisitasiPeserta::query()
            ->where('visitasi_rencana_id', $rute->visitasi_rencana_id)
            ->with('mahasiswa.wilayah')
            ->get()
            ->keyBy('visitasi_peserta_id')
            ->all();

        return [
            'rencana' => $this->visitasiRepo->transformRencana($rute->rencana),
            'rute' => [
                'visitasi_rute_id' => $rute->visitasi_rute_id,
                'metode_kalkulasi' => $rute->metode_kalkulasi,
                'total_jarak_km' => $rute->total_jarak_km,
                'total_estimasi_menit' => $rute->total_estimasi_menit,
                'dibuat_pada' => $rute->dibuat_pada,
            ],
            'detail' => $this->transformDetailList($rute->detail->all(), $pesertaMap),
        ];
    }

    /**
     * Generate Excel rekap semua rencana + simulasi terakhir milik dosen.
     */
    public function exportRekapXlsx(string $dosenId): string
    {
        $rencanas = VisitasiRencana::query()
            ->where('dosen_id', $dosenId)
            ->whereNull('dihapus_pada')
            ->withCount(['peserta' => fn($q) => $q->whereNull('dihapus_pada')])
            ->with(['rute' => fn($q) => $q->where('status', 'success')->orderByDesc('dibuat_pada')->limit(1)])
            ->orderByDesc('dibuat_pada')
            ->get();

        $rows = [
            [
                'No',
                'Nama Rencana',
                'Status',
                'Jenis Kendaraan',
                'Lewat Tol',
                'Jumlah Peserta',
                'Total Jarak (km)',
                'Total Estimasi (menit)',
                'Tanggal Simulasi Terakhir',
                'Tanggal Dibuat',
            ],
        ];

        foreach ($rencanas as $idx => $rencana) {
            $latestRute = $rencana->rute->first();
            $rows[] = [
                $idx + 1,
                $rencana->nama_rencana,
                $rencana->status,
                $rencana->jenis_kendaraan,
                $rencana->lewat_tol ? 'Ya' : 'Tidak',
                $rencana->peserta_count ?? 0,
                $latestRute ? $latestRute->total_jarak_km : '-',
                $latestRute ? $latestRute->total_estimasi_menit : '-',
                $latestRute ? $latestRute->dibuat_pada : '-',
                $rencana->dibuat_pada,
            ];
        }

        return $this->buildSimpleXlsx($rows);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function saveRuteDetail(
        string $ruteId,
        string $rencanaId,
        array $result,
        array $originalWaypoints,
        VisitasiRencana $rencana
    ): void {
        $orderedWaypoints = $result['ordered_waypoints'];
        $legs = $result['legs'];
        $cumulativeMinutes = 0;

        // Titik awal (urutan 0)
        VisitasiRuteDetail::query()->create([
            'visitasi_rute_detail_id' => (string) Str::uuid(),
            'visitasi_rute_id' => $ruteId,
            'visitasi_rencana_id' => $rencanaId,
            'visitasi_peserta_id' => null,
            'tipe_titik' => 'titik_awal',
            'urutan_kunjungan' => 0,
            'latitude' => $rencana->titik_awal_latitude,
            'longitude' => $rencana->titik_awal_longitude,
            'label' => $rencana->titik_awal_label ?? 'Titik Awal Dosen',
            'estimasi_ke_sini_menit' => 0,
            'jarak_dari_sebelumnya_km' => 0,
            'estimasi_kumulatif_menit' => 0,
            'geometri_polyline' => null,
        ]);

        // Titik kunjungan mahasiswa (urutan 1..N)
        foreach ($orderedWaypoints as $idx => $wp) {
            if (($wp['tipe'] ?? '') === 'titik_awal') {
                continue; // Skip titik awal di ordered_waypoints
            }

            $leg = $legs[$idx - 1] ?? null;
            $jarakKm = $leg ? (float) $leg['jarak_km'] : 0;
            $durasiMenit = $leg ? (int) $leg['durasi_menit'] : 0;
            $cumulativeMinutes += $durasiMenit;

            VisitasiRuteDetail::query()->create([
                'visitasi_rute_detail_id' => (string) Str::uuid(),
                'visitasi_rute_id' => $ruteId,
                'visitasi_rencana_id' => $rencanaId,
                'visitasi_peserta_id' => $wp['peserta_id'] ?? null,
                'tipe_titik' => 'mahasiswa',
                'urutan_kunjungan' => $idx,
                'latitude' => $wp['lat'],
                'longitude' => $wp['lng'],
                'label' => $wp['label'] ?? null,
                'estimasi_ke_sini_menit' => $durasiMenit,
                'jarak_dari_sebelumnya_km' => $jarakKm,
                'estimasi_kumulatif_menit' => $cumulativeMinutes,
                'geometri_polyline' => $leg['geometri_polyline'] ?? null,
            ]);
        }

        // Titik kembali ke awal (urutan N+1)
        $lastLeg = end($legs) ?: null;
        $returnJarak = $lastLeg ? (float) $lastLeg['jarak_km'] : 0;
        $returnMenit = $lastLeg ? (int) $lastLeg['durasi_menit'] : 0;
        $cumulativeMinutes += $returnMenit;

        VisitasiRuteDetail::query()->create([
            'visitasi_rute_detail_id' => (string) Str::uuid(),
            'visitasi_rute_id' => $ruteId,
            'visitasi_rencana_id' => $rencanaId,
            'visitasi_peserta_id' => null,
            'tipe_titik' => 'kembali',
            'urutan_kunjungan' => count($orderedWaypoints),
            'latitude' => $rencana->titik_awal_latitude,
            'longitude' => $rencana->titik_awal_longitude,
            'label' => 'Kembali ke ' . ($rencana->titik_awal_label ?? 'Titik Awal'),
            'estimasi_ke_sini_menit' => $returnMenit,
            'jarak_dari_sebelumnya_km' => $returnJarak,
            'estimasi_kumulatif_menit' => $cumulativeMinutes,
            'geometri_polyline' => $lastLeg['geometri_polyline'] ?? null,
        ]);
    }

    private function updatePesertaUrutan(string $ruteId, string $rencanaId): void
    {
        $details = VisitasiRuteDetail::query()
            ->where('visitasi_rute_id', $ruteId)
            ->where('tipe_titik', 'mahasiswa')
            ->get();

        foreach ($details as $detail) {
            if ($detail->visitasi_peserta_id === null) {
                continue;
            }

            VisitasiPeserta::query()
                ->where('visitasi_peserta_id', $detail->visitasi_peserta_id)
                ->update(['urutan' => $detail->urutan_kunjungan]);
        }
    }

    private function transformDetailList(array $details, array $pesertaMap): array
    {
        return array_map(function ($detail) use ($pesertaMap) {
            $peserta = isset($detail->visitasi_peserta_id)
                ? ($pesertaMap[$detail->visitasi_peserta_id] ?? null)
                : null;

            return [
                'visitasi_rute_detail_id' => $detail->visitasi_rute_detail_id,
                'tipe_titik' => $detail->tipe_titik,
                'urutan_kunjungan' => $detail->urutan_kunjungan,
                'label' => $detail->label,
                'latitude' => $detail->latitude,
                'longitude' => $detail->longitude,
                'estimasi_ke_sini_menit' => $detail->estimasi_ke_sini_menit,
                'jarak_dari_sebelumnya_km' => $detail->jarak_dari_sebelumnya_km,
                'estimasi_kumulatif_menit' => $detail->estimasi_kumulatif_menit,
                'geometri_polyline' => $detail->geometri_polyline,
                'visitasi_peserta_id' => $detail->visitasi_peserta_id,
                'mahasiswa' => $peserta && $peserta->mahasiswa ? [
                    'mahasiswa_id' => $peserta->mahasiswa->mahasiswa_id,
                    'nama' => $peserta->mahasiswa->nama,
                    'alamat' => $peserta->mahasiswa->alamat,
                    'wilayah' => $peserta->mahasiswa->wilayah
                        ? ['nama' => $peserta->mahasiswa->wilayah->nama]
                        : null,
                ] : null,
            ];
        }, $details);
    }

    private function assertDosenOwnsRencana(string $rencanaId, string $dosenId): void
    {
        $exists = VisitasiRencana::query()
            ->where('visitasi_rencana_id', $rencanaId)
            ->where('dosen_id', $dosenId)
            ->whereNull('dihapus_pada')
            ->exists();

        if (!$exists) {
            throw new RuntimeException('Rencana tidak ditemukan.');
        }
    }

    /**
     * Build XLSX binary dari array of rows — reuse pola dari MahasiswaRepository.
     */
    private function buildSimpleXlsx(array $rows): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'visitasi-rekap-');

        if ($tempPath === false) {
            throw new \RuntimeException('Gagal membuat file sementara untuk XLSX.');
        }

        $zipPath = $tempPath . '.xlsx';
        rename($tempPath, $zipPath);

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat file XLSX.');
        }

        // Shared strings
        $strings = [];
        $stringIndex = [];

        $getCellValue = function ($value) use (&$strings, &$stringIndex) {
            $str = (string) $value;

            if (!isset($stringIndex[$str])) {
                $stringIndex[$str] = count($strings);
                $strings[] = $str;
            }

            return $stringIndex[$str];
        };

        // Build sheet XML
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $sheetXml .= '<sheetData>';

        $colLetters = range('A', 'Z');

        foreach ($rows as $rowIdx => $row) {
            $rowNum = $rowIdx + 1;
            $sheetXml .= '<row r="' . $rowNum . '">';

            foreach ($row as $colIdx => $cellValue) {
                $col = $colLetters[$colIdx] ?? chr(65 + $colIdx);
                $cellRef = $col . $rowNum;
                $si = $getCellValue($cellValue);
                $sheetXml .= '<c r="' . $cellRef . '" t="s"><v>' . $si . '</v></c>';
            }

            $sheetXml .= '</row>';
        }

        $sheetXml .= '</sheetData></worksheet>';

        // Shared strings XML
        $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';

        foreach ($strings as $s) {
            $ssXml .= '<si><t>' . htmlspecialchars((string) $s, ENT_XML1, 'UTF-8') . '</t></si>';
        }

        $ssXml .= '</sst>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>';

        $relsMain = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $relsWorkbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Rekap Visitasi" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $relsMain);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relsWorkbook);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);
        $zip->close();

        $content = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        return $content;
    }
}
