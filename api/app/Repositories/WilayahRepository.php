<?php

namespace App\Repositories;

use App\Models\Wilayah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WilayahRepository
{
    public function getTree(string $parentId = ''): array
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
            $query->whereRaw('char_length(wilayah_id) = 3');
        } else {
            $expectedLength = strlen($parentId) + 3;
            $query->where('wilayah_id', 'like', $parentId . '%')
                ->whereRaw('char_length(wilayah_id) = ?', [$expectedLength]);
        }

        $rows = $query->get()->toArray();

        $ids = array_column($rows, 'wilayah_id');
        
        $hasChildMap = [];
        if (!empty($ids)) {
            // Ambil semua ID anak langsung dari node yang sedang ditampilkan
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
            $row['is_have_child'] = isset($hasChildMap[$id]) && $hasChildMap[$id] > 0 ? 1 : 0;
            return $row;
        }, $rows);
    }

    public function create(array $payload): array
    {
        $parentId = trim((string) ($payload['parent_id'] ?? ''));
        
        if ($parentId === '') {
            $maxId = Wilayah::query()
                ->withTrashed()
                ->whereRaw('char_length(wilayah_id) = 3')
                ->max('wilayah_id');
                
            $newNumber = $maxId ? ((int)$maxId) + 1 : 1;
            $newId = str_pad((string)$newNumber, 3, '0', STR_PAD_LEFT);
        } else {
            $expectedLength = strlen($parentId) + 3;
            $maxId = Wilayah::query()
                ->withTrashed()
                ->where('wilayah_id', 'like', $parentId . '%')
                ->whereRaw('char_length(wilayah_id) = ?', [$expectedLength])
                ->max('wilayah_id');
                
            if ($maxId) {
                $last3 = substr($maxId, -3);
                $newNumber = ((int)$last3) + 1;
                $newId = $parentId . str_pad((string)$newNumber, 3, '0', STR_PAD_LEFT);
            } else {
                $newId = $parentId . '001';
            }
        }

        $wilayah = Wilayah::query()->create([
            'wilayah_id' => $newId,
            'nama' => trim((string) ($payload['nama'] ?? '')),
            'kode_dukcapil' => $payload['kode_dukcapil'] ?? null,
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'dibuat_oleh_user_id' => $payload['dibuat_oleh_user_id'] ?? null,
            'diubah_oleh_user_id' => $payload['diubah_oleh_user_id'] ?? null,
        ]);

        return $this->transform($wilayah);
    }

    public function update(string $wilayahId, array $payload): ?array
    {
        $wilayah = Wilayah::query()->where('wilayah_id', $wilayahId)->first();

        if ($wilayah === null) {
            return null;
        }

        if (array_key_exists('nama', $payload)) {
            $wilayah->nama = trim((string) $payload['nama']);
        }
        if (array_key_exists('kode_dukcapil', $payload)) {
            $wilayah->kode_dukcapil = $payload['kode_dukcapil'];
        }
        if (array_key_exists('latitude', $payload)) {
            $wilayah->latitude = $payload['latitude'];
        }
        if (array_key_exists('longitude', $payload)) {
            $wilayah->longitude = $payload['longitude'];
        }
        if (array_key_exists('diubah_oleh_user_id', $payload)) {
            $wilayah->diubah_oleh_user_id = $payload['diubah_oleh_user_id'];
        }

        $wilayah->save();

        return $this->transform($wilayah);
    }

    public function delete(string $wilayahId, ?int $deletedByUserId = null): bool
    {
        $wilayah = Wilayah::query()->where('wilayah_id', $wilayahId)->first();

        if ($wilayah === null) {
            return false;
        }

        if ($deletedByUserId !== null) {
            $wilayah->dihapus_oleh_user_id = $deletedByUserId;
            $wilayah->save();
        }

        $wilayah->delete();

        return true;
    }

    private function transform(Wilayah $wilayah): array
    {
        $id = $wilayah->wilayah_id;
        return [
            'wilayah_id' => $id,
            'parent_wilayah_id' => $this->getParentWilayahId($id),
            'level' => $this->getLevel($id),
            'nama' => $wilayah->nama,
            'kode_dukcapil' => $wilayah->kode_dukcapil,
            'latitude' => $wilayah->latitude,
            'longitude' => $wilayah->longitude,
            'dibuat_pada' => $wilayah->dibuat_pada,
            'diubah_pada' => $wilayah->diubah_pada,
        ];
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
}
