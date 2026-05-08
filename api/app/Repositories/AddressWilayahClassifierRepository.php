<?php

namespace App\Repositories;

class AddressWilayahClassifierRepository
{
    private const FUZZY_THRESHOLD = 0.78;

    private const CONFIDENCE_REVIEW_THRESHOLD = 0.78;

    private const HIERARCHY_COMPLETENESS_WEIGHT = 0.03;

    private const ADMIN_HINT_EXACT_BONUS = 0.2;

    private const ADMIN_HINT_PARTIAL_BONUS = 0.12;

    private const ADMIN_HINT_FUZZY_BONUS = 0.08;

    private const STOP_WORDS_PATTERN = '/\b(ALAMAT|RUMAH|JL|JLN|JALAN|NO|NOMOR|BLOK|BLK|RT|RW|GANG|GG|PERUM|PERUMAHAN|KAV|KAVLING|DSN|DUSUN|DS|DESA|KEL|KELURAHAN|KEC|KECAMATAN|KAB|KABUPATEN|KOTA|PROV|PROVINSI)\.?\b/u';

    private WilayahDictionaryRepository $wilayahDictionaryRepository;

    private NominatimGeocodingRepository $nominatimGeocodingRepository;

    public function __construct(
        WilayahDictionaryRepository $wilayahDictionaryRepository,
        NominatimGeocodingRepository $nominatimGeocodingRepository
    ) {
        $this->wilayahDictionaryRepository = $wilayahDictionaryRepository;
        $this->nominatimGeocodingRepository = $nominatimGeocodingRepository;
    }

    public function classifyMany(array $addresses, array $options = []): array
    {
        $useExternalGeocoding = $this->resolveUseExternalOption($options);

        $results = [];
        $matchedCount = 0;
        $needsConfirmationCount = 0;
        $externalGeocodingUsed = false;

        foreach ($addresses as $index => $address) {
            $result = $this->classifyOne((string) $address, $useExternalGeocoding);
            $result['index'] = $index;
            $results[] = $result;

            if ($result['mapping']['status'] !== 'unmatched') {
                $matchedCount++;
            }

            if ($result['needs_confirmation']) {
                $needsConfirmationCount++;
            }

            if (($result['external_geocoding']['used'] ?? false) === true) {
                $externalGeocodingUsed = true;
            }
        }

        return [
            'meta' => [
                'total_input' => count($addresses),
                'matched' => $matchedCount,
                'unmatched' => count($addresses) - $matchedCount,
                'needs_confirmation' => $needsConfirmationCount,
                'external_geocoding_enabled' => $useExternalGeocoding,
                'external_geocoding_used' => $externalGeocodingUsed,
            ],
            'data' => $results,
        ];
    }

    public function classifyOne(string $address, bool $useExternalGeocoding = true): array
    {
        $internalResult = $this->runInternalPipeline($address);
        $selectedResult = $internalResult;

        $externalGeocodingMeta = [
            'used' => false,
            'provider' => null,
            'display_name' => null,
            'latitude' => null,
            'longitude' => null,
            'importance' => null,
            'hint_address' => null,
            'query_source' => null,
            'query_address' => null,
        ];

        if ($useExternalGeocoding && $this->shouldUseExternal($internalResult)) {
            $queryAddress = $this->buildManualGeocodingQuery($selectedResult);

            $externalGeocodingMeta['query_source'] = 'manual_wilayah';
            $externalGeocodingMeta['query_address'] = $queryAddress;

            if ($queryAddress === '') {
                return [
                    'alamat_asli' => $address,
                    'normalisasi' => $internalResult['normalisasi'],
                    'mapping' => $selectedResult['mapping'],
                    'geocoding' => $selectedResult['geocoding'],
                    'needs_confirmation' => $selectedResult['needs_confirmation'],
                    'external_geocoding' => $externalGeocodingMeta,
                ];
            }

            $externalResult = $this->nominatimGeocodingRepository->geocode($queryAddress);

            if ($externalResult !== null) {
                $externalGeocodingMeta = [
                    'used' => true,
                    'provider' => 'nominatim',
                    'display_name' => $externalResult['display_name'],
                    'latitude' => $externalResult['latitude'],
                    'longitude' => $externalResult['longitude'],
                    'importance' => $externalResult['importance'],
                    'hint_address' => null,
                    'query_source' => 'manual_wilayah',
                    'query_address' => $queryAddress,
                ];

                $hintAddress = $this->buildExternalHintAddress($externalResult['address'] ?? []);

                if ($hintAddress !== '') {
                    $externalGeocodingMeta['hint_address'] = $hintAddress;

                    $hintResult = $this->runInternalPipeline($hintAddress);

                    if ($this->shouldPreferHintResult($selectedResult, $hintResult)) {
                        $selectedResult['mapping'] = $hintResult['mapping'];
                        $selectedResult['needs_confirmation'] = $hintResult['needs_confirmation'];
                    }
                }

                $selectedLat = $selectedResult['geocoding']['latitude'] ?? null;
                $selectedLon = $selectedResult['geocoding']['longitude'] ?? null;

                if (($selectedLat === null || $selectedLon === null)
                    && $externalResult['latitude'] !== null
                    && $externalResult['longitude'] !== null) {
                    $selectedResult['geocoding']['latitude'] = $externalResult['latitude'];
                    $selectedResult['geocoding']['longitude'] = $externalResult['longitude'];
                    $selectedResult['geocoding']['source_wilayah_id'] = null;
                    $selectedResult['geocoding']['source_level'] = null;
                    $selectedResult['geocoding']['source'] = 'external_nominatim';
                    $selectedResult['geocoding']['external_geocoding_used'] = true;
                }
            }
        }

        return [
            'alamat_asli' => $address,
            'normalisasi' => $internalResult['normalisasi'],
            'mapping' => $selectedResult['mapping'],
            'geocoding' => $selectedResult['geocoding'],
            'needs_confirmation' => $selectedResult['needs_confirmation'],
            'external_geocoding' => $externalGeocodingMeta,
        ];
    }

