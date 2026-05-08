<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class WilayahDictionaryRepository
{
    private static ?array $cachedDictionary = null;

    private const GLOBAL_NAME_ALIASES = [
        'DAERAH ISTIMEWA YOGYAKARTA' => ['DIY', 'DI YOGYAKARTA', 'YOGYAKARTA', 'JOGJA'],
        'DKI JAKARTA' => ['DKI', 'JAKARTA'],
        'JAWA BARAT' => ['JABAR'],
        'JAWA TENGAH' => ['JATENG'],
        'JAWA TIMUR' => ['JATIM'],
        'KALIMANTAN BARAT' => ['KALBAR'],
        'KALIMANTAN SELATAN' => ['KALSEL'],
        'KALIMANTAN TENGAH' => ['KALTENG'],
        'KALIMANTAN TIMUR' => ['KALTIM'],
        'KALIMANTAN UTARA' => ['KALTARA'],
        'KEPULAUAN BANGKA BELITUNG' => ['BABEL'],
        'KEPULAUAN RIAU' => ['KEPRI'],
        'MALUKU UTARA' => ['MALUT'],
        'NUSA TENGGARA BARAT' => ['NTB'],
        'NUSA TENGGARA TIMUR' => ['NTT'],
        'SULAWESI BARAT' => ['SULBAR'],
        'SULAWESI SELATAN' => ['SULSEL'],
        'SULAWESI TENGAH' => ['SULTENG'],
        'SULAWESI TENGGARA' => ['SULTRA'],
        'SULAWESI UTARA' => ['SULUT'],
        'SUMATERA BARAT' => ['SUMBAR'],
        'SUMATERA SELATAN' => ['SUMSEL'],
        'SUMATERA UTARA' => ['SUMUT'],
    ];

    public function getDictionary(): array
    {
        if (self::$cachedDictionary !== null) {
            return self::$cachedDictionary;
        }

        $rowsById = [];
        $byKeyMap = [];
        $keysByPrefixMap = [];

        $rows = DB::table('wilayah')
            ->select([
                'wilayah_id',
                'nama',
                'latitude',
                'longitude',
                'dihapus_pada',
            ])
            ->orderBy('wilayah_id')
            ->cursor();

        foreach ($rows as $wilayah) {
            $id = (string) $wilayah->wilayah_id;
            $name = trim((string) $wilayah->nama);

            $rowsById[$id] = [
                'wilayah_id' => $id,
                'nama' => $name,
                'level' => $this->getLevelFromId($id),
                'parent_wilayah_id' => $this->getParentWilayahId($id),
                'latitude' => $this->normalizeCoordinateValue($wilayah->latitude),
                'longitude' => $this->normalizeCoordinateValue($wilayah->longitude),
                'is_deleted' => $wilayah->dihapus_pada !== null,
            ];

            $searchKeys = $this->buildSearchKeys($name);

            foreach ($searchKeys as $searchKey) {
                if (!isset($byKeyMap[$searchKey])) {
                    $byKeyMap[$searchKey] = [];
                }

                $byKeyMap[$searchKey][$id] = true;

                $prefix = substr($searchKey, 0, 2);

                if (!isset($keysByPrefixMap[$prefix])) {
                    $keysByPrefixMap[$prefix] = [];
                }

                $keysByPrefixMap[$prefix][$searchKey] = true;
            }
        }

        $byKey = [];

        foreach ($byKeyMap as $key => $wilayahMap) {
            $byKey[$key] = array_keys($wilayahMap);
        }

        $keysByPrefix = [];

        foreach ($keysByPrefixMap as $prefix => $keyMap) {
            $keysByPrefix[$prefix] = array_keys($keyMap);
        }

        self::$cachedDictionary = [
            'rows_by_id' => $rowsById,
            'by_key' => $byKey,
            'keys_by_prefix' => $keysByPrefix,
        ];

        return self::$cachedDictionary;
    }

    private function buildSearchKeys(string $name): array
    {
        $keys = [];

        $rawKey = $this->canonicalize($name);

        if ($rawKey !== '') {
            $keys[] = $rawKey;
        }

        $strippedName = $this->stripAdministrativePrefix($name);
        $strippedKey = $this->canonicalize($strippedName);

        if ($strippedKey !== '') {
            $keys[] = $strippedKey;
        }

        $aliasKeys = $this->buildAliasKeys($name, $strippedName);

        foreach ($aliasKeys as $aliasKey) {
            $keys[] = $aliasKey;
        }

        return array_values(array_unique($keys));
    }

    private function buildAliasKeys(string $name, string $strippedName): array
    {
        $keys = [];

        $normalizedSources = [
            $this->normalizeAliasSource($name),
            $this->normalizeAliasSource($strippedName),
        ];

        foreach ($normalizedSources as $sourceName) {
            if ($sourceName === '') {
                continue;
            }

            $aliases = self::GLOBAL_NAME_ALIASES[$sourceName] ?? [];

            foreach ($aliases as $alias) {
                $aliasKey = $this->canonicalize($alias);

                if ($aliasKey !== '') {
                    $keys[] = $aliasKey;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private function normalizeAliasSource(string $name): string
    {
        $normalized = mb_strtoupper(trim($name));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function canonicalize(string $value): string
    {
        $upper = mb_strtoupper($value);
        $clean = preg_replace('/[^A-Z0-9]+/u', '', $upper) ?? '';
        $clean = preg_replace('/\d+/u', '', $clean) ?? '';

        return trim($clean);
    }

    private function stripAdministrativePrefix(string $name): string
    {
        $upper = mb_strtoupper(trim($name));

        $patterns = [
            '/^PROV\.?\s+/u',
            '/^PROVINSI\s+/u',
            '/^KAB\.?\s+/u',
            '/^KABUPATEN\s+/u',
            '/^KOTA\s+/u',
            '/^KEC\.?\s+/u',
            '/^KECAMATAN\s+/u',
            '/^DESA\s+/u',
            '/^DS\.?\s+/u',
            '/^KEL\.?\s+/u',
            '/^KELURAHAN\s+/u',
            '/^DSN\.?\s+/u',
            '/^DUSUN\s+/u',
        ];

        foreach ($patterns as $pattern) {
            $upper = preg_replace($pattern, '', $upper) ?? $upper;
        }

        return trim($upper);
    }

    private function getLevelFromId(string $wilayahId): int
    {
        if ($wilayahId === '') {
            return 0;
        }

        return (int) ceil(strlen($wilayahId) / 3);
    }

    private function getParentWilayahId(string $wilayahId): ?string
    {
        if (strlen($wilayahId) <= 3) {
            return null;
        }

        return substr($wilayahId, 0, strlen($wilayahId) - 3);
    }

    private function normalizeCoordinateValue($value): ?string
    {
        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        return $stringValue;
    }
}