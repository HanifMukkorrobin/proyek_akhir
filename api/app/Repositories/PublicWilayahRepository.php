<?php

namespace App\Repositories;

use App\Models\Wilayah;

class PublicWilayahRepository
{
    public function getWilayah(string $wilayahId, string $cari): array
    {
        $rows = $this->getBaseRows($wilayahId, $cari);
        $rowsById = $this->indexRowsById($rows);

        $matchIds = [];

        if ($cari !== '') {
            [$selectedRows, $matchIds] = $this->filterBySearch($rows, $rowsById, $cari, $wilayahId);
        } elseif ($wilayahId !== '') {
            $selectedRows = $this->filterDirectChildren($rows, $wilayahId);
        } else {
            $selectedRows = $rows;
        }

        $allWilayahIds = $this->getWilayahIdsForChildMap($rows, $wilayahId, $cari);
        $hasChildMap = $this->buildHasChildMap($allWilayahIds);
        $matchMap = array_fill_keys($matchIds, true);

        return array_map(function (array $row) use ($matchMap, $hasChildMap) {
            $id = $row['wilayah_id'];

            return [
                'wilayah_id' => $id,
                'parent_wilayah_id' => $this->getParentWilayahId($id),
                'level' => $this->getLevel($id),
                'nama' => $row['nama'],
                'kode_dukcapil' => $row['kode_dukcapil'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'is_have_child' => isset($hasChildMap[$id]) ? 1 : 0,
                'is_match' => isset($matchMap[$id]),
            ];
        }, $selectedRows);
    }

    private function getBaseRows(string $wilayahId, string $cari): array
    {
        $query = Wilayah::query()->orderBy('wilayah_id');

        // Performance mode: without filter params only fetch level-1 nodes.
        if ($wilayahId === '' && $cari === '') {
            $query->whereRaw('char_length(wilayah_id) = 3');
        }

        return $query
            ->get([
                'wilayah_id',
                'nama',
                'kode_dukcapil',
                'latitude',
                'longitude',
            ])
            ->map(function (Wilayah $wilayah) {
                return [
                    'wilayah_id' => (string) $wilayah->wilayah_id,
                    'nama' => $wilayah->nama,
                    'kode_dukcapil' => $wilayah->kode_dukcapil,
                    'latitude' => $wilayah->latitude,
                    'longitude' => $wilayah->longitude,
                ];
            })
            ->all();
    }

    private function getWilayahIdsForChildMap(array $rows, string $wilayahId, string $cari): array
    {
        if ($wilayahId === '' && $cari === '') {
            return Wilayah::query()
                ->orderBy('wilayah_id')
                ->pluck('wilayah_id')
                ->map(function ($id) {
                    return (string) $id;
                })
                ->all();
        }

        return array_map(function (array $row) {
            return (string) $row['wilayah_id'];
        }, $rows);
    }

    private function indexRowsById(array $rows): array
    {
        $rowsById = [];

        foreach ($rows as $row) {
            $rowsById[$row['wilayah_id']] = $row;
        }

        return $rowsById;
    }

    private function filterDirectChildren(array $rows, string $wilayahId): array
    {
        $childLength = strlen($wilayahId) + 3;

        return array_values(array_filter($rows, function (array $row) use ($wilayahId, $childLength) {
            $id = $row['wilayah_id'];

            return str_starts_with($id, $wilayahId) && strlen($id) === $childLength;
        }));
    }

    private function filterBySearch(array $rows, array $rowsById, string $cari, string $wilayahId): array
    {
        $keyword = mb_strtolower($cari);
        $matchMap = [];

        foreach ($rows as $row) {
            $id = $row['wilayah_id'];

            if (!$this->isInScope($id, $wilayahId)) {
                continue;
            }

            $isMatch = mb_stripos((string) $row['nama'], $keyword) !== false;

            if ($isMatch) {
                $matchMap[$id] = true;
            }
        }

        if (empty($matchMap)) {
            return [[], []];
        }

        $includeMap = [];

        foreach (array_keys($matchMap) as $matchId) {
            $currentId = $matchId;

            while ($currentId !== '') {
                if (isset($rowsById[$currentId])) {
                    $includeMap[$currentId] = true;
                }

                if ($wilayahId !== '' && $currentId === $wilayahId) {
                    break;
                }

                if (strlen($currentId) <= 3) {
                    break;
                }

                $nextId = substr($currentId, 0, strlen($currentId) - 3);

                if ($wilayahId !== '' && !$this->isInScope($nextId, $wilayahId) && $nextId !== $wilayahId) {
                    if (isset($rowsById[$wilayahId])) {
                        $includeMap[$wilayahId] = true;
                    }

                    break;
                }

                $currentId = $nextId;
            }
        }

        $selectedRows = array_values(array_filter($rows, function (array $row) use ($includeMap) {
            return isset($includeMap[$row['wilayah_id']]);
        }));

        return [$selectedRows, array_keys($matchMap)];
    }

    private function buildHasChildMap(array $wilayahIds): array
    {
        $hasChildMap = [];

        foreach ($wilayahIds as $wilayahId) {
            $id = (string) $wilayahId;

            if (strlen($id) <= 3) {
                continue;
            }

            $parentId = substr($id, 0, strlen($id) - 3);
            $hasChildMap[$parentId] = true;
        }

        return $hasChildMap;
    }

    private function isInScope(string $candidateId, string $wilayahId): bool
    {
        if ($wilayahId === '') {
            return true;
        }

        return $candidateId === $wilayahId || str_starts_with($candidateId, $wilayahId);
    }

    private function getLevel(string $wilayahId): int
    {
        $length = strlen($wilayahId);

        if ($length === 0) {
            return 0;
        }

        return (int) ceil($length / 3);
    }

    private function getParentWilayahId(string $wilayahId): ?string
    {
        if (strlen($wilayahId) <= 3) {
            return null;
        }

        return substr($wilayahId, 0, strlen($wilayahId) - 3);
    }
}