    private function runInternalPipeline(string $address): array
    {
        $dictionary = $this->wilayahDictionaryRepository->getDictionary();

        $uppercase = mb_strtoupper($address);
        $specialCleaned = $this->removeSpecialCharacters($uppercase);
        $administrativeHints = $this->extractAdministrativeHints($specialCleaned);
        $withoutStopWords = $this->removeStopWords($specialCleaned);
        $tokens = $this->tokenize($withoutStopWords);

        if (empty($tokens)) {
            $tokens = $this->tokenize($specialCleaned);
        }

        $candidates = $this->collectCandidates($tokens, $dictionary, $administrativeHints);
        $anchor = $this->pickAnchor($candidates);
        $mappedWilayah = $this->buildMappedWilayah($anchor, $dictionary['rows_by_id']);
        $relatedRegionsArray = $this->buildRelatedRegionsArray($candidates);
        $hierarchyValidation = $this->validateHierarchyConsistency($mappedWilayah, $relatedRegionsArray);
        $confidence = $this->calculateConfidence($anchor, $candidates, $hierarchyValidation, $mappedWilayah, $administrativeHints);
        $coordinates = $this->resolveCoordinates($mappedWilayah);
        $topCandidatesForReview = $this->buildTopCandidatesForReview($candidates);

        $needsConfirmation = $anchor === null
            || ($hierarchyValidation['status'] ?? 'consistent') === 'inconsistent'
            || ($confidence['score'] ?? 0) < self::CONFIDENCE_REVIEW_THRESHOLD;
        $status = $anchor === null ? 'unmatched' : ($needsConfirmation ? 'partial' : 'matched');

        return [
            'normalisasi' => [
                'uppercase' => $uppercase,
                'cleaned' => $specialCleaned,
                'without_stop_words' => $withoutStopWords,
                'tokens' => $tokens,
                'administrative_hints' => $administrativeHints,
            ],
            'mapping' => [
                'status' => $status,
                'anchor' => $anchor,
                'wilayah' => $mappedWilayah,
                'confidence' => $confidence,
                'hierarchy_validation' => $hierarchyValidation,
                'related_regions_array' => $relatedRegionsArray,
                'top_candidates_for_review' => $needsConfirmation ? $topCandidatesForReview : [],
                'candidates' => $this->sanitizeCandidates(array_slice($candidates, 0, 10)),
            ],
            'geocoding' => [
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
                'source_wilayah_id' => $coordinates['source_wilayah_id'],
                'source_level' => $coordinates['source_level'],
                'source' => 'internal_wilayah',
                'external_geocoding_used' => false,
            ],
            'needs_confirmation' => $needsConfirmation,
        ];
    }

    private function removeSpecialCharacters(string $address): string
    {
        $cleaned = preg_replace('/[^A-Z0-9,\.\/\s:;-]+/u', ' ', $address) ?? $address;
        $cleaned = str_replace(['/', ':', ';', '-'], ',', $cleaned);
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*,\s*/u', ', ', $cleaned) ?? $cleaned;

        return trim($cleaned, " ,.\t\n\r\0\x0B");
    }

    private function removeStopWords(string $address): string
    {
        $cleaned = preg_replace(self::STOP_WORDS_PATTERN, ' ', $address) ?? $address;
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\s*,\s*/u', ', ', $cleaned) ?? $cleaned;

        return trim($cleaned, " ,.\t\n\r\0\x0B");
    }

