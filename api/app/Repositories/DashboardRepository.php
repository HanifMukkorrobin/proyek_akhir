<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Mahasiswa;
use App\Models\Wilayah;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    private const GROUP_LENGTHS = [
        'provinsi' => 6,
        'kabupaten' => 9,
    ];

    public function getSummary(?int $angkatan = null): array
    {
        $jumlahMahasiswaQuery = Mahasiswa::query();
        $this->applyAngkatanFilter($jumlahMahasiswaQuery, $angkatan);
        $jumlahMahasiswa = $jumlahMahasiswaQuery->count();

        $jumlahDataInvalidQuery = Mahasiswa::query()->where('is_valid_address', false);
        $this->applyAngkatanFilter($jumlahDataInvalidQuery, $angkatan);
        $jumlahDataInvalid = $jumlahDataInvalidQuery->count();

        $jumlahUser = User::query()->count();
        $jumlahProvinsiTerjangkau = $this->countReachedRegions(self::GROUP_LENGTHS['provinsi'], $angkatan);
        $jumlahKabupatenKotaTerjangkau = $this->countReachedRegions(self::GROUP_LENGTHS['kabupaten'], $angkatan);
        $totalProvinsi = $this->countRegionsByLength(self::GROUP_LENGTHS['provinsi']);
        $totalKabupatenKota = $this->countRegionsByLength(self::GROUP_LENGTHS['kabupaten']);
        $topProvinceRows = $this->getDistributionRows('provinsi', 1, $angkatan);

        return [
            'jumlah_mahasiswa' => $jumlahMahasiswa,
            'jumlah_data_invalid' => $jumlahDataInvalid,
            'jumlah_user_terdaftar' => $jumlahUser,
            'jumlah_provinsi_terjangkau' => $jumlahProvinsiTerjangkau,
            'jumlah_kabupaten_kota_terjangkau' => $jumlahKabupatenKotaTerjangkau,
            'total_provinsi' => $totalProvinsi,
            'total_kabupaten_kota' => $totalKabupatenKota,
            'persentase_kabupaten_kota_terjangkau' => $totalKabupatenKota > 0
                ? round(($jumlahKabupatenKotaTerjangkau / $totalKabupatenKota) * 100)
                : 0,
            'provinsi_teratas' => $topProvinceRows[0] ?? null,
        ];
    }

    public function getChartData(string $groupBy, ?int $limit = null, ?int $angkatan = null): array
    {
        $length = $this->getGroupLength($groupBy);
        $rows = $this->getDistributionRows($groupBy, $limit, $angkatan);
        $totalMahasiswaQuery = Mahasiswa::query()->where('is_valid_address', true);
        $this->applyAngkatanFilter($totalMahasiswaQuery, $angkatan);

        $categories = array_map(function (array $row) {
            return $row['nama_wilayah'];
        }, $rows);

        $data = array_map(function (array $row) {
            return $row['jumlah'];
        }, $rows);

        return [
            'group_by' => $groupBy,
            'total_mahasiswa' => $totalMahasiswaQuery->count(),
            'total_wilayah_terjangkau' => $this->countReachedRegions($length, $angkatan),
            'rows' => $rows,
            'categories' => $categories,
            'series' => [
                [
                    'name' => 'Jumlah Mahasiswa',
                    'data' => $data,
                ]
            ]
        ];
    }

    public function getWilayahTree(string $parentId = '', string $rootLevel = 'root', ?int $angkatan = null): array
    {
        $query = Wilayah::query()
            ->select([
                'wilayah_id',
                'nama',
                'kode_dukcapil',
                'latitude',
                'longitude',
                'dibuat_pada',
            ])
            ->whereNull('dihapus_pada')
            ->orderBy('wilayah_id');

        if ($parentId === '') {
            $query->whereRaw('char_length(wilayah_id) = ?', [$this->resolveRootLevelLength($rootLevel)]);
        } else {
            $expectedLength = strlen($parentId) + 3;
            $query->where('wilayah_id', 'like', $parentId . '%')
                ->whereRaw('char_length(wilayah_id) = ?', [$expectedLength]);
        }

        // Subquery for jumlah_mahasiswa
        $query->selectSub(function ($q) use ($angkatan) {
            $q->from('mahasiswa')
                ->selectRaw('COUNT(mahasiswa_id)')
                ->whereNull('dihapus_pada')
                ->where('is_valid_address', true)
                ->whereRaw('mahasiswa.wilayah_id LIKE wilayah.wilayah_id || \'%\'');

            if ($angkatan !== null) {
                $q->where('angkatan', $angkatan);
            }
        }, 'jumlah_mahasiswa');

        $rows = $query->get()->toArray();

        $ids = array_column($rows, 'wilayah_id');
        
        $hasChildMap = [];
        if (!empty($ids)) {
            $childIds = Wilayah::query()
                ->whereNull('dihapus_pada')
                ->where(function ($q) use ($ids) {
                    foreach ($ids as $id) {
                        $q->orWhere('wilayah_id', 'LIKE', $id . '___');
                    }
                })
                ->pluck('wilayah_id')
                ->toArray();
                
            foreach ($childIds as $childId) {
                $pId = substr($childId, 0, strlen($childId) - 3);
                $hasChildMap[$pId] = true;
            }
        }

        return array_map(function ($row) use ($hasChildMap) {
            $id = $row['wilayah_id'];
            $row['parent_wilayah_id'] = $this->getParentWilayahId($id);
            $row['level'] = $this->getLevel($id);
            $row['is_have_child'] = isset($hasChildMap[$id]) ? 1 : 0;
            return $row;
        }, $rows);
    }

    private function getLevel(string $wilayahId): int
    {
        $length = strlen($wilayahId);
        return $length === 0 ? 0 : (int) ceil($length / 3);
    }

    private function getParentWilayahId(string $wilayahId): ?string
    {
        if (strlen($wilayahId) <= 3) {
            return null;
        }
        return substr($wilayahId, 0, strlen($wilayahId) - 3);
    }

    private function getDistributionRows(string $groupBy, ?int $limit = null, ?int $angkatan = null): array
    {
        $length = $this->getGroupLength($groupBy);
        $totalMahasiswaQuery = Mahasiswa::query()->where('is_valid_address', true);
        $this->applyAngkatanFilter($totalMahasiswaQuery, $angkatan);
        $totalMahasiswa = $totalMahasiswaQuery->count();

        $query = Mahasiswa::query()
            ->selectRaw("LEFT(mahasiswa.wilayah_id, {$length}) as kode_wilayah")
            ->selectRaw('COUNT(mahasiswa.mahasiswa_id) as jumlah')
            ->selectRaw('w.wilayah_id as wilayah_id')
            ->selectRaw('w.nama as nama_wilayah')
            ->selectRaw('w.kode_dukcapil as kode_dukcapil')
            ->selectRaw('w.latitude as latitude')
            ->selectRaw('w.longitude as longitude')
            ->whereNotNull('mahasiswa.wilayah_id')
            ->where('mahasiswa.is_valid_address', true)
            ->whereRaw('char_length(mahasiswa.wilayah_id) >= ?', [$length])
            ->join('wilayah as w', function ($join) use ($length) {
                $join->on(DB::raw("LEFT(mahasiswa.wilayah_id, {$length})"), '=', 'w.wilayah_id')
                    ->whereNull('w.dihapus_pada');
            })
            ->groupByRaw("LEFT(mahasiswa.wilayah_id, {$length})")
            ->groupBy('w.wilayah_id', 'w.nama', 'w.kode_dukcapil', 'w.latitude', 'w.longitude')
            ->orderByDesc('jumlah')
            ->orderBy('w.nama');

        $this->applyAngkatanFilter($query, $angkatan, 'mahasiswa.angkatan');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $results = $query->get();
        $maxValue = (int) $results->max('jumlah');

        return $results
            ->values()
            ->map(function ($row, int $index) use ($totalMahasiswa, $maxValue) {
                $jumlah = (int) $row->jumlah;

                return [
                    'rank' => $index + 1,
                    'kode_wilayah' => (string) $row->kode_wilayah,
                    'wilayah_id' => (string) $row->wilayah_id,
                    'nama_wilayah' => $row->nama_wilayah,
                    'nama' => $row->nama_wilayah,
                    'kode_dukcapil' => $row->kode_dukcapil,
                    'latitude' => $row->latitude,
                    'longitude' => $row->longitude,
                    'jumlah' => $jumlah,
                    'persentase' => $totalMahasiswa > 0
                        ? round(($jumlah / $totalMahasiswa) * 100, 2)
                        : 0,
                    'bar_percent' => $maxValue > 0
                        ? round(($jumlah / $maxValue) * 100)
                        : 0,
                ];
            })
            ->toArray();
    }

    private function countReachedRegions(int $length, ?int $angkatan = null): int
    {
        $query = Mahasiswa::query()
            ->whereNotNull('wilayah_id')
            ->where('is_valid_address', true)
            ->whereRaw('char_length(wilayah_id) >= ?', [$length]);

        $this->applyAngkatanFilter($query, $angkatan);

        return (int) $query
            ->selectRaw("COUNT(DISTINCT LEFT(wilayah_id, {$length})) as aggregate")
            ->value('aggregate');
    }

    private function applyAngkatanFilter($query, ?int $angkatan, string $column = 'angkatan'): void
    {
        if ($angkatan === null) {
            return;
        }

        $query->where($column, $angkatan);
    }

    private function countRegionsByLength(int $length): int
    {
        return Wilayah::query()
            ->whereNull('dihapus_pada')
            ->whereRaw('char_length(wilayah_id) = ?', [$length])
            ->count();
    }

    private function getGroupLength(string $groupBy): int
    {
        return self::GROUP_LENGTHS[$groupBy] ?? self::GROUP_LENGTHS['provinsi'];
    }

    private function resolveRootLevelLength(string $rootLevel): int
    {
        $normalized = strtolower(trim($rootLevel));

        if (ctype_digit($normalized)) {
            $level = max(1, min(5, (int) $normalized));
            return $level * 3;
        }

        $map = [
            'root' => 3,
            'nasional' => 3,
            'provinsi' => self::GROUP_LENGTHS['provinsi'],
            'province' => self::GROUP_LENGTHS['provinsi'],
            'kabupaten' => self::GROUP_LENGTHS['kabupaten'],
            'kabupaten_kota' => self::GROUP_LENGTHS['kabupaten'],
            'kota' => self::GROUP_LENGTHS['kabupaten'],
            'kecamatan' => 12,
            'desa' => 15,
            'kelurahan' => 15,
        ];

        return $map[$normalized] ?? $map['root'];
    }
}
