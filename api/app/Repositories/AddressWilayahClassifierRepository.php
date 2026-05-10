<?php

namespace App\Repositories;

class AddressWilayahClassifierRepository
{
    private const FUZZY_THRESHOLD = 0.78;

    private const CONFIDENCE_REVIEW_THRESHOLD = 0.78;

    private const HIERARCHY_COMPLETENESS_WEIGHT = 0.03;

    private const ADMIN_HINT_EXACT_BONUS = 0.25;

    private const ADMIN_HINT_PARTIAL_BONUS = 0.15;

    private const ADMIN_HINT_FUZZY_BONUS = 0.08;

    /** Penalty ketika nama kandidat cocok dengan hint tapi levelnya SALAH (desa = nama kecamatan). */
    private const ADMIN_HINT_LEVEL_MISMATCH_PENALTY = -0.15;

    /** Kata-kata umum yang bukan nama wilayah — dihapus sebelum tokenisasi. */
    private const STOP_WORDS_PATTERN = '/\b(ALAMAT|RUMAH|JL|JLN|JALAN|NO|NOMOR|BLOK|BLK|RT|RW|GANG|GG|PERUM|PERUMAHAN|KAV|KAVLING|DSN|DUSUN|DS|DESA|KEL|KELURAHAN|KEC|KECAMATAN|KAB|KABUPATEN|KOTA|PROV|PROVINSI|LINGKUNGAN|KOMPLEK|CLUSTER|GRIYA|REGENCY|RESIDENCE|INDAH|PERMAI|ASRI|SEJAHTERA)\.?\b/u';

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
            // Bangun komponen wilayah dari hasil normalisasi internal classifier.
            // Format: "Bojong Kulur, Gunung Putri, Kabupaten Bogor, Jawa Barat"
            $wilayahParts = $this->buildStructuredWilayahParts($selectedResult);

            // Susun query string penuh untuk meta logging
            $fullQuery = implode(', ', array_filter([
                $wilayahParts['desa'],
                $wilayahParts['kecamatan'],
                $wilayahParts['kabupaten'],
                $wilayahParts['provinsi'],
            ]));

            $externalGeocodingMeta['query_source']  = 'normalized_wilayah';
            $externalGeocodingMeta['query_address'] = $fullQuery;

            // geocodeWithFallback() mencoba dari paling spesifik ke paling umum:
            //   1. "desa, kecamatan, Kabupaten X, Provinsi"
            //   2. "kecamatan, Kabupaten X, Provinsi"
            //   3. "Kabupaten X, Provinsi"
            //   4. "Provinsi"
            $externalResult = $this->nominatimGeocodingRepository->geocodeWithFallback($wilayahParts);