    private function tokenize(string $address): array
    {
        $chunks = preg_split('/[,.]+/u', $address) ?: [];
        $tokens = [];

        foreach ($chunks as $chunk) {
            $chunk = trim(preg_replace('/\s+/u', ' ', $chunk) ?? $chunk);

            if ($chunk === '') {
                continue;
            }

            $words = array_values(array_filter(explode(' ', $chunk), function (string $word) {
                return trim($word) !== '';
            }));

            $words = array_values(array_filter($words, function (string $word) {
                return preg_match('/\d/u', $word) !== 1;
            }));

            if (empty($words)) {
                continue;
            }

            $tokens[] = implode(' ', $words);

            foreach ($words as $word) {
                $tokens[] = $word;
            }

            $wordCount = count($words);

            for ($ngramSize = 2; $ngramSize <= 3; $ngramSize++) {
                if ($wordCount < $ngramSize) {
                    continue;
                }

                for ($i = 0; $i <= $wordCount - $ngramSize; $i++) {
                    $tokens[] = implode(' ', array_slice($words, $i, $ngramSize));
                }
            }
        }

        $uniqueTokens = [];
        $seen = [];

        foreach ($tokens as $token) {
            $normalized = trim($token);

            if ($normalized === '') {
                continue;
            }

            if (preg_match('/\d/u', $normalized) === 1) {
                continue;
            }

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $uniqueTokens[] = $normalized;
        }

        return $uniqueTokens;
    }

    private function collectCandidates(array $tokens, array $dictionary, array $administrativeHints = []): array
    {
        $candidateMap = [];

        foreach ($tokens as $token) {
            $tokenKey = $this->canonicalize($token);

            if (strlen($tokenKey) < 3) {
                continue;
            }

            $tokenCandidates = $this->collectCandidatesFromToken($token, $tokenKey, $dictionary);

            foreach ($tokenCandidates as $candidate) {
                $id = $candidate['wilayah_id'];

                if (!isset($candidateMap[$id]) || $candidate['score'] > $candidateMap[$id]['score']) {
                    $candidateMap[$id] = $candidate;
                }
            }
        }

        $candidates = array_values($candidateMap);
        $scoreById = [];

        foreach ($candidates as $candidate) {
            $scoreById[$candidate['wilayah_id']] = $candidate['score'];
        }

        foreach ($candidates as $index => $candidate) {
            $contextScore = $this->calculateHierarchyContextScore($candidate['wilayah_id'], $scoreById);
            $hierarchyCompleteness = $this->calculateHierarchyCompleteness($candidate['wilayah_id'], $scoreById);
            $hintScore = $this->calculateAdministrativeHintScore($candidate, $administrativeHints);

            $candidates[$index]['context_score'] = round($contextScore, 4);
            $candidates[$index]['hint_score'] = round($hintScore, 4);
            $candidates[$index]['hierarchy_completeness'] = $hierarchyCompleteness;
            $candidates[$index]['rank_score'] = round(
                $candidate['score']
                + $contextScore
                + $hintScore
                + ($hierarchyCompleteness * self::HIERARCHY_COMPLETENESS_WEIGHT),
                4
            );
        }

        usort($candidates, function (array $a, array $b) {
            if ($a['rank_score'] === $b['rank_score']) {
                if ($a['score'] === $b['score']) {
                    if ($a['matched_key_length'] === $b['matched_key_length']) {
                        return $b['level'] <=> $a['level'];
                    }

                    return $b['matched_key_length'] <=> $a['matched_key_length'];
                }

                return $b['score'] <=> $a['score'];
            }

            return $b['rank_score'] <=> $a['rank_score'];
        });

        return $candidates;
    }

