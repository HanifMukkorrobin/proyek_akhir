<?php

namespace App\Repositories;

use App\Models\Mahasiswa;
use App\Models\Wilayah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DashboardMapRepository
{
    private const LEVELS = [
        'provinsi' => ['level' => 2, 'length' => 6, 'label' => 'Provinsi', 'min_zoom' => 0],
        'kabupaten' => ['level' => 3, 'length' => 9, 'label' => 'Kabupaten/Kota', 'min_zoom' => 6],
        'kecamatan' => ['level' => 4, 'length' => 12, 'label' => 'Kecamatan', 'min_zoom' => 9],
        'desa' => ['level' => 5, 'length' => 15, 'label' => 'Desa/Kelurahan', 'min_zoom' => 12],
    ];

    public function getWilayahPoints(array $filters = []): array
    {
        $levelKey = $this->resolveLevelKey($filters['level'] ?? null, $filters['zoom'] ?? null);
        $levelMeta = self::LEVELS[$levelKey];
        $nextLevelKey = $this->getNextLevelKey($levelKey);
        $length = $levelMeta['length'];
        $nextLength = $nextLevelKey !== null ? self::LEVELS[$nextLevelKey]['length'] : null;
        $parentId = $this->normalizeNullableString($filters['parent_id'] ?? null);
        $limit = $this->normalizeLimit($filters['limit'] ?? null, 1000);

        if ($parentId !== null && strlen($parentId) >= $length) {
            throw new InvalidArgumentException('parent_id harus berada di level yang lebih tinggi dari level titik yang diminta.');
        }

        $aggregate = DB::table('mahasiswa')
            ->selectRaw("LEFT(wilayah_id, {$length}) as wilayah_group_id")
            ->selectRaw('COUNT(mahasiswa_id) as jumlah_mahasiswa')
            ->selectRaw('AVG(' . $this->numericSql('latitude') . ') as avg_latitude')
            ->selectRaw('AVG(' . $this->numericSql('longitude') . ') as avg_longitude')
            ->selectRaw(
                $nextLength !== null
                    ? "MAX(CASE WHEN char_length(wilayah_id) >= {$nextLength} THEN 1 ELSE 0 END) as has_child"
                    : '0 as has_child'
            )
            ->whereNull('dihapus_pada')
            ->whereNotNull('wilayah_id')
            ->whereRaw('char_length(wilayah_id) >= ?', [$length]);

        if ($parentId !== null) {
            $aggregate->where('wilayah_id', 'like', $parentId . '%');
        }

        $aggregate->groupByRaw("LEFT(wilayah_id, {$length})");

        $query = DB::table('wilayah as w')
            ->joinSub($aggregate, 'm', function ($join) {
                $join->on('w.wilayah_id', '=', 'm.wilayah_group_id');
            })
            ->select([
                'w.wilayah_id',
                'w.nama',
                'w.kode_dukcapil',
                'w.latitude',
                'w.longitude',
                'm.jumlah_mahasiswa',
                'm.avg_latitude',
                'm.avg_longitude',
                'm.has_child',
            ])
            ->whereNull('w.dihapus_pada')
            ->whereRaw('char_length(w.wilayah_id) = ?', [$length]);

        if ($parentId !== null) {
            $query->where('w.wilayah_id', 'like', $parentId . '%');
        }

        $this->applyBoundsFilter($query, $filters);

        $rows = $query
            ->orderByDesc('m.jumlah_mahasiswa')
            ->orderBy('w.nama')
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($levelKey, $levelMeta) {
                return $this->transformWilayahPoint($row, $levelKey, $levelMeta);
            })
            ->values()
            ->toArray();

        return [
            'level' => $levelKey,
            'level_number' => $levelMeta['level'],
            'level_label' => $levelMeta['label'],
            'next_level' => $nextLevelKey,
            'parent_id' => $parentId,
            'zoom_rules' => $this->getZoomRules(),
            'total_points' => count($rows),
            'points' => $rows,
        ];
    }

    public function getMahasiswaByWilayah(string $wilayahId, array $filters = []): array
    {
        $wilayahId = trim($wilayahId);

        if ($wilayahId === '') {
            throw new InvalidArgumentException('wilayah_id wajib diisi.');
        }

        $wilayah = Wilayah::query()
            ->where('wilayah_id', $wilayahId)
            ->first();

        if ($wilayah === null) {
            throw new InvalidArgumentException('Wilayah tidak ditemukan.');
        }

        $query = Mahasiswa::query()
            ->with('wilayah')
            ->where('wilayah_id', 'like', $wilayahId . '%')
            ->orderBy('nama')
            ->orderBy('mahasiswa_id');

        $search = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder
                    ->where('nama', 'ILIKE', '%' . $search . '%')
                    ->orWhere('alamat', 'ILIKE', '%' . $search . '%')
                    ->orWhere('mahasiswa_id', 'ILIKE', '%' . $search . '%');
            });
        }

        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $pagination = paginate_builder($query, $page, $perPage);

        return [
            'wilayah' => $this->transformWilayah($wilayah),
            'mahasiswa' => array_map(function (Mahasiswa $mahasiswa) {
                return $this->transformMahasiswaPoint($mahasiswa);
            }, $pagination['data']->all()),
            'pagination' => [
                'halaman_sekarang' => $pagination['halaman_sekarang'],
                'per_halaman' => $pagination['per_halaman'],
                'total_data' => $pagination['total_data'],
                'total_halaman' => $pagination['total_halaman'],
            ],
        ];
    }

    public function searchMahasiswa(array $filters = []): array
    {
        $queryText = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));

        if ($queryText === '') {
            throw new InvalidArgumentException('Parameter q wajib diisi untuk pencarian mahasiswa.');
        }

        $limit = $this->normalizeLimit($filters['limit'] ?? null, 50);
        $wilayahId = $this->normalizeNullableString($filters['wilayah_id'] ?? null);

        $query = Mahasiswa::query()
            ->with('wilayah')
            ->where(function (Builder $builder) use ($queryText) {
                $builder
                    ->where('nama', 'ILIKE', '%' . $queryText . '%')
                    ->orWhere('alamat', 'ILIKE', '%' . $queryText . '%')
                    ->orWhere('mahasiswa_id', 'ILIKE', '%' . $queryText . '%');
            })
            ->orderBy('nama')
            ->orderBy('mahasiswa_id')
            ->limit($limit);

        if ($wilayahId !== null) {
            $query->where('wilayah_id', 'like', $wilayahId . '%');
        }

        $this->applyMahasiswaBoundsFilter($query, $filters);

        $results = $query
            ->get()
            ->map(function (Mahasiswa $mahasiswa) {
                return $this->transformMahasiswaPoint($mahasiswa);
            })
            ->values()
            ->toArray();

        return [
            'query' => $queryText,
            'total_results' => count($results),
            'results' => $results,
        ];
    }

    private function transformWilayahPoint($row, string $levelKey, array $levelMeta): array
    {
        $latitude = $this->normalizeCoordinate($row->latitude ?? null);
        $longitude = $this->normalizeCoordinate($row->longitude ?? null);
        $avgLatitude = $this->normalizeCoordinate($row->avg_latitude ?? null);
        $avgLongitude = $this->normalizeCoordinate($row->avg_longitude ?? null);

        return [
            'wilayah_id' => (string) $row->wilayah_id,
            'parent_wilayah_id' => $this->getParentWilayahId((string) $row->wilayah_id),
            'level' => $levelMeta['level'],
            'level_key' => $levelKey,
            'level_label' => $levelMeta['label'],
            'nama' => $row->nama,
            'kode_dukcapil' => $row->kode_dukcapil,
            'latitude' => $latitude ?? $avgLatitude,
            'longitude' => $longitude ?? $avgLongitude,
            'coordinate_source' => ($latitude !== null && $longitude !== null) ? 'wilayah' : 'mahasiswa_average',
            'jumlah_mahasiswa' => (int) $row->jumlah_mahasiswa,
            'has_child' => (bool) ($row->has_child ?? false),
            'next_level' => $this->getNextLevelKey($levelKey),
        ];
    }

    private function transformMahasiswaPoint(Mahasiswa $mahasiswa): array
    {
        $mahasiswa->loadMissing('wilayah');

        $latitude = $this->normalizeCoordinate($mahasiswa->latitude);
        $longitude = $this->normalizeCoordinate($mahasiswa->longitude);
        $wilayahLatitude = $this->normalizeCoordinate($mahasiswa->wilayah?->latitude);
        $wilayahLongitude = $this->normalizeCoordinate($mahasiswa->wilayah?->longitude);

        return [
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'nama' => $mahasiswa->nama,
            'alamat' => $mahasiswa->alamat,
            'wilayah_id' => $mahasiswa->wilayah_id,
            'latitude' => $latitude ?? $wilayahLatitude,
            'longitude' => $longitude ?? $wilayahLongitude,
            'coordinate_source' => ($latitude !== null && $longitude !== null) ? 'mahasiswa' : 'wilayah',
            'wilayah' => $this->transformWilayah($mahasiswa->wilayah),
        ];
    }

    private function transformWilayah(?Wilayah $wilayah)
    {
        if ($wilayah === null) {
            return (object) [];
        }

        $wilayahId = (string) $wilayah->wilayah_id;
        $level = $this->getLevelFromId($wilayahId);

        return [
            'wilayah_id' => $wilayahId,
            'parent_wilayah_id' => $this->getParentWilayahId($wilayahId),
            'level' => $level,
            'level_key' => $this->getLevelKeyFromNumber($level),
            'level_label' => $this->getLevelLabelFromNumber($level),
            'nama' => $wilayah->nama,
            'kode_dukcapil' => $wilayah->kode_dukcapil,
            'latitude' => $this->normalizeCoordinate($wilayah->latitude),
            'longitude' => $this->normalizeCoordinate($wilayah->longitude),
        ];
    }

    private function applyBoundsFilter($query, array $filters): void
    {
        $minLat = $this->normalizeCoordinate($filters['min_lat'] ?? null);
        $maxLat = $this->normalizeCoordinate($filters['max_lat'] ?? null);
        $minLng = $this->normalizeCoordinate($filters['min_lng'] ?? $filters['min_lon'] ?? null);
        $maxLng = $this->normalizeCoordinate($filters['max_lng'] ?? $filters['max_lon'] ?? null);

        if ($minLat !== null && $maxLat !== null) {
            $query->whereRaw('COALESCE(' . $this->numericSql('w.latitude') . ', m.avg_latitude) BETWEEN ? AND ?', [
                min($minLat, $maxLat),
                max($minLat, $maxLat),
            ]);
        }

        if ($minLng !== null && $maxLng !== null) {
            $query->whereRaw('COALESCE(' . $this->numericSql('w.longitude') . ', m.avg_longitude) BETWEEN ? AND ?', [
                min($minLng, $maxLng),
                max($minLng, $maxLng),
            ]);
        }
    }

    private function applyMahasiswaBoundsFilter(Builder $query, array $filters): void
    {
        $minLat = $this->normalizeCoordinate($filters['min_lat'] ?? null);
        $maxLat = $this->normalizeCoordinate($filters['max_lat'] ?? null);
        $minLng = $this->normalizeCoordinate($filters['min_lng'] ?? $filters['min_lon'] ?? null);
        $maxLng = $this->normalizeCoordinate($filters['max_lng'] ?? $filters['max_lon'] ?? null);

        if ($minLat !== null && $maxLat !== null) {
            $query->whereRaw($this->numericSql('latitude') . ' BETWEEN ? AND ?', [
                min($minLat, $maxLat),
                max($minLat, $maxLat),
            ]);
        }

        if ($minLng !== null && $maxLng !== null) {
            $query->whereRaw($this->numericSql('longitude') . ' BETWEEN ? AND ?', [
                min($minLng, $maxLng),
                max($minLng, $maxLng),
            ]);
        }
    }

    private function resolveLevelKey($level, $zoom): string
    {
        $normalizedLevel = strtolower(trim((string) ($level ?? '')));

        if ($normalizedLevel !== '') {
            if (ctype_digit($normalizedLevel)) {
                $levelNumber = (int) $normalizedLevel;

                foreach (self::LEVELS as $key => $meta) {
                    if ($meta['level'] === $levelNumber) {
                        return $key;
                    }
                }
            }

            $aliases = [
                'province' => 'provinsi',
                'kabupaten_kota' => 'kabupaten',
                'kota' => 'kabupaten',
                'city' => 'kabupaten',
                'regency' => 'kabupaten',
                'kelurahan' => 'desa',
                'village' => 'desa',
            ];

            $normalizedLevel = $aliases[$normalizedLevel] ?? $normalizedLevel;

            if (isset(self::LEVELS[$normalizedLevel])) {
                return $normalizedLevel;
            }

            throw new InvalidArgumentException('Parameter level harus berupa provinsi, kabupaten, kecamatan, atau desa.');
        }

        $zoomValue = is_numeric($zoom) ? (float) $zoom : 0.0;
        $resolved = 'provinsi';

        foreach (self::LEVELS as $key => $meta) {
            if ($zoomValue >= $meta['min_zoom']) {
                $resolved = $key;
            }
        }

        return $resolved;
    }

    private function getZoomRules(): array
    {
        return array_map(function (string $key, array $meta) {
            return [
                'level' => $key,
                'level_number' => $meta['level'],
                'level_label' => $meta['label'],
                'min_zoom' => $meta['min_zoom'],
            ];
        }, array_keys(self::LEVELS), self::LEVELS);
    }

    private function getNextLevelKey(string $levelKey): ?string
    {
        $keys = array_keys(self::LEVELS);
        $index = array_search($levelKey, $keys, true);

        if ($index === false || !isset($keys[$index + 1])) {
            return null;
        }

        return $keys[$index + 1];
    }

    private function getLevelFromId(string $wilayahId): int
    {
        $length = strlen($wilayahId);
        return $length === 0 ? 0 : (int) ceil($length / 3);
    }

    private function getLevelKeyFromNumber(int $level): ?string
    {
        foreach (self::LEVELS as $key => $meta) {
            if ($meta['level'] === $level) {
                return $key;
            }
        }

        return null;
    }

    private function getLevelLabelFromNumber(int $level): string
    {
        foreach (self::LEVELS as $meta) {
            if ($meta['level'] === $level) {
                return $meta['label'];
            }
        }

        return $level <= 1 ? 'Root' : 'Wilayah';
    }

    private function getParentWilayahId(string $wilayahId): ?string
    {
        if (strlen($wilayahId) <= 3) {
            return null;
        }

        return substr($wilayahId, 0, strlen($wilayahId) - 3);
    }

    private function normalizeCoordinate($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function normalizeLimit($value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Parameter limit harus berupa angka.');
        }

        return max(1, min(1000, (int) $value));
    }

    private function normalizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function numericSql(string $column): string
    {
        return "CASE WHEN {$column} ~ '^-?[0-9]+(\\.[0-9]+)?$' THEN {$column}::numeric END";
    }
}