            if ($externalResult !== null) {
                $externalGeocodingMeta = [
                    'used' => true,
                    'provider' => 'nominatim',
                    'display_name' => $externalResult['display_name'],
                    'latitude' => $externalResult['latitude'],
                    'longitude' => $externalResult['longitude'],
                    'importance' => $externalResult['importance'],
                    'hint_address' => null,
                    'query_source' => 'normalized_wilayah',
                    'query_address' => $fullQuery,
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

        $uppercase = $this->normalizeAdministrativeKeywordBoundaries(mb_strtoupper($address));
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

        $needsConfirmation = $this->shouldNeedConfirmation(
            $anchor,
            $candidates,
            $hierarchyValidation,
            $mappedWilayah,
            $confidence,
            $administrativeHints
        );
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

    private function normalizeAdministrativeKeywordBoundaries(string $address): string
    {
        $normalized = preg_replace(
            '/(?<=[A-Z0-9])(?=(?:PROVINSI|KABUPATEN|KECAMATAN|KELURAHAN|DUSUN|DESA)\b)/u',
            ' ',
            $address
        ) ?? $address;

        $normalized = preg_replace(
            '/(?<=[A-Z0-9])(?=(?:PROV|KAB|KEC|KEL|DSN|DS)\.?(?=[\s\.\/:;,]))/u',
            ' ',
            $normalized
        ) ?? $normalized;

        $normalized = preg_replace('/\bKEL(?:URAHAN)?\.?\s*\/\s*DESA\b/u', 'DESA', $normalized) ?? $normalized;

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
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

            $tokenCandidates = $this->collectCandidatesFromToken($token, $tokenKey, $dictionary, $administrativeHints);

            foreach ($tokenCandidates as $candidate) {
                $id = $candidate['wilayah_id'];

                if (!isset($candidateMap[$id]) || $candidate['score'] > $candidateMap[$id]['score']) {
                    $candidateMap[$id] = $candidate;
                }
            }
        }

        $candidates = array_values($candidateMap);
        $scoreById = [];
        $tokenKeyById = [];

        foreach ($candidates as $candidate) {
            $scoreById[$candidate['wilayah_id']] = $candidate['score'];
            $tokenKeyById[$candidate['wilayah_id']] = $this->canonicalize((string) ($candidate['token'] ?? ''));
        }

        foreach ($candidates as $index => $candidate) {
            $candidateTokenKey = $this->canonicalize((string) ($candidate['token'] ?? ''));
            $contextScore = $this->calculateHierarchyContextScore($candidate['wilayah_id'], $scoreById, $tokenKeyById, $candidateTokenKey);
            $hierarchyCompleteness = $this->calculateHierarchyCompleteness($candidate['wilayah_id'], $scoreById, $tokenKeyById, $candidateTokenKey);
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

    private function collectCandidatesFromToken(string $token, string $tokenKey, array $dictionary, array $administrativeHints): array
    {
        $candidates = [];
        $candidateMap = [];
        $allowedHintBuckets = $this->resolveAllowedHintBucketsForToken($tokenKey, $administrativeHints);

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

                if (($row['is_deleted'] ?? false) === true) {
                    continue;
                }

                if (!$this->isCandidateAllowedByHintBucket($row, $allowedHintBuckets)) {
                    continue;
                }

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

        $hasExactMatch = !empty($candidateMap);
        $prefix = substr($tokenKey, 0, 2);
        $candidateKeys = $keysByPrefix[$prefix] ?? [];

        if (!$hasExactMatch) {
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
                $minLength = min(strlen($candidateKey), strlen($tokenKey));
                $minLenRatio = $minLength <= 6 ? 0.74 : 0.7;

                if ($lenRatio < $minLenRatio) {
                    continue;
                }

                $score = min(0.88, 0.68 + (0.2 * $lenRatio));

                foreach ($byKey[$candidateKey] as $wilayahId) {
                    if (!isset($rowsById[$wilayahId])) {
                        continue;
                    }

                    $row = $rowsById[$wilayahId];

                    if (($row['is_deleted'] ?? false) === true) {
                        continue;
                    }

                    if (!$this->isCandidateAllowedByHintBucket($row, $allowedHintBuckets)) {
                        continue;
                    }

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

                    if (($row['is_deleted'] ?? false) === true) {
                        continue;
                    }

                    if (!$this->isCandidateAllowedByHintBucket($row, $allowedHintBuckets)) {
                        continue;
                    }

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

    private function resolveAllowedHintBucketsForToken(string $tokenKey, array $administrativeHints): ?array
    {
        $allowedBuckets = [];

        foreach (['provinsi', 'kabupaten_kota', 'kecamatan', 'desa'] as $bucket) {
            foreach (($administrativeHints[$bucket] ?? []) as $hint) {
                $hintKey = $this->canonicalize((string) $hint);

                if ($hintKey === '' || $hintKey !== $tokenKey) {
                    continue;
                }

                $allowedBuckets[$bucket] = true;
            }
        }

        return empty($allowedBuckets) ? null : $allowedBuckets;
    }

    private function isCandidateAllowedByHintBucket(array $row, ?array $allowedHintBuckets): bool
    {
        if ($allowedHintBuckets === null) {
            return true;
        }

        $bucket = $this->resolveHintBucketForLevel((int) ($row['level'] ?? 0));

        return $bucket !== null && isset($allowedHintBuckets[$bucket]);
    }

    private function pickAnchor(array $candidates): ?array
    {
        if (empty($candidates)) {
            return null;
        }

        $topCandidate = $candidates[0];
        $topRankScore = $topCandidate['rank_score'] ?? $topCandidate['score'];
        $preferredHintCandidate = $this->findPreferredHintCandidate($candidates);

        $desaCandidates = array_values(array_filter($candidates, function (array $candidate) use ($topRankScore, $preferredHintCandidate) {
            $rankScore = $candidate['rank_score'] ?? $candidate['score'];

            if ($preferredHintCandidate !== null
                && !$this->isSameOrDescendantWilayah(
                    (string) ($candidate['wilayah_id'] ?? ''),
                    (string) ($preferredHintCandidate['wilayah_id'] ?? '')
                )) {
                return false;
            }

            return $candidate['level'] >= 5
                && $candidate['score'] >= 0.83
                && ($candidate['hint_score'] ?? 0) >= 0
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
        } elseif ($preferredHintCandidate !== null) {
            $anchor = $preferredHintCandidate;
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

    private function findPreferredHintCandidate(array $candidates): ?array
    {
        $hintCandidates = array_values(array_filter($candidates, static function (array $candidate): bool {
            return ($candidate['hint_score'] ?? 0) > 0
                && ($candidate['wilayah_id'] ?? '') !== '';
        }));

        if (empty($hintCandidates)) {
            return null;
        }

        usort($hintCandidates, function (array $a, array $b) use ($candidates): int {
            if (($a['hint_score'] ?? 0) !== ($b['hint_score'] ?? 0)) {
                return ($b['hint_score'] ?? 0) <=> ($a['hint_score'] ?? 0);
            }

            if (($a['level'] ?? 0) !== ($b['level'] ?? 0)) {
                return ($b['level'] ?? 0) <=> ($a['level'] ?? 0);
            }

            $descendantSupportA = $this->countDescendantCandidateSupport((string) ($a['wilayah_id'] ?? ''), $candidates);
            $descendantSupportB = $this->countDescendantCandidateSupport((string) ($b['wilayah_id'] ?? ''), $candidates);

            if ($descendantSupportA !== $descendantSupportB) {
                return $descendantSupportB <=> $descendantSupportA;
            }

            $rankA = $a['rank_score'] ?? $a['score'] ?? 0;
            $rankB = $b['rank_score'] ?? $b['score'] ?? 0;

            return $rankB <=> $rankA;
        });

        return $hintCandidates[0];
    }

    private function countDescendantCandidateSupport(string $wilayahId, array $candidates): int
    {
        if ($wilayahId === '') {
            return 0;
        }

        $support = 0;

        foreach ($candidates as $candidate) {
            $candidateId = (string) ($candidate['wilayah_id'] ?? '');

            if ($candidateId !== $wilayahId && str_starts_with($candidateId, $wilayahId)) {
                $support++;
            }
        }

        return $support;
    }

    private function isSameOrDescendantWilayah(string $wilayahId, string $ancestorWilayahId): bool
    {
        return $wilayahId !== ''
            && $ancestorWilayahId !== ''
            && str_starts_with($wilayahId, $ancestorWilayahId);
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

        // Penalty untuk token pendek (≤ 5 karakter) — terlalu ambigu
        // "PAKEL" (5), "TUGU" (4), "WADAS" (5) → base lebih rendah
        $base = $length <= 5 ? 0.6 : 0.7;

        return round($base + (($boundedLength / 15) * 0.3), 4);
    }

    private function calculateHierarchyContextScore(string $wilayahId, array $scoreById, array $tokenKeyById, string $candidateTokenKey): float
    {
        $contextScore = 0.0;
        $currentId = $wilayahId;

        while (strlen($currentId) > 3) {
            $currentId = substr($currentId, 0, strlen($currentId) - 3);

            if (!isset($scoreById[$currentId])) {
                continue;
            }

            if (($tokenKeyById[$currentId] ?? '') === $candidateTokenKey) {
                continue;
            }

            $contextScore += $scoreById[$currentId] * 0.5;
        }

        return $contextScore;
    }

    private function calculateHierarchyCompleteness(string $wilayahId, array $scoreById, array $tokenKeyById, string $candidateTokenKey): int
    {
        $completeness = 0;
        $currentId = $wilayahId;

        while (strlen($currentId) > 3) {
            $currentId = substr($currentId, 0, strlen($currentId) - 3);

            if (isset($scoreById[$currentId]) && ($tokenKeyById[$currentId] ?? '') !== $candidateTokenKey) {
                $completeness++;
            }
        }

        return $completeness;
    }

    private function calculateAdministrativeHintScore(array $candidate, array $administrativeHints): float
    {
        $candidateLevel = (int) ($candidate['level'] ?? 0);
        $candidateBucket = $this->resolveHintBucketForLevel($candidateLevel);

        $candidateName = $this->stripAdministrativePrefix((string) ($candidate['nama'] ?? ''));
        $candidateKey = $this->canonicalize($candidateName);

        if ($candidateKey === '') {
            return 0.0;
        }

        $bestBonus = 0.0;
        $hasSameBucketExactHint = false;

        // 1) Cek apakah nama kandidat cocok dengan hint di LEVEL YANG SAMA (positive signal)
        if ($candidateBucket !== null && !empty($administrativeHints[$candidateBucket])) {
            foreach ($administrativeHints[$candidateBucket] as $hint) {
                $hintKey = $this->canonicalize((string) $hint);

                if ($hintKey === '') {
                    continue;
                }

                if ($hintKey === $candidateKey) {
                    $hasSameBucketExactHint = true;
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
        }

        // 2) Cek apakah nama kandidat cocok dengan hint di LEVEL LAIN (negative signal)
        //    Contoh: desa "Kedungwaru" match hint kecamatan "Kedungwaru" → penalty
        //    Ini menandakan bahwa token sebenarnya merujuk ke kecamatan, bukan desa.
        if (!$hasSameBucketExactHint) {
            $otherBuckets = array_diff(
                ['provinsi', 'kabupaten_kota', 'kecamatan', 'desa'],
                $candidateBucket !== null ? [$candidateBucket] : []
            );

            foreach ($otherBuckets as $otherBucket) {
                if (empty($administrativeHints[$otherBucket])) {
                    continue;
                }

                foreach ($administrativeHints[$otherBucket] as $hint) {
                    $hintKey = $this->canonicalize((string) $hint);

                    if ($hintKey === '' || $hintKey !== $candidateKey) {
                        continue;
                    }

                    // Nama persis sama tapi level berbeda → strong negative signal
                    $bestBonus = min($bestBonus, self::ADMIN_HINT_LEVEL_MISMATCH_PENALTY);
                    break 2;
                }
            }
        }

        return $bestBonus;
    }

    private function resolveHintBucketForLevel(int $level): ?string
    {
        if ($level === 5) {
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

        $keywordBoundary = '(?=[\s\.,:;\/-]|$)';
        $keywords = 'PROVINSI|PROV' . $keywordBoundary
            . '|KABUPATEN|KAB' . $keywordBoundary
            . '|KOTA|KECAMATAN|KEC' . $keywordBoundary
            . '|DESA|DS' . $keywordBoundary
            . '|KELURAHAN|KEL' . $keywordBoundary
            . '|DUSUN|DSN' . $keywordBoundary;
        $pattern = '/\b(' . $keywords . ')\.?\s*[\.,:;\/-]?\s*([^,]+?)(?=\s+\b(?:' . $keywords . ')|,|$)/u';

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
            // DUSUN/DSN sengaja TIDAK dimasukkan ke bucket desa
            // karena dusun bukan level administratif yang sama dengan desa.
        ];

        return $map[$normalized] ?? null;
    }

    private function normalizeAdministrativeHint(string $value): string
    {
        $normalized = $this->normalizeLocationPart($this->stripAdministrativePrefix($value));
        $normalized = preg_replace('/\d+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = $this->removeTrailingProvinceName($normalized);

        return trim($normalized);
    }

    private function removeTrailingProvinceName(string $value): string
    {
        $provinceNames = [
            'ACEH',
            'SUMATERA UTARA',
            'SUMATERA BARAT',
            'RIAU',
            'JAMBI',
            'SUMATERA SELATAN',
            'BENGKULU',
            'LAMPUNG',
            'KEPULAUAN BANGKA BELITUNG',
            'KEPULAUAN RIAU',
            'DKI JAKARTA',
            'JAWA BARAT',
            'JAWA TENGAH',
            'DAERAH ISTIMEWA YOGYAKARTA',
            'DI YOGYAKARTA',
            'JAWA TIMUR',
            'BANTEN',
            'BALI',
            'NUSA TENGGARA BARAT',
            'NUSA TENGGARA TIMUR',
            'KALIMANTAN BARAT',
            'KALIMANTAN TENGAH',
            'KALIMANTAN SELATAN',
            'KALIMANTAN TIMUR',
            'KALIMANTAN UTARA',
            'SULAWESI UTARA',
            'SULAWESI TENGAH',
            'SULAWESI SELATAN',
            'SULAWESI TENGGARA',
            'GORONTALO',
            'SULAWESI BARAT',
            'MALUKU',
            'MALUKU UTARA',
            'PAPUA',
            'PAPUA BARAT',
        ];

        $normalized = trim($value);

        foreach ($provinceNames as $provinceName) {
            $pattern = '/\s+' . preg_quote($provinceName, '/') . '$/u';
            $normalized = preg_replace($pattern, '', $normalized) ?? $normalized;
        }

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

    private function shouldNeedConfirmation(
        ?array $anchor,
        array $candidates,
        array $hierarchyValidation,
        array $mappedWilayah,
        array $confidence,
        array $administrativeHints
    ): bool {
        if ($anchor === null) {
            return true;
        }

        if (($hierarchyValidation['status'] ?? 'consistent') === 'inconsistent') {
            return true;
        }

        $confidenceScore = (float) ($confidence['score'] ?? 0.0);

        if ($confidenceScore >= self::CONFIDENCE_REVIEW_THRESHOLD) {
            return false;
        }

        $components = $confidence['components'] ?? [];
        $anchorScore = (float) ($components['anchor_score'] ?? 0.0);
        $gapScore = (float) ($components['gap_score'] ?? 0.0);
        $hintCoverageScore = (float) ($components['hint_coverage_score'] ?? 0.0);
        $supportCount = $this->countMappedCandidateSupport($mappedWilayah, $candidates);
        $distinctTokenSupport = $this->countMappedDistinctCandidateTokens($mappedWilayah, $candidates);

        if ($confidenceScore >= 0.70 && $anchorScore >= 0.82 && $supportCount >= 2) {
            return false;
        }

        if ($confidenceScore >= 0.755 && $anchorScore >= 0.90 && $gapScore >= 0.35) {
            return false;
        }

        if ($confidenceScore >= 0.65
            && $anchorScore >= 0.82
            && $supportCount >= 2
            && $distinctTokenSupport >= 2) {
            return false;
        }

        if ($confidenceScore >= 0.68
            && $anchorScore >= 0.84
            && $supportCount >= 1
            && $hintCoverageScore >= 0.66
            && $this->countAdministrativeHints($administrativeHints) >= 2) {
            return false;
        }

        return true;
    }

    private function countMappedCandidateSupport(array $mappedWilayah, array $candidates): int
    {
        $candidateIds = [];

        foreach ($candidates as $candidate) {
            $wilayahId = (string) ($candidate['wilayah_id'] ?? '');

            if ($wilayahId !== '') {
                $candidateIds[$wilayahId] = true;
            }
        }

        $support = 0;

        foreach (['kabupaten_kota', 'kecamatan', 'desa'] as $levelKey) {
            $node = $mappedWilayah[$levelKey] ?? null;

            if (!is_array($node)) {
                continue;
            }

            $wilayahId = (string) ($node['wilayah_id'] ?? '');

            if ($wilayahId !== '' && isset($candidateIds[$wilayahId])) {
                $support++;
            }
        }

        return $support;
    }

    private function countMappedDistinctCandidateTokens(array $mappedWilayah, array $candidates): int
    {
        $mappedIds = [];

        foreach (['kabupaten_kota', 'kecamatan', 'desa'] as $levelKey) {
            $node = $mappedWilayah[$levelKey] ?? null;

            if (!is_array($node)) {
                continue;
            }

            $wilayahId = (string) ($node['wilayah_id'] ?? '');

            if ($wilayahId !== '') {
                $mappedIds[$wilayahId] = true;
            }
        }

        if (empty($mappedIds)) {
            return 0;
        }

        $tokens = [];

        foreach ($candidates as $candidate) {
            $wilayahId = (string) ($candidate['wilayah_id'] ?? '');

            if ($wilayahId === '' || !isset($mappedIds[$wilayahId])) {
                continue;
            }

            $token = $this->canonicalize((string) ($candidate['token'] ?? ''));

            if ($token !== '') {
                $tokens[$token] = true;
            }
        }

        return count($tokens);
    }

    private function countAdministrativeHints(array $administrativeHints): int
    {
        $count = 0;

        foreach ($administrativeHints as $values) {
            if (is_array($values)) {
                $count += count($values);
            }
        }

        return $count;
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

        $resolvedEnv = filter_var((string) env('NOMINATIM_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($resolvedEnv === null) {
            return false;
        }

        return $resolvedEnv;
    }

    private function shouldUseExternal(array $internalResult): bool
    {
        $confidenceScore = (float) ($internalResult['mapping']['confidence']['score'] ?? 0.0);
        $hintCoverageScore = (float) ($internalResult['mapping']['confidence']['components']['hint_coverage_score'] ?? 0.0);

        return $confidenceScore < 0.55 && $hintCoverageScore < 0.5;
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

    /**
     * Ekstrak dan format komponen wilayah dari hasil internal pipeline.
     *
     * Format output sesuai dengan format Nominatim yang valid:
     *   "Bojong Kulur, Gunung Putri, Kabupaten Bogor, Jawa Barat"
     *
     * Aturan per level:
     *   - desa/kelurahan : strip prefix (Desa/Kel/Ds), Title Case
     *   - kecamatan      : strip prefix (Kec/Kecamatan), Title Case
     *   - kabupaten_kota : PERTAHANKAN prefix "Kabupaten"/"Kota", Title Case
     *   - provinsi       : strip prefix "Provinsi"/"Prov", Title Case
     *
     * @return array ['desa', 'kecamatan', 'kabupaten', 'provinsi']
     */
    private function buildStructuredWilayahParts(array $result): array
    {
        $wilayah = $result['mapping']['wilayah'] ?? [];

        // Desa/Kelurahan: strip prefix administratif, Title Case
        $desa = '';
        if (is_array($wilayah['desa'] ?? null)) {
            $raw  = $this->stripAdministrativePrefix((string) ($wilayah['desa']['nama'] ?? ''));
            $desa = $this->toTitleCase($raw);
        }

        // Kecamatan: strip prefix, Title Case
        $kec = '';
        if (is_array($wilayah['kecamatan'] ?? null)) {
            $raw = $this->stripAdministrativePrefix((string) ($wilayah['kecamatan']['nama'] ?? ''));
            $kec = $this->toTitleCase($raw);
        }

        // Kabupaten/Kota: PERTAHANKAN prefix "Kabupaten"/"Kota" — wajib untuk Nominatim
        // Normalisasi: "KAB. BREBES" → "Kabupaten Brebes", "KABUPATEN BREBES" → "Kabupaten Brebes"
        //              "KOTA SURABAYA" → "Kota Surabaya"
        $kab = '';
        if (is_array($wilayah['kabupaten_kota'] ?? null)) {
            $rawKab = mb_strtoupper(trim((string) ($wilayah['kabupaten_kota']['nama'] ?? '')));

            // Normalisasi singkatan KAB. → KABUPATEN agar Nominatim bisa parse
            $rawKab = preg_replace('/^KAB\.?\s+/u', 'KABUPATEN ', $rawKab) ?? $rawKab;

            $kab = $this->toTitleCase($rawKab);
        }

        // Provinsi: strip prefix "Provinsi"/"Prov" (Nominatim sudah tahu ini provinsi)
        // Contoh: "PROVINSI JAWA BARAT" → "Jawa Barat"
        $prov = '';
        if (is_array($wilayah['provinsi'] ?? null)) {
            $raw  = preg_replace('/^(?:PROVINSI|PROV)\.?\s+/ui', '', mb_strtoupper((string) ($wilayah['provinsi']['nama'] ?? ''))) ?? '';
            $prov = $this->toTitleCase(trim($raw));
        }

        // Fallback: jika semua level kosong, gunakan nama anchor
        if ($desa === '' && $kec === '' && $kab === '' && $prov === '') {
            $anchorName = $this->stripAdministrativePrefix(
                (string) ($result['mapping']['anchor']['nama'] ?? '')
            );

            if ($anchorName !== '') {
                $kab = $this->toTitleCase($anchorName);
            }
        }

        return [
            'desa'      => $desa,
            'kecamatan' => $kec,
            'kabupaten' => $kab,
            'provinsi'  => $prov,
        ];
    }

    /**
     * Konversi string ke Title Case menggunakan multibyte UTF-8.
     * Contoh: "JAWA BARAT" → "Jawa Barat", "gunung putri" → "Gunung Putri"
     */
    private function toTitleCase(string $value): string
    {
        return mb_convert_case(mb_strtolower(trim($value)), MB_CASE_TITLE, 'UTF-8');
    }
}