    private function collectCandidatesFromToken(string $token, string $tokenKey, array $dictionary): array
    {
        $candidates = [];
        $candidateMap = [];

        $rowsById = $dictionary['rows_by_id'];
        $byKey = $dictionary['by_key'];
        $keysByPrefix = $dictionary['keys_by_prefix'];

        if (isset($byKey[$tokenKey])) {
            $exactScore = $this->computeExactScore($tokenKey);

            foreach ($byKey[$tokenKey] as $wilayahId) {
                if (!isset($rowsById[$wilayahId])) {
                    continue;
                }

                $row = $rowsById[$wilayahId];
                $candidateMap[$wilayahId] = [
                    'wilayah_id' => $wilayahId,
                    'nama' => $row['nama'],
                    'level' => $row['level'],
                    'score' => $exactScore,
                    'match_type' => 'exact',
                    'token' => $token,
                    'matched_key_length' => strlen($tokenKey),
                ];
            }
        }

        $prefix = substr($tokenKey, 0, 2);
        $candidateKeys = $keysByPrefix[$prefix] ?? [];

        foreach ($candidateKeys as $candidateKey) {
            if (!isset($byKey[$candidateKey])) {
                continue;
            }

            if ($candidateKey === $tokenKey) {
                continue;
            }

            if (strlen($candidateKey) < 4) {
                continue;
            }

            $containsRelation = str_contains($tokenKey, $candidateKey) || str_contains($candidateKey, $tokenKey);

            if (!$containsRelation) {
                continue;
            }

            $shortTokenOverlap = min(strlen($candidateKey), strlen($tokenKey)) <= 5
                && abs(strlen($candidateKey) - strlen($tokenKey)) <= 1;

            if ($shortTokenOverlap) {
                continue;
            }

            $lenRatio = min(strlen($candidateKey), strlen($tokenKey)) / max(strlen($candidateKey), strlen($tokenKey));
            $score = min(0.92, 0.72 + (0.2 * $lenRatio));

            foreach ($byKey[$candidateKey] as $wilayahId) {
                if (!isset($rowsById[$wilayahId])) {
                    continue;
                }

                $row = $rowsById[$wilayahId];

                if (isset($candidateMap[$wilayahId]) && $candidateMap[$wilayahId]['score'] >= $score) {
                    continue;
                }

                $candidateMap[$wilayahId] = [
                    'wilayah_id' => $wilayahId,
                    'nama' => $row['nama'],
                    'level' => $row['level'],
                    'score' => round($score, 4),
                    'match_type' => 'contains',
                    'token' => $token,
                    'matched_key_length' => strlen($candidateKey),
                ];
            }
        }

        if (empty($candidateMap)) {
            foreach ($candidateKeys as $candidateKey) {
                if (!isset($byKey[$candidateKey])) {
                    continue;
                }

                if (strlen($candidateKey) < 4) {
                    continue;
                }

                $similarity = $this->similarity($tokenKey, $candidateKey);
                $fuzzyThreshold = $this->resolveAdaptiveFuzzyThreshold($tokenKey, $candidateKey);

                if ($similarity < $fuzzyThreshold) {
                    continue;
                }

                $score = min(0.89, max($similarity, $fuzzyThreshold));

                foreach ($byKey[$candidateKey] as $wilayahId) {
                    if (!isset($rowsById[$wilayahId])) {
                        continue;
                    }

                    $row = $rowsById[$wilayahId];

                    if (isset($candidateMap[$wilayahId]) && $candidateMap[$wilayahId]['score'] >= $score) {
                        continue;
                    }

                    $candidateMap[$wilayahId] = [
                        'wilayah_id' => $wilayahId,
                        'nama' => $row['nama'],
                        'level' => $row['level'],
                        'score' => round($score, 4),
                        'match_type' => 'fuzzy',
                        'token' => $token,
                        'matched_key_length' => strlen($candidateKey),
                    ];
                }
            }
        }

        foreach ($candidateMap as $candidate) {
            $candidates[] = $candidate;
        }

        return $candidates;
    }

    private function pickAnchor(array $candidates): ?array
    {
        if (empty($candidates)) {
            return null;
        }

        $topCandidate = $candidates[0];
        $topRankScore = $topCandidate['rank_score'] ?? $topCandidate['score'];

        $desaCandidates = array_values(array_filter($candidates, function (array $candidate) use ($topRankScore) {
            $rankScore = $candidate['rank_score'] ?? $candidate['score'];

            return $candidate['level'] >= 5
                && $candidate['score'] >= 0.83
                && $rankScore >= ($topRankScore * 0.82);
        }));

        if (!empty($desaCandidates)) {
            usort($desaCandidates, function (array $a, array $b) {
                $rankA = $a['rank_score'] ?? $a['score'];
                $rankB = $b['rank_score'] ?? $b['score'];

                if ($rankA === $rankB) {
                    if ($a['matched_key_length'] === $b['matched_key_length']) {
                        return $b['level'] <=> $a['level'];
                    }

                    return $b['matched_key_length'] <=> $a['matched_key_length'];
                }

                return $rankB <=> $rankA;
            });

            $anchor = $desaCandidates[0];
        } else {
            $anchor = $topCandidate;
        }

        unset(
            $anchor['matched_key_length'],
            $anchor['rank_score'],
            $anchor['context_score'],
            $anchor['hint_score'],
            $anchor['hierarchy_completeness']
        );

        return $anchor;
    }

    private function buildMappedWilayah(?array $anchor, array $rowsById): array
    {
        $levels = [
            2 => null,
            3 => null,
            4 => null,
            5 => null,
        ];

        if ($anchor === null) {
            return [
                'provinsi' => null,
                'kabupaten_kota' => null,
                'kecamatan' => null,
                'desa' => null,
            ];
        }

        $currentId = $anchor['wilayah_id'];

        while ($currentId !== '') {
            if (!isset($rowsById[$currentId])) {
                break;
            }

            $row = $rowsById[$currentId];
            $level = $row['level'];

            if (array_key_exists($level, $levels)) {
                $levels[$level] = [
                    'wilayah_id' => $row['wilayah_id'],
                    'nama' => $row['nama'],
                    'level' => $row['level'],
                    'parent_wilayah_id' => $row['parent_wilayah_id'],
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'is_deleted' => $row['is_deleted'] ?? false,
                ];
            }

            if (strlen($currentId) <= 3) {
                break;
            }

            $currentId = substr($currentId, 0, strlen($currentId) - 3);
        }

        return [
            'provinsi' => $levels[2],
            'kabupaten_kota' => $levels[3],
            'kecamatan' => $levels[4],
            'desa' => $levels[5],
        ];
    }

