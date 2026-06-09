<?php

namespace App\Repositories;

use RuntimeException;

/**
 * OSRM Routing Repository
 *
 * Menangani komunikasi dengan OSRM routing engine.
 * Public OSRM server: http://router.project-osrm.org
 *
 * Mendukung profil:
 *   - motor  → 'driving' + exclude=motorway (hindari tol)
 *   - mobil  + lewat_tol=true  → 'driving' (gunakan tol, default)
 *   - mobil  + lewat_tol=false → 'driving' + exclude=motorway
 *
 * Fallback otomatis ke Haversine jika OSRM tidak tersedia.
 */
class OsrmRoutingRepository
{
    private string $baseUrl;

    private int $timeoutSeconds;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) env('OSRM_BASE_URL', 'http://router.project-osrm.org'), '/');
        $this->timeoutSeconds = (int) env('OSRM_TIMEOUT', 10);
    }

    /**
     * Memanggil OSRM /trip endpoint untuk optimasi urutan kunjungan (round-trip TSP).
     *
     * @param array<array{lat: float, lng: float, label?: string}> $waypoints Daftar koordinat
     * @param string $osrmProfile Profil OSRM ('driving')
     * @param bool $excludeMotorway Hindari jalan tol/bebas hambatan
     * @return array{success: bool, waypoints: array, legs: array, total_distance_m: float, total_duration_s: float, geometry: array, raw: array}
     */
    public function getTrip(array $waypoints, string $osrmProfile, bool $excludeMotorway = false): array
    {
        if (count($waypoints) < 2) {
            throw new \InvalidArgumentException('Minimal 2 waypoint diperlukan untuk simulasi rute.');
        }

        $coordString = implode(';', array_map(
            fn($wp) => $wp['lng'] . ',' . $wp['lat'],
            $waypoints
        ));

        $params = [
            'roundtrip' => 'true',
            'source' => 'first',
            'overview' => 'full',
            'geometries' => 'polyline',
            'annotations' => 'false',
            'steps' => 'true',
        ];

        // Disable exclude=motorway when using the public project-osrm.org demo server,
        // as the demo server does not support exclude flag combinations and will return InvalidValue.
        if ($excludeMotorway && strpos($this->baseUrl, 'project-osrm.org') === false) {
            $params['exclude'] = 'motorway';
        }

        $queryString = http_build_query($params);
        $url = "{$this->baseUrl}/trip/v1/{$osrmProfile}/{$coordString}?{$queryString}";

        $response = $this->makeHttpRequest($url);

        if (!$response['success']) {
            return $this->fallbackHaversine($waypoints, true);
        }

        $data = $response['data'];

        if (($data['code'] ?? '') !== 'Ok' || empty($data['trips'])) {
            return $this->fallbackHaversine($waypoints, true);
        }

        return $this->parseTripResponse($data, $waypoints);
    }

    /**
     * Menghitung jarak antara dua titik menggunakan formula Haversine (offline fallback).
     */
    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }

    /**
     * Menentukan OSRM profile dan opsi exclude berdasarkan jenis kendaraan.
     *
     * @return array{profile: string, exclude_motorway: bool}
     */
    public function resolveProfileOptions(string $jenisKendaraan, bool $lewatTol): array
    {
        // Motor: selalu hindari jalan tol/bebas hambatan
        if ($jenisKendaraan === 'motor') {
            return ['profile' => 'driving', 'exclude_motorway' => true];
        }

        // Mobil tanpa tol: hindari jalan tol/bebas hambatan
        if ($jenisKendaraan === 'mobil' && !$lewatTol) {
            return ['profile' => 'driving', 'exclude_motorway' => true];
        }

        // Mobil dengan tol: gunakan semua jalan termasuk tol
        return ['profile' => 'driving', 'exclude_motorway' => false];
    }

    /**
     * Parse response OSRM /trip ke format internal.
     */
    private function parseTripResponse(array $data, array $originalWaypoints): array
    {
        $trip = $data['trips'][0];
        $waypointOrder = array_map(fn($wp) => (int) $wp['waypoint_index'], $data['waypoints'] ?? []);

        // Bangun urutan waypoint sesuai hasil optimasi OSRM
        $orderedWaypoints = [];
        $tripWaypoints = $data['waypoints'] ?? [];
        usort($tripWaypoints, fn($a, $b) => ($a['trips_index'] ?? 0) <=> ($b['trips_index'] ?? 0));

        foreach ($tripWaypoints as $tw) {
            $origIdx = (int) ($tw['waypoint_index'] ?? 0);
            $orderedWaypoints[] = array_merge(
                $originalWaypoints[$origIdx] ?? [],
                [
                    'original_index' => $origIdx,
                    'snapped_lat' => $tw['location'][1] ?? ($originalWaypoints[$origIdx]['lat'] ?? 0),
                    'snapped_lng' => $tw['location'][0] ?? ($originalWaypoints[$origIdx]['lng'] ?? 0),
                ]
            );
        }

        // Ekstrak legs (segmen antar titik)
        $legs = [];
        $cumulativeMinutes = 0;
        $cumulativeKm = 0;

        foreach (($trip['legs'] ?? []) as $index => $leg) {
            $distanceKm = round(($leg['distance'] ?? 0) / 1000, 2);
            $durationMin = (int) round(($leg['duration'] ?? 0) / 60);
            $cumulativeKm += $distanceKm;
            $cumulativeMinutes += $durationMin;

            $legs[] = [
                'urutan' => $index + 1,
                'jarak_km' => $distanceKm,
                'durasi_menit' => $durationMin,
                'kumulatif_km' => round($cumulativeKm, 2),
                'kumulatif_menit' => $cumulativeMinutes,
                'geometri_polyline' => $leg['geometry'] ?? null,
            ];
        }

        return [
            'success' => true,
            'engine' => 'osrm',
            'ordered_waypoints' => $orderedWaypoints,
            'legs' => $legs,
            'total_distance_km' => round(($trip['distance'] ?? 0) / 1000, 2),
            'total_duration_minutes' => (int) round(($trip['duration'] ?? 0) / 60),
            'geometry' => $trip['geometry'] ?? null,
            'raw' => $data,
        ];
    }

    /**
     * Fallback kalkulasi Haversine ketika OSRM tidak tersedia.
     * Menggunakan Nearest Neighbor greedy untuk optimasi urutan.
     */
    private function fallbackHaversine(array $waypoints, bool $roundtrip): array
    {
        $start = $waypoints[0];
        $remaining = array_slice($waypoints, 1);
        $ordered = [$start];
        $legs = [];
        $totalKm = 0;
        $totalMin = 0;
        $current = $start;
        $avgSpeedKmh = 40; // estimasi kecepatan rata-rata motor dalam kota

        while (!empty($remaining)) {
            $nearest = null;
            $nearestDist = PHP_FLOAT_MAX;
            $nearestIdx = -1;

            foreach ($remaining as $idx => $wp) {
                $dist = $this->haversineKm(
                    (float) $current['lat'], (float) $current['lng'],
                    (float) $wp['lat'], (float) $wp['lng']
                );

                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearest = $wp;
                    $nearestIdx = $idx;
                }
            }

            if ($nearest === null) {
                break;
            }

            $durationMin = (int) round(($nearestDist / $avgSpeedKmh) * 60);
            $totalKm += $nearestDist;
            $totalMin += $durationMin;

            $legs[] = [
                'urutan' => count($legs) + 1,
                'jarak_km' => round($nearestDist, 2),
                'durasi_menit' => $durationMin,
                'kumulatif_km' => round($totalKm, 2),
                'kumulatif_menit' => $totalMin,
                'geometri_polyline' => null,
            ];

            $ordered[] = $nearest;
            $current = $nearest;
            array_splice($remaining, $nearestIdx, 1);
        }

        // Tambah leg kembali ke titik awal
        if ($roundtrip && count($ordered) > 1) {
            $returnDist = $this->haversineKm(
                (float) $current['lat'], (float) $current['lng'],
                (float) $start['lat'], (float) $start['lng']
            );
            $returnMin = (int) round(($returnDist / $avgSpeedKmh) * 60);
            $totalKm += $returnDist;
            $totalMin += $returnMin;

            $legs[] = [
                'urutan' => count($legs) + 1,
                'jarak_km' => round($returnDist, 2),
                'durasi_menit' => $returnMin,
                'kumulatif_km' => round($totalKm, 2),
                'kumulatif_menit' => $totalMin,
                'geometri_polyline' => null,
            ];
        }

        return [
            'success' => true,
            'engine' => 'haversine_fallback',
            'ordered_waypoints' => array_map(function ($wp, $i) { return array_merge($wp, ['original_index' => $i]); }, $ordered, array_keys($ordered)),
            'legs' => $legs,
            'total_distance_km' => round($totalKm, 2),
            'total_duration_minutes' => $totalMin,
            'geometry' => null,
            'raw' => [],
        ];
    }

    /**
     * Melakukan HTTP GET request ke OSRM.
     */
    private function makeHttpRequest(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeoutSeconds,
                'header' => "Accept: application/json\r\nUser-Agent: GeoVisitPJJIT/1.0\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $rawResponse = @file_get_contents($url, false, $context);

        if ($rawResponse === false) {
            return ['success' => false, 'data' => [], 'error' => 'OSRM tidak dapat dihubungi.'];
        }

        $decoded = json_decode($rawResponse, true);

        if (!is_array($decoded)) {
            return ['success' => false, 'data' => [], 'error' => 'Response OSRM tidak valid.'];
        }

        return ['success' => true, 'data' => $decoded, 'error' => null];
    }
}