    private function resolveCoordinates(array $mappedWilayah): array
    {
        $ordered = [
            $mappedWilayah['desa'],
            $mappedWilayah['kecamatan'],
            $mappedWilayah['kabupaten_kota'],
            $mappedWilayah['provinsi'],
        ];

        foreach ($ordered as $node) {
            if ($node === null) {
                continue;
            }

            $latitude = $node['latitude'] ?? null;
            $longitude = $node['longitude'] ?? null;

            if ($latitude === null || $longitude === null) {
                continue;
            }

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'source_wilayah_id' => $node['wilayah_id'],
                'source_level' => $node['level'],
            ];
        }

        return [
            'latitude' => null,
            'longitude' => null,
            'source_wilayah_id' => null,
            'source_level' => null,
        ];
    }

    private function canonicalize(string $value): string
    {
        $upper = mb_strtoupper($value);
        $clean = preg_replace('/[^A-Z0-9]+/u', '', $upper) ?? '';
        $clean = preg_replace('/\d+/u', '', $clean) ?? '';

        return trim($clean);
    }

    private function similarity(string $left, string $right): float
    {
        $maxLength = max(strlen($left), strlen($right));

        if ($maxLength === 0) {
            return 0.0;
        }

        $distance = levenshtein($left, $right);

        return 1 - ($distance / $maxLength);
    }

    private function resolveAdaptiveFuzzyThreshold(string $tokenKey, string $candidateKey): float
    {
        $tokenLength = strlen($tokenKey);
        $candidateLength = strlen($candidateKey);
        $minLength = min($tokenLength, $candidateLength);

        if ($minLength <= 4) {
            return 0.88;
        }

        if ($minLength <= 6) {
            return 0.85;
        }

        if ($minLength <= 9) {
            return 0.81;
        }

        return self::FUZZY_THRESHOLD;
    }

    private function computeExactScore(string $tokenKey): float
    {
        $length = strlen($tokenKey);
        $boundedLength = min($length, 15);

        return round(0.7 + (($boundedLength / 15) * 0.3), 4);
    }

    private function calculateHierarchyContextScore(string $wilayahId, array $scoreById): float
    {
        $contextScore = 0.0;
        $currentId = $wilayahId;

        while (strlen($currentId) > 3) {
            $currentId = substr($currentId, 0, strlen($currentId) - 3);

            if (!isset($scoreById[$currentId])) {
                continue;
            }

            $contextScore += $scoreById[$currentId] * 0.35;
        }

        return $contextScore;
    }

    private function calculateHierarchyCompleteness(string $wilayahId, array $scoreById): int
    {
        $completeness = 0;
        $currentId = $wilayahId;

        while (strlen($currentId) > 3) {
            $currentId = substr($currentId, 0, strlen($currentId) - 3);

            if (isset($scoreById[$currentId])) {
                $completeness++;
            }
        }

        return $completeness;
    }

    private function calculateAdministrativeHintScore(array $candidate, array $administrativeHints): float
    {
        $level = (int) ($candidate['level'] ?? 0);
        $bucket = $this->resolveHintBucketForLevel($level);

        if ($bucket === null || empty($administrativeHints[$bucket])) {
            return 0.0;
        }

        $candidateName = $this->stripAdministrativePrefix((string) ($candidate['nama'] ?? ''));
        $candidateKey = $this->canonicalize($candidateName);

        if ($candidateKey === '') {
            return 0.0;
        }

        $bestBonus = 0.0;

        foreach ($administrativeHints[$bucket] as $hint) {
            $hintKey = $this->canonicalize((string) $hint);

            if ($hintKey === '') {
                continue;
            }

            if ($hintKey === $candidateKey) {
                $bestBonus = max($bestBonus, self::ADMIN_HINT_EXACT_BONUS);
                continue;
            }

            if (str_contains($candidateKey, $hintKey) || str_contains($hintKey, $candidateKey)) {
                $bestBonus = max($bestBonus, self::ADMIN_HINT_PARTIAL_BONUS);
                continue;
            }

            $similarity = $this->similarity($candidateKey, $hintKey);

            if ($similarity >= 0.9) {
                $bestBonus = max($bestBonus, self::ADMIN_HINT_FUZZY_BONUS);
            }
        }

        return $bestBonus;
    }

    private function resolveHintBucketForLevel(int $level): ?string
    {
        if ($level >= 5) {
            return 'desa';
        }

        $map = [
            2 => 'provinsi',
            3 => 'kabupaten_kota',
            4 => 'kecamatan',
        ];

        return $map[$level] ?? null;
    }

    private function buildRelatedRegionsArray(array $candidates): array
    {
        $grouped = [
            'provinsi' => [],
            'kabupaten_kota' => [],
            'kecamatan' => [],
            'desa' => [],
        ];

        foreach ($candidates as $candidate) {
            $bucket = $this->resolveHintBucketForLevel((int) ($candidate['level'] ?? 0));

            if ($bucket === null) {
                continue;
            }

            if (count($grouped[$bucket]) >= 3) {
                continue;
            }

            $grouped[$bucket][] = [
                'wilayah_id' => $candidate['wilayah_id'],
                'nama' => $candidate['nama'],
                'level' => $candidate['level'],
                'score' => $candidate['score'],
                'rank_score' => $candidate['rank_score'] ?? $candidate['score'],
                'match_type' => $candidate['match_type'],
            ];
        }

        return $grouped;
    }

    private function validateHierarchyConsistency(array $mappedWilayah, array $relatedRegionsArray): array
    {
        $hasMappedNode = false;

        foreach (['provinsi', 'kabupaten_kota', 'kecamatan', 'desa'] as $key) {
            if (is_array($mappedWilayah[$key] ?? null)) {
                $hasMappedNode = true;
                break;
            }
        }

        if (!$hasMappedNode) {
            return [
                'status' => 'no_data_to_validate',
                'consistency_check' => null,
                'alignment_report' => [],
            ];
        }

        $checks = [
            ['child' => 'kabupaten_kota', 'parent' => 'provinsi'],
            ['child' => 'kecamatan', 'parent' => 'kabupaten_kota'],
            ['child' => 'desa', 'parent' => 'kecamatan'],
        ];

        $issues = [];
        $alignmentReport = [];
        $relatedSupportMissing = 0;

        foreach ($checks as $check) {
            $childKey = $check['child'];
            $parentKey = $check['parent'];
            $child = $mappedWilayah[$childKey] ?? null;

            if (!is_array($child)) {
                continue;
            }

            $expectedParentId = (string) ($child['parent_wilayah_id'] ?? '');
            $parent = $mappedWilayah[$parentKey] ?? null;
            $selectedParentId = is_array($parent) ? (string) ($parent['wilayah_id'] ?? '') : '';

            $isAligned = true;

            if ($expectedParentId !== '') {
                if ($selectedParentId === '') {
                    $isAligned = false;
                    $issues[] = sprintf(
                        '%s "%s" tidak memiliki parent %s pada hasil terpilih.',
                        ucfirst(str_replace('_', ' ', $childKey)),
                        (string) ($child['nama'] ?? ''),
                        str_replace('_', ' ', $parentKey)
                    );
                } elseif ($selectedParentId !== $expectedParentId) {
                    $isAligned = false;
                    $issues[] = sprintf(
                        'Parent mismatch untuk %s "%s": expected %s, selected %s.',
                        str_replace('_', ' ', $childKey),
                        (string) ($child['nama'] ?? ''),
                        $expectedParentId,
                        $selectedParentId
                    );
                }
            }

            $relatedParentCandidates = $relatedRegionsArray[$parentKey] ?? [];
            $hasRelatedParentSupport = $this->hasAlignedParentInRelatedRegions(
                (string) ($child['wilayah_id'] ?? ''),
                $relatedParentCandidates
            );

            if (!$hasRelatedParentSupport && !empty($relatedParentCandidates)) {
                $relatedSupportMissing++;
            }

            $alignmentReport[] = [
                'child_level' => $childKey,
                'child_wilayah_id' => $child['wilayah_id'] ?? null,
                'child_nama' => $child['nama'] ?? null,
                'parent_level' => $parentKey,
                'expected_parent_wilayah_id' => $expectedParentId !== '' ? $expectedParentId : null,
                'selected_parent_wilayah_id' => $selectedParentId !== '' ? $selectedParentId : null,
                'is_aligned' => $isAligned,
                'related_parent_support' => $hasRelatedParentSupport,
            ];
        }

        return [
            'status' => empty($issues) ? 'consistent' : 'inconsistent',
            'consistency_check' => [
                'total_issues' => count($issues),
                'issues' => $issues,
                'related_parent_support_missing' => $relatedSupportMissing,
            ],
            'alignment_report' => $alignmentReport,
        ];
    }

    private function hasAlignedParentInRelatedRegions(string $childWilayahId, array $parentCandidates): bool
    {
        if ($childWilayahId === '') {
            return false;
        }

        foreach ($parentCandidates as $candidate) {
            $parentId = (string) ($candidate['wilayah_id'] ?? '');

            if ($parentId === '') {
                continue;
            }

            if (str_starts_with($childWilayahId, $parentId)) {
                return true;
            }
        }

        return false;
    }

    private function extractAdministrativeHints(string $address): array
    {
        $hints = [
            'provinsi' => [],
            'kabupaten_kota' => [],
            'kecamatan' => [],
            'desa' => [],
        ];

        if (trim($address) === '') {
            return $hints;
        }

        $keywords = 'PROVINSI|PROV|KABUPATEN|KAB|KOTA|KECAMATAN|KEC|DESA|DS|KELURAHAN|KEL|DUSUN|DSN';
        $pattern = '/\b(' . $keywords . ')\.?\s*([^,]+?)(?=\s+\b(?:' . $keywords . ')\b|,|$)/u';

        if (preg_match_all($pattern, $address, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $keyword = mb_strtoupper(trim((string) ($match[1] ?? '')));
                $bucket = $this->resolveHintBucket($keyword);

                if ($bucket === null) {
                    continue;
                }

                $hintValue = $this->normalizeAdministrativeHint((string) ($match[2] ?? ''));

                if ($hintValue === '') {
                    continue;
                }

                $hints[$bucket][] = $hintValue;
            }
        }

        foreach ($hints as $bucket => $values) {
            $seen = [];
            $unique = [];

            foreach ($values as $value) {
                $signature = $this->canonicalize((string) $value);

                if ($signature === '' || isset($seen[$signature])) {
                    continue;
                }

                $seen[$signature] = true;
                $unique[] = $value;
            }

            $hints[$bucket] = $unique;
        }

        return $hints;
    }

    private function resolveHintBucket(string $keyword): ?string
    {
        $normalized = rtrim(mb_strtoupper(trim($keyword)), '.');

        $map = [
            'PROVINSI' => 'provinsi',
            'PROV' => 'provinsi',
            'KABUPATEN' => 'kabupaten_kota',
            'KAB' => 'kabupaten_kota',
            'KOTA' => 'kabupaten_kota',
            'KECAMATAN' => 'kecamatan',
            'KEC' => 'kecamatan',
            'DESA' => 'desa',
            'DS' => 'desa',
            'KELURAHAN' => 'desa',
            'KEL' => 'desa',
            'DUSUN' => 'desa',
            'DSN' => 'desa',
        ];

        return $map[$normalized] ?? null;
    }

    private function normalizeAdministrativeHint(string $value): string
    {
        $normalized = $this->normalizeLocationPart($this->stripAdministrativePrefix($value));
        $normalized = preg_replace('/\d+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function calculateConfidence(
        ?array $anchor,
        array $candidates,
        array $hierarchyValidation,
        array $mappedWilayah,
        array $administrativeHints
    ): array {
        if ($anchor === null) {
            return [
                'score' => 0.0,
                'components' => [
                    'anchor_score' => 0.0,
                    'gap_score' => 0.0,
                    'hierarchy_score' => 0.0,
                    'hint_coverage_score' => 0.0,
                ],
            ];
        }

        $anchorScore = max(0.0, min(1.0, (float) ($anchor['score'] ?? 0.0)));
        $gapScore = $this->calculateCandidateGapScore($candidates);
        $hierarchyScore = $this->resolveHierarchyConfidenceScore($hierarchyValidation);
        $hintCoverageScore = $this->calculateHintCoverageScore($mappedWilayah, $administrativeHints);

        $confidenceScore = round(
            ($anchorScore * 0.45)
            + ($gapScore * 0.25)
            + ($hierarchyScore * 0.20)
            + ($hintCoverageScore * 0.10),
            4
        );

        return [
            'score' => $confidenceScore,
            'components' => [
                'anchor_score' => round($anchorScore, 4),
                'gap_score' => round($gapScore, 4),
                'hierarchy_score' => round($hierarchyScore, 4),
                'hint_coverage_score' => round($hintCoverageScore, 4),
            ],
        ];
    }

    private function calculateCandidateGapScore(array $candidates): float
    {
        if (empty($candidates)) {
            return 0.0;
        }

        $topScore = (float) ($candidates[0]['rank_score'] ?? $candidates[0]['score'] ?? 0.0);

        if (!isset($candidates[1])) {
            return 1.0;
        }

        $secondScore = (float) ($candidates[1]['rank_score'] ?? $candidates[1]['score'] ?? 0.0);
        $gap = max(0.0, $topScore - $secondScore);

        return min(1.0, $gap / 0.25);
    }

    private function resolveHierarchyConfidenceScore(array $hierarchyValidation): float
    {
        $status = (string) ($hierarchyValidation['status'] ?? 'no_data_to_validate');

        if ($status === 'consistent') {
            return 1.0;
        }

        if ($status === 'inconsistent') {
            return 0.2;
        }

        return 0.55;
    }

    private function calculateHintCoverageScore(array $mappedWilayah, array $administrativeHints): float
    {
        $totalHints = 0;
        $matchedHints = 0;

        foreach (['provinsi', 'kabupaten_kota', 'kecamatan', 'desa'] as $bucket) {
            $bucketHints = $administrativeHints[$bucket] ?? [];

            if (empty($bucketHints)) {
                continue;
            }

            $totalHints += count($bucketHints);
            $node = $mappedWilayah[$bucket] ?? null;

            if (!is_array($node)) {
                continue;
            }

            $nodeKey = $this->canonicalize($this->stripAdministrativePrefix((string) ($node['nama'] ?? '')));

            if ($nodeKey === '') {
                continue;
            }

            foreach ($bucketHints as $hint) {
                $hintKey = $this->canonicalize((string) $hint);

                if ($hintKey === '') {
                    continue;
                }

                if ($hintKey === $nodeKey || str_contains($nodeKey, $hintKey) || str_contains($hintKey, $nodeKey)) {
                    $matchedHints++;
                }
            }
        }

        if ($totalHints === 0) {
            return 0.5;
        }

        return min(1.0, $matchedHints / $totalHints);
    }

    private function buildTopCandidatesForReview(array $candidates, int $limit = 3): array
    {
        $selected = array_slice($candidates, 0, max(1, $limit));

        return array_values(array_map(function (array $candidate) {
            return [
                'wilayah_id' => $candidate['wilayah_id'] ?? null,
                'nama' => $candidate['nama'] ?? null,
                'level' => $candidate['level'] ?? null,
                'score' => $candidate['score'] ?? null,
                'rank_score' => $candidate['rank_score'] ?? $candidate['score'] ?? null,
                'match_type' => $candidate['match_type'] ?? null,
                'token' => $candidate['token'] ?? null,
            ];
        }, $selected));
    }

    private function sanitizeCandidates(array $candidates): array
    {
        return array_map(function (array $candidate) {
            unset(
                $candidate['matched_key_length'],
                $candidate['rank_score'],
                $candidate['context_score'],
                $candidate['hint_score'],
                $candidate['hierarchy_completeness']
            );

            return $candidate;
        }, $candidates);
    }

    private function resolveUseExternalOption(array $options): bool
    {
        if (array_key_exists('use_external_geocoding', $options)) {
            $resolved = filter_var($options['use_external_geocoding'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $resolvedEnv = filter_var((string) env('NOMINATIM_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($resolvedEnv === null) {
            return true;
        }

        return $resolvedEnv;
    }

    private function shouldUseExternal(array $internalResult): bool
    {
        if (($internalResult['needs_confirmation'] ?? false) === true) {
            return true;
        }

        $latitude = $internalResult['geocoding']['latitude'] ?? null;
        $longitude = $internalResult['geocoding']['longitude'] ?? null;

        return $latitude === null || $longitude === null;
    }

    private function shouldPreferHintResult(array $currentResult, array $hintResult): bool
    {
        $hintAnchor = $hintResult['mapping']['anchor'] ?? null;

        if ($hintAnchor === null) {
            return false;
        }

        $currentAnchor = $currentResult['mapping']['anchor'] ?? null;

        if ($currentAnchor === null) {
            return true;
        }

        $currentScore = (float) ($currentAnchor['score'] ?? 0);
        $hintScore = (float) ($hintAnchor['score'] ?? 0);
        $currentLevel = (int) ($currentAnchor['level'] ?? 0);
        $hintLevel = (int) ($hintAnchor['level'] ?? 0);

        if (($hintResult['needs_confirmation'] ?? true) === false
            && ($currentResult['needs_confirmation'] ?? false) === true
            && $hintScore >= ($currentScore - 0.05)) {
            return true;
        }

        if ($hintLevel > $currentLevel && $hintScore >= ($currentScore - 0.03)) {
            return true;
        }

        if ($hintScore >= ($currentScore + 0.04)) {
            return true;
        }

        return false;
    }

    private function buildExternalHintAddress(array $addressDetail): string
    {
        if (empty($addressDetail)) {
            return '';
        }

        $orderedKeys = [
            'village',
            'hamlet',
            'suburb',
            'quarter',
            'city_district',
            'district',
            'town',
            'city',
            'municipality',
            'county',
            'state',
        ];

        $parts = [];
        $seen = [];

        foreach ($orderedKeys as $key) {
            if (!isset($addressDetail[$key])) {
                continue;
            }

            $part = $this->normalizeLocationPart((string) $addressDetail[$key]);

            if ($part === '') {
                continue;
            }

            $signature = $this->canonicalize($part);

            if ($signature === '' || isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $parts[] = $part;
        }

        return implode(', ', $parts);
    }

    private function normalizeLocationPart(string $value): string
    {
        $upper = mb_strtoupper(trim($value));
        $upper = preg_replace('/[^A-Z0-9\s]+/u', ' ', $upper) ?? $upper;
        $upper = preg_replace('/\s+/u', ' ', $upper) ?? $upper;

        return trim($upper);
    }

    private function buildManualGeocodingQuery(array $result): string
    {
        $wilayah = $result['mapping']['wilayah'] ?? [];

        $orderedNodes = [
            $wilayah['desa'] ?? null,
            $wilayah['kecamatan'] ?? null,
            $wilayah['kabupaten_kota'] ?? null,
            $wilayah['provinsi'] ?? null,
        ];

        $parts = [];
        $seen = [];

        foreach ($orderedNodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $name = $this->normalizeLocationPart($this->stripAdministrativePrefix((string) ($node['nama'] ?? '')));

            if ($name === '') {
                continue;
            }

            $signature = $this->canonicalize($name);

            if ($signature === '' || isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $parts[] = $name;
        }

        if (empty($parts)) {
            $anchorName = $this->normalizeLocationPart($this->stripAdministrativePrefix((string) ($result['mapping']['anchor']['nama'] ?? '')));

            if ($anchorName !== '') {
                $parts[] = $anchorName;
            }
        }

        if (empty($parts)) {
            return '';
        }

        $parts[] = 'INDONESIA';

        return implode(', ', $parts);
    }

    private function stripAdministrativePrefix(string $value): string
    {
        $text = mb_strtoupper(trim($value));

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
            $text = preg_replace($pattern, '', $text) ?? $text;
        }

        return trim($text);
    }
}
