<?php

namespace App\Repositories;

use App\Models\Mahasiswa;
use App\Models\VisitasiPeserta;
use App\Models\VisitasiRencana;
use App\Models\VisitasiRute;
use App\Models\VisitasiRuteDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class RouteSimulationRepository
{
    private const DEFAULT_START = [
        'nama' => 'PENS Hub',
        'latitude' => -7.275612,
        'longitude' => 112.793910,
    ];

    private const VEHICLE_PROFILES = [
        'mobil' => 'driving',
        'motor' => 'driving',
        'sepeda' => 'cycling',
        'jalan_kaki' => 'walking',
    ];

    public function paginate(array $filters = []): array
    {
        $query = VisitasiRencana::query()
            ->withCount('peserta')
            ->with(['rute' => function ($routeQuery) {
                $routeQuery->orderByDesc('dibuat_pada');
            }])
            ->orderByDesc('dibuat_pada')
            ->orderByDesc('visitasi_rencana_id');

        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('nama', 'ILIKE', '%' . $search . '%')
                    ->orWhere('catatan', 'ILIKE', '%' . $search . '%');
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (!empty($filters['profile'])) {
            $query->where('profile', (string) $filters['profile']);
        }

        if (!empty($filters['kendaraan'])) {
            $query->where('kendaraan', $this->resolveVehicle($filters['kendaraan']));
        }

        if (!empty($filters['pemilik_user_id'])) {
            $this->applyOwnershipScope($query, (string) $filters['pemilik_user_id']);
        } elseif (!empty($filters['dibuat_oleh_user_id'])) {
            $query->where('dibuat_oleh_user_id', $filters['dibuat_oleh_user_id']);
        }

        $pagination = paginate_builder(
            $query,
            (int) ($filters['page'] ?? 1),
            (int) ($filters['per_page'] ?? 10),
        );

        return [
            'data' => array_map(function (VisitasiRencana $rencana): array {
                return $this->transformSavedSummary($rencana);
            }, $pagination['data']->all()),
            'halaman_sekarang' => $pagination['halaman_sekarang'],
            'per_halaman' => $pagination['per_halaman'],
            'total_data' => $pagination['total_data'],
            'total_halaman' => $pagination['total_halaman'],
        ];
    }

    public function find(string $simulationId, ?string $dibuatOlehUserId = null): ?array
    {
        $query = VisitasiRencana::query()
            ->with([
                'peserta' => function ($participantQuery) {
                    $participantQuery
                        ->with('mahasiswa.wilayah')
                        ->orderBy('urutan_input');
                },
                'rute' => function ($routeQuery) {
                    $routeQuery
                        ->with(['detail' => function ($detailQuery) {
                            $detailQuery
                                ->with('mahasiswa.wilayah')
                                ->orderBy('urutan');
                        }])
                        ->orderByDesc('dibuat_pada');
                },
            ])
            ->where('visitasi_rencana_id', $simulationId);

        if ($dibuatOlehUserId !== null) {
            $this->applyOwnershipScope($query, $dibuatOlehUserId);
        }

        $rencana = $query->first();

        if ($rencana === null) {
            return null;
        }

        return $this->transformSavedDetail($rencana);
    }

    public function deleteOwned(string $simulationId, string $userId): ?array
    {
        $query = VisitasiRencana::query()
            ->where('visitasi_rencana_id', $simulationId);
        $this->applyOwnershipScope($query, $userId);

        /** @var VisitasiRencana|null $rencana */
        $rencana = $query->first();

        if ($rencana === null) {
            return null;
        }

        if (Schema::hasColumn('visitasi_rencana', 'dihapus_oleh_user_id')) {
            $rencana->dihapus_oleh_user_id = $userId;
            $rencana->save();
        }

        $rencana->delete();

        return [
            'simulation_id' => $rencana->visitasi_rencana_id,
            'deleted' => true,
        ];
    }

    public function simulate(array $payload): array
    {
        $vehicle = $this->resolveVehicle($payload['kendaraan'] ?? null);
        $profile = $this->resolveProfile($payload['profile'] ?? $this->profileForVehicle($vehicle));
        $service = $this->resolveService($payload);
        $start = $this->resolvePoint($payload['titik_awal'] ?? null, self::DEFAULT_START, 'titik_awal');
        $end = $this->resolveSimulationEnd($payload, $start);
        $students = $this->resolveStudents($payload['mahasiswa_ids'] ?? []);

        $stops = $this->buildStops($start, $students, $end);
        $this->assertWaypointLimit($stops);

        $compareRoutes = filter_var($payload['bandingkan_jalur'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $manualCandidate = null;
        $optimizedCandidate = null;
        $comparison = null;

        if ($compareRoutes) {
            $manualCandidate = $this->buildRouteCandidate('manual', 'Urutan Input', 'route', $profile, $stops, $end !== null);
            $optimizedCandidate = $this->buildRouteCandidate('optimized', 'Optimasi OSRM', 'trip', $profile, $stops, $end !== null);
            $comparison = $this->buildRouteComparison($manualCandidate, $optimizedCandidate);
            $selectedCandidate = ($comparison['recommended_key'] ?? 'optimized') === 'manual'
                ? $manualCandidate
                : $optimizedCandidate;
        } else {
            $selectedCandidate = $this->buildRouteCandidate(
                $service === 'route' ? 'manual' : 'optimized',
                $service === 'route' ? 'Urutan Input' : 'Optimasi OSRM',
                $service,
                $profile,
                $stops,
                $end !== null
            );
        }

        $route = $selectedCandidate['route'];
        $orderedWaypoints = $selectedCandidate['ordered_waypoints'];

        $result = [
            'simulation_id' => null,
            'nama_rencana' => $this->resolvePlanName($payload),
            'deskripsi' => $this->resolveDescription($payload),
            'provider' => 'osrm',
            'requested_service' => $service,
            'service' => $selectedCandidate['service'],
            'profile' => $profile,
            'kendaraan' => $vehicle,
            'optimize_order' => $selectedCandidate['optimize_order'],
            'is_persisted' => false,
            'titik_awal' => $start,
            'titik_akhir' => $end,
            'input_waypoints' => $stops,
            'ordered_waypoints' => $orderedWaypoints,
            'ordered_mahasiswa' => $selectedCandidate['ordered_mahasiswa'],
            'route_candidates' => [
                'manual' => $manualCandidate === null ? null : $this->summarizeRouteCandidate($manualCandidate),
                'optimized' => $optimizedCandidate === null ? null : $this->summarizeRouteCandidate($optimizedCandidate),
            ],
            'comparison' => $comparison,
            'route' => [
                'distance_meters' => $this->normalizeNullableFloat($route['distance'] ?? null),
                'duration_seconds' => $this->normalizeNullableFloat($route['duration'] ?? null),
                'weight' => $this->normalizeNullableFloat($route['weight'] ?? null),
                'weight_name' => $route['weight_name'] ?? null,
                'geometry' => $route['geometry'] ?? null,
                'legs' => $route['legs'] ?? [],
                'leg_summaries' => $selectedCandidate['leg_summaries'],
            ],
            'osrm' => [
                'code' => $selectedCandidate['osrm']['code'] ?? null,
                'waypoints' => $selectedCandidate['osrm']['waypoints'] ?? [],
                'raw_response' => $selectedCandidate['osrm']['raw_response'] ?? [],
            ],
        ];

        if (filter_var($payload['simpan'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $result = $this->persistSimulation($result, $payload);
        }

        return $result;
    }

    private function transformSavedSummary(VisitasiRencana $rencana): array
    {
        /** @var VisitasiRute|null $route */
        $route = $rencana->rute->first();
        $parameterInput = is_array($route?->parameter_input) ? $route->parameter_input : [];

        return [
            'simulation_id' => $rencana->visitasi_rencana_id,
            'route_id' => $route?->visitasi_rute_id,
            'nama_rencana' => $rencana->nama,
            'judul' => $rencana->nama,
            'deskripsi' => $rencana->catatan,
            'catatan' => $rencana->catatan,
            'provider' => $route?->provider ?? 'osrm',
            'service' => $route?->service ?? 'trip',
            'profile' => $rencana->profile,
            'kendaraan' => $rencana->kendaraan ?? $this->vehicleForProfile($rencana->profile),
            'optimize_order' => (bool) $rencana->optimize_order,
            'status' => $rencana->status,
            'peserta_count' => (int) ($rencana->peserta_count ?? $rencana->peserta()->count()),
            'titik_awal' => $this->transformRencanaStart($rencana),
            'titik_akhir' => $this->transformRencanaEnd($rencana),
            'route' => $route === null ? null : [
                'distance_meters' => $this->normalizeNullableFloat($route->distance_meters),
                'duration_seconds' => $this->normalizeNullableFloat($route->duration_seconds),
                'weight' => $this->normalizeNullableFloat($route->weight),
                'legs_count' => is_array($route->legs) ? count($route->legs) : 0,
                'steps_count' => $this->countRouteSteps($route->legs),
            ],
            'route_candidates' => $parameterInput['route_candidates'] ?? null,
            'comparison' => $parameterInput['comparison'] ?? null,
            'dibuat_pada' => $rencana->dibuat_pada,
            'diubah_pada' => $rencana->diubah_pada,
        ];
    }

    private function transformSavedDetail(VisitasiRencana $rencana): array
    {
        /** @var VisitasiRute|null $route */
        $route = $rencana->rute->first();
        $osrmResponse = is_array($route?->osrm_response) ? $route->osrm_response : [];
        $parameterInput = is_array($route?->parameter_input) ? $route->parameter_input : [];
        $orderedWaypoints = $route?->waypoints;

        if (!is_array($orderedWaypoints)) {
            $orderedWaypoints = $route === null ? [] : $this->buildWaypointsFromRouteDetails($route);
        }

        $legs = is_array($route?->legs) ? $route->legs : [];

        return [
            'simulation_id' => $rencana->visitasi_rencana_id,
            'route_id' => $route?->visitasi_rute_id,
            'nama_rencana' => $rencana->nama,
            'judul' => $rencana->nama,
            'deskripsi' => $rencana->catatan,
            'catatan' => $rencana->catatan,
            'provider' => $route?->provider ?? 'osrm',
            'service' => $route?->service ?? 'trip',
            'profile' => $rencana->profile,
            'kendaraan' => $rencana->kendaraan ?? $this->vehicleForProfile($rencana->profile),
            'optimize_order' => (bool) $rencana->optimize_order,
            'is_persisted' => true,
            'status' => $rencana->status,
            'titik_awal' => $this->transformRencanaStart($rencana),
            'titik_akhir' => $this->transformRencanaEnd($rencana),
            'peserta' => $rencana->peserta->map(function (VisitasiPeserta $peserta): array {
                return $this->transformParticipant($peserta);
            })->values()->all(),
            'ordered_waypoints' => $orderedWaypoints,
            'ordered_mahasiswa' => $this->resolveOrderedStudents($orderedWaypoints),
            'route_candidates' => $parameterInput['route_candidates'] ?? null,
            'comparison' => $parameterInput['comparison'] ?? null,
            'route' => [
                'distance_meters' => $this->normalizeNullableFloat($route?->distance_meters),
                'duration_seconds' => $this->normalizeNullableFloat($route?->duration_seconds),
                'weight' => $this->normalizeNullableFloat($route?->weight),
                'weight_name' => $osrmResponse['routes'][0]['weight_name']
                    ?? $osrmResponse['trips'][0]['weight_name']
                    ?? null,
                'geometry' => $route?->geometry,
                'legs' => $legs,
                'leg_summaries' => $this->buildLegSummaries($legs, $orderedWaypoints),
            ],
            'route_details' => $route === null ? [] : $route->detail->map(function (VisitasiRuteDetail $detail): array {
                return $this->transformRouteDetail($detail);
            })->values()->all(),
            'osrm' => [
                'code' => $osrmResponse['code'] ?? null,
                'waypoints' => $osrmResponse['waypoints'] ?? [],
                'raw_response' => $osrmResponse,
            ],
            'dibuat_pada' => $rencana->dibuat_pada,
            'diubah_pada' => $rencana->diubah_pada,
        ];
    }

    private function transformRencanaStart(VisitasiRencana $rencana): array
    {
        return [
            'nama' => $rencana->titik_awal_nama,
            'latitude' => $this->normalizeNullableFloat($rencana->titik_awal_latitude),
            'longitude' => $this->normalizeNullableFloat($rencana->titik_awal_longitude),
        ];
    }

    private function transformRencanaEnd(VisitasiRencana $rencana): ?array
    {
        if ($rencana->titik_akhir_latitude === null || $rencana->titik_akhir_longitude === null) {
            return null;
        }

        return [
            'nama' => $rencana->titik_akhir_nama,
            'latitude' => $this->normalizeNullableFloat($rencana->titik_akhir_latitude),
            'longitude' => $this->normalizeNullableFloat($rencana->titik_akhir_longitude),
        ];
    }

    private function transformParticipant(VisitasiPeserta $peserta): array
    {
        $mahasiswa = $peserta->mahasiswa;
        $wilayah = $mahasiswa?->wilayah;

        return [
            'visitasi_peserta_id' => $peserta->visitasi_peserta_id,
            'mahasiswa_id' => $peserta->mahasiswa_id,
            'urutan_input' => (int) $peserta->urutan_input,
            'urutan_rute' => $peserta->urutan_rute === null ? null : (int) $peserta->urutan_rute,
            'latitude' => $this->normalizeNullableFloat($peserta->latitude),
            'longitude' => $this->normalizeNullableFloat($peserta->longitude),
            'status_lokasi' => $peserta->status_lokasi,
            'mahasiswa' => $mahasiswa === null ? null : [
                'mahasiswa_id' => $mahasiswa->mahasiswa_id,
                'nama' => $mahasiswa->nama,
                'alamat' => $mahasiswa->alamat,
                'wilayah_id' => $mahasiswa->wilayah_id,
                'latitude' => $this->normalizeNullableFloat($mahasiswa->latitude),
                'longitude' => $this->normalizeNullableFloat($mahasiswa->longitude),
                'wilayah' => $wilayah === null ? null : [
                    'wilayah_id' => $wilayah->wilayah_id,
                    'nama' => $wilayah->nama,
                    'latitude' => $this->normalizeNullableFloat($wilayah->latitude),
                    'longitude' => $this->normalizeNullableFloat($wilayah->longitude),
                ],
            ],
        ];
    }

    private function transformRouteDetail(VisitasiRuteDetail $detail): array
    {
        return [
            'visitasi_rute_detail_id' => $detail->visitasi_rute_detail_id,
            'visitasi_peserta_id' => $detail->visitasi_peserta_id,
            'mahasiswa_id' => $detail->mahasiswa_id,
            'urutan' => (int) $detail->urutan,
            'tipe' => $detail->tipe,
            'nama' => $detail->nama,
            'latitude' => $this->normalizeNullableFloat($detail->latitude),
            'longitude' => $this->normalizeNullableFloat($detail->longitude),
            'leg_index' => $detail->leg_index === null ? null : (int) $detail->leg_index,
            'distance_meters' => $this->normalizeNullableFloat($detail->distance_meters),
            'duration_seconds' => $this->normalizeNullableFloat($detail->duration_seconds),
            'steps' => $detail->steps,
        ];
    }

    private function buildWaypointsFromRouteDetails(VisitasiRute $route): array
    {
        return $route->detail->map(function (VisitasiRuteDetail $detail): array {
            return [
                'order' => (int) $detail->urutan,
                'waypoint_index' => (int) $detail->urutan,
                'input_index' => (int) $detail->urutan,
                'type' => $detail->tipe,
                'mahasiswa_id' => $detail->mahasiswa_id,
                'nama' => $detail->nama,
                'alamat' => $detail->mahasiswa?->alamat,
                'wilayah_id' => $detail->mahasiswa?->wilayah_id,
                'wilayah_nama' => $detail->mahasiswa?->wilayah?->nama,
                'latitude' => $this->normalizeNullableFloat($detail->latitude),
                'longitude' => $this->normalizeNullableFloat($detail->longitude),
                'snapped_latitude' => null,
                'snapped_longitude' => null,
                'snapped_name' => null,
                'snap_distance_meters' => null,
            ];
        })->values()->all();
    }

    private function countRouteSteps($legs): int
    {
        if (!is_array($legs)) {
            return 0;
        }

        return array_reduce($legs, static function (int $total, $leg): int {
            return $total + (is_array($leg['steps'] ?? null) ? count($leg['steps']) : 0);
        }, 0);
    }

    private function resolveProfile($profile): string
    {
        $resolved = trim((string) ($profile ?: env('OSRM_PROFILE', 'driving')));

        if ($resolved === '') {
            $resolved = 'driving';
        }

        if (!preg_match('/^[a-z0-9_-]+$/i', $resolved)) {
            throw new InvalidArgumentException('Profile OSRM tidak valid.');
        }

        return $resolved;
    }

    private function applyOwnershipScope($query, string $userId): void
    {
        $query->where(function ($builder) use ($userId) {
            $applied = false;
            foreach (['dibuat_oleh_user_id', 'dosen_user_id', 'dosen_id'] as $column) {
                if (!Schema::hasColumn('visitasi_rencana', $column)) {
                    continue;
                }

                $method = $applied ? 'orWhere' : 'where';
                $builder->{$method}($column, $userId);
                $applied = true;
            }

            if (!$applied) {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    private function resolveVehicle($vehicle): string
    {
        $resolved = strtolower(trim((string) ($vehicle ?: 'mobil')));
        $aliases = [
            'car' => 'mobil',
            'driving' => 'mobil',
            'motorcycle' => 'motor',
            'bike' => 'sepeda',
            'cycling' => 'sepeda',
            'bicycle' => 'sepeda',
            'foot' => 'jalan_kaki',
            'walk' => 'jalan_kaki',
            'walking' => 'jalan_kaki',
            'jalan kaki' => 'jalan_kaki',
        ];

        $resolved = $aliases[$resolved] ?? $resolved;

        if (!array_key_exists($resolved, self::VEHICLE_PROFILES)) {
            throw new InvalidArgumentException('Opsi kendaraan tidak valid.');
        }

        return $resolved;
    }

    private function profileForVehicle(string $vehicle): string
    {
        return self::VEHICLE_PROFILES[$vehicle] ?? 'driving';
    }

    private function vehicleForProfile(?string $profile): string
    {
        return match ($profile) {
            'cycling' => 'sepeda',
            'walking' => 'jalan_kaki',
            default => 'mobil',
        };
    }

    private function resolvePlanName(array $payload, $now = null): string
    {
        $name = trim((string) ($payload['nama_rencana'] ?? $payload['judul'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        return 'Simulasi Visitasi ' . ($now ?: Carbon::now())->format('Y-m-d H:i');
    }

    private function resolveDescription(array $payload): ?string
    {
        $description = trim((string) ($payload['deskripsi'] ?? $payload['catatan'] ?? ''));

        return $description === '' ? null : $description;
    }

    private function resolveService(array $payload): string
    {
        $service = strtolower(trim((string) ($payload['service'] ?? '')));

        if ($service === '') {
            $optimizeOrder = filter_var($payload['optimize_order'] ?? true, FILTER_VALIDATE_BOOLEAN);
            return $optimizeOrder ? 'trip' : 'route';
        }

        if (!in_array($service, ['trip', 'route'], true)) {
            throw new InvalidArgumentException('Service OSRM harus route atau trip.');
        }

        return $service;
    }

    private function resolvePoint($point, array $fallback, string $fieldName): array
    {
        $source = is_array($point) ? $point : $fallback;
        $name = trim((string) ($source['nama'] ?? $source['name'] ?? $fallback['nama']));
        $latitude = $this->normalizeCoordinate($source['latitude'] ?? $source['lat'] ?? null, -90, 90, $fieldName . '.latitude');
        $longitude = $this->normalizeCoordinate($source['longitude'] ?? $source['lon'] ?? $source['lng'] ?? null, -180, 180, $fieldName . '.longitude');

        return [
            'nama' => $name !== '' ? $name : $fallback['nama'],
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function resolveOptionalPoint($point, string $fieldName): ?array
    {
        if (!is_array($point) || empty($point)) {
            return null;
        }

        return $this->resolvePoint($point, [
            'nama' => 'Titik Akhir',
            'latitude' => null,
            'longitude' => null,
        ], $fieldName);
    }

    private function resolveSimulationEnd(array $payload, array $start): ?array
    {
        $explicitEnd = $this->resolveOptionalPoint($payload['titik_akhir'] ?? null, 'titik_akhir');

        if ($explicitEnd !== null) {
            return $explicitEnd;
        }

        $returnToStart = filter_var(
            $payload['kembali_ke_titik_awal'] ?? true,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($returnToStart === false) {
            return null;
        }

        return [
            'nama' => 'Kembali ke ' . $start['nama'],
            'latitude' => $start['latitude'],
            'longitude' => $start['longitude'],
        ];
    }

    private function resolveStudents($ids): array
    {
        if (!is_array($ids)) {
            throw new InvalidArgumentException('mahasiswa_ids wajib berupa array.');
        }

        $orderedIds = [];
        foreach ($ids as $id) {
            $normalized = trim((string) $id);
            if ($normalized !== '' && !in_array($normalized, $orderedIds, true)) {
                $orderedIds[] = $normalized;
            }
        }

        if (empty($orderedIds)) {
            throw new InvalidArgumentException('Minimal satu mahasiswa tujuan wajib dipilih.');
        }

        $rows = Mahasiswa::query()
            ->with('wilayah')
            ->whereIn('mahasiswa_id', $orderedIds)
            ->get()
            ->keyBy('mahasiswa_id');

        $students = [];
        $missing = [];
        $invalid = [];

        foreach ($orderedIds as $index => $id) {
            /** @var Mahasiswa|null $mahasiswa */
            $mahasiswa = $rows->get($id);

            if ($mahasiswa === null) {
                $missing[] = $id;
                continue;
            }

            $latitude = $this->normalizeNullableFloat($mahasiswa->latitude);
            $longitude = $this->normalizeNullableFloat($mahasiswa->longitude);
            $isValidAddress = (bool) ($mahasiswa->is_valid_address ?? true);

            if (!$isValidAddress || $latitude === null || $longitude === null) {
                $invalid[] = $mahasiswa->nama . ' (' . $mahasiswa->mahasiswa_id . ')';
                continue;
            }

            $students[] = [
                'input_index' => $index + 1,
                'type' => 'mahasiswa',
                'mahasiswa_id' => $mahasiswa->mahasiswa_id,
                'nama' => $mahasiswa->nama,
                'alamat' => $mahasiswa->alamat,
                'wilayah_id' => $mahasiswa->wilayah_id,
                'wilayah_nama' => $mahasiswa->wilayah?->nama,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'status_lokasi' => $mahasiswa->geocoding_status,
            ];
        }

        if (!empty($missing)) {
            throw new InvalidArgumentException('Mahasiswa tidak ditemukan: ' . implode(', ', $missing) . '.');
        }

        if (!empty($invalid)) {
            throw new InvalidArgumentException('Mahasiswa tujuan memiliki lokasi tidak valid: ' . implode(', ', $invalid) . '.');
        }

        return $students;
    }

    private function buildStops(array $start, array $students, ?array $end): array
    {
        $stops = [[
            'input_index' => 0,
            'type' => 'start',
            'mahasiswa_id' => null,
            'nama' => $start['nama'],
            'alamat' => null,
            'wilayah_id' => null,
            'wilayah_nama' => null,
            'latitude' => $start['latitude'],
            'longitude' => $start['longitude'],
            'status_lokasi' => 'manual_start',
        ]];

        foreach ($students as $student) {
            $stops[] = $student;
        }

        if ($end !== null) {
            $stops[] = [
                'input_index' => count($stops),
                'type' => 'end',
                'mahasiswa_id' => null,
                'nama' => $end['nama'],
                'alamat' => null,
                'wilayah_id' => null,
                'wilayah_nama' => null,
                'latitude' => $end['latitude'],
                'longitude' => $end['longitude'],
                'status_lokasi' => 'manual_end',
            ];
        }

        return $stops;
    }

    private function assertWaypointLimit(array $stops): void
    {
        $maxWaypoints = max(2, (int) env('OSRM_MAX_WAYPOINTS', 25));

        if (count($stops) > $maxWaypoints) {
            throw new InvalidArgumentException('Jumlah waypoint melebihi batas OSRM_MAX_WAYPOINTS (' . $maxWaypoints . ').');
        }
    }

    private function buildRouteCandidate(string $key, string $label, string $service, string $profile, array $stops, bool $hasEndPoint): array
    {
        $osrmPayload = $this->requestOsrm($service, $profile, $stops, $hasEndPoint);
        $route = $this->extractRoute($osrmPayload, $service);
        $orderedWaypoints = $this->resolveOrderedWaypoints($stops, $osrmPayload, $service);

        return [
            'key' => $key,
            'label' => $label,
            'service' => $service,
            'profile' => $profile,
            'optimize_order' => $service === 'trip',
            'route' => $route,
            'ordered_waypoints' => $orderedWaypoints,
            'ordered_mahasiswa' => $this->resolveOrderedStudents($orderedWaypoints),
            'leg_summaries' => $this->buildLegSummaries($route['legs'] ?? [], $orderedWaypoints),
            'osrm' => [
                'code' => $osrmPayload['code'] ?? null,
                'waypoints' => $osrmPayload['waypoints'] ?? [],
                'raw_response' => $osrmPayload,
            ],
        ];
    }

    private function buildRouteComparison(array $manualCandidate, array $optimizedCandidate): array
    {
        $durationWeight = 0.7;
        $distanceWeight = 0.3;
        $scores = $this->calculateCandidateScores([
            'manual' => $manualCandidate,
            'optimized' => $optimizedCandidate,
        ], $durationWeight, $distanceWeight);

        $recommendedKey = $this->resolveRecommendedCandidateKey($scores, $manualCandidate, $optimizedCandidate);
        $manualDistance = $this->normalizeNullableFloat($manualCandidate['route']['distance'] ?? null);
        $optimizedDistance = $this->normalizeNullableFloat($optimizedCandidate['route']['distance'] ?? null);
        $manualDuration = $this->normalizeNullableFloat($manualCandidate['route']['duration'] ?? null);
        $optimizedDuration = $this->normalizeNullableFloat($optimizedCandidate['route']['duration'] ?? null);
        $distanceSaving = $manualDistance === null || $optimizedDistance === null ? null : $manualDistance - $optimizedDistance;
        $durationSaving = $manualDuration === null || $optimizedDuration === null ? null : $manualDuration - $optimizedDuration;

        return [
            'criteria' => [
                'method' => 'weighted_duration_distance',
                'duration_weight' => $durationWeight,
                'distance_weight' => $distanceWeight,
                'lower_score_is_better' => true,
            ],
            'recommended_key' => $recommendedKey,
            'recommended_label' => $recommendedKey === 'manual' ? 'Urutan Input' : 'Optimasi OSRM',
            'candidates' => [
                'manual' => $this->summarizeRouteCandidate($manualCandidate, $scores['manual'] ?? null),
                'optimized' => $this->summarizeRouteCandidate($optimizedCandidate, $scores['optimized'] ?? null),
            ],
            'optimized_vs_manual' => [
                'distance_delta_meters' => $manualDistance === null || $optimizedDistance === null ? null : $optimizedDistance - $manualDistance,
                'duration_delta_seconds' => $manualDuration === null || $optimizedDuration === null ? null : $optimizedDuration - $manualDuration,
                'distance_saving_meters' => $distanceSaving,
                'duration_saving_seconds' => $durationSaving,
                'distance_saving_percent' => $this->calculatePercent($distanceSaving, $manualDistance),
                'duration_saving_percent' => $this->calculatePercent($durationSaving, $manualDuration),
            ],
        ];
    }

    private function calculateCandidateScores(array $candidates, float $durationWeight, float $distanceWeight): array
    {
        $durations = [];
        $distances = [];

        foreach ($candidates as $candidate) {
            $duration = $this->normalizeNullableFloat($candidate['route']['duration'] ?? null);
            $distance = $this->normalizeNullableFloat($candidate['route']['distance'] ?? null);

            if ($duration !== null && $duration > 0) {
                $durations[] = $duration;
            }

            if ($distance !== null && $distance > 0) {
                $distances[] = $distance;
            }
        }

        $minDuration = empty($durations) ? null : min($durations);
        $minDistance = empty($distances) ? null : min($distances);
        $scores = [];

        foreach ($candidates as $key => $candidate) {
            $duration = $this->normalizeNullableFloat($candidate['route']['duration'] ?? null);
            $distance = $this->normalizeNullableFloat($candidate['route']['distance'] ?? null);
            $durationRatio = $duration !== null && $minDuration !== null && $minDuration > 0 ? $duration / $minDuration : 1;
            $distanceRatio = $distance !== null && $minDistance !== null && $minDistance > 0 ? $distance / $minDistance : 1;
            $scores[$key] = round(($durationRatio * $durationWeight) + ($distanceRatio * $distanceWeight), 6);
        }

        return $scores;
    }

    private function resolveRecommendedCandidateKey(array $scores, array $manualCandidate, array $optimizedCandidate): string
    {
        $manualScore = $scores['manual'] ?? null;
        $optimizedScore = $scores['optimized'] ?? null;

        if ($manualScore === null || $optimizedScore === null) {
            return 'optimized';
        }

        if (abs($manualScore - $optimizedScore) <= 0.000001) {
            $manualDuration = $this->normalizeNullableFloat($manualCandidate['route']['duration'] ?? null);
            $optimizedDuration = $this->normalizeNullableFloat($optimizedCandidate['route']['duration'] ?? null);
            $manualDistance = $this->normalizeNullableFloat($manualCandidate['route']['distance'] ?? null);
            $optimizedDistance = $this->normalizeNullableFloat($optimizedCandidate['route']['distance'] ?? null);

            if ($optimizedDuration !== null && $manualDuration !== null && $optimizedDuration < $manualDuration) {
                return 'optimized';
            }

            if ($optimizedDistance !== null && $manualDistance !== null && $optimizedDistance < $manualDistance) {
                return 'optimized';
            }

            return 'manual';
        }

        return $optimizedScore < $manualScore ? 'optimized' : 'manual';
    }

    private function summarizeRouteCandidate(array $candidate, ?float $score = null): array
    {
        $route = $candidate['route'];

        return [
            'key' => $candidate['key'],
            'label' => $candidate['label'],
            'service' => $candidate['service'],
            'optimize_order' => $candidate['optimize_order'],
            'score' => $score,
            'distance_meters' => $this->normalizeNullableFloat($route['distance'] ?? null),
            'duration_seconds' => $this->normalizeNullableFloat($route['duration'] ?? null),
            'legs_count' => is_array($route['legs'] ?? null) ? count($route['legs']) : 0,
            'steps_count' => $this->countRouteSteps($route['legs'] ?? []),
            'waypoint_names' => array_map(static function (array $waypoint): string {
                return (string) ($waypoint['nama'] ?? '');
            }, $candidate['ordered_waypoints']),
        ];
    }

    private function calculatePercent(?float $value, ?float $base): ?float
    {
        if ($value === null || $base === null || $base == 0.0) {
            return null;
        }

        return round(($value / $base) * 100, 2);
    }

    private function requestOsrm(string $service, string $profile, array $stops, bool $hasEndPoint): array
    {
        $baseUrl = rtrim((string) env('OSRM_BASE_URL', 'https://router.project-osrm.org'), '/');
        $timeout = max(1.0, (float) env('OSRM_TIMEOUT', 20));
        $coordinates = implode(';', array_map(function (array $stop): string {
            return $this->formatCoordinatePair($stop['longitude'], $stop['latitude']);
        }, $stops));

        $params = [
            'steps' => 'true',
            'geometries' => 'geojson',
            'overview' => 'full',
            'annotations' => 'duration,distance',
        ];

        if ($service === 'trip') {
            $params['roundtrip'] = 'false';
            $params['source'] = 'first';
            $params['destination'] = $hasEndPoint ? 'last' : 'any';
        } else {
            $params['alternatives'] = 'false';
        }

        $url = $baseUrl . '/' . $service . '/v1/' . rawurlencode($profile) . '/' . $coordinates . '?' . http_build_query($params);
        $payload = $this->requestJson($url, $timeout);

        if (($payload['code'] ?? null) !== 'Ok') {
            $code = (string) ($payload['code'] ?? 'Unknown');
            $message = (string) ($payload['message'] ?? 'OSRM tidak mengembalikan rute.');
            throw new RuntimeException('OSRM error ' . $code . ': ' . $message);
        }

        $payload['_request'] = [
            'url' => $url,
            'service' => $service,
            'profile' => $profile,
            'params' => $params,
        ];

        return $payload;
    }

    private function requestJson(string $url, float $timeout): array
    {
        $headers = [
            'Accept: application/json',
            'User-Agent: ' . $this->buildUserAgent(),
        ];

        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => (int) ceil($timeout),
                CURLOPT_TIMEOUT => (int) ceil($timeout),
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $rawBody = curl_exec($handle);
            $error = curl_error($handle);
            $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);

            if ($rawBody === false || $rawBody === '') {
                throw new RuntimeException('Gagal menghubungi OSRM: ' . ($error ?: 'empty response'));
            }

            if ($statusCode >= 500) {
                throw new RuntimeException('OSRM server error HTTP ' . $statusCode . '.');
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);
            $rawBody = @file_get_contents($url, false, $context);

            if ($rawBody === false || $rawBody === '') {
                throw new RuntimeException('Gagal menghubungi OSRM.');
            }
        }

        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            throw new RuntimeException('Response OSRM bukan JSON valid.');
        }

        return $payload;
    }

    private function extractRoute(array $payload, string $service): array
    {
        $key = $service === 'trip' ? 'trips' : 'routes';
        $routes = $payload[$key] ?? [];

        if (!is_array($routes) || empty($routes) || !is_array($routes[0])) {
            throw new RuntimeException('Response OSRM tidak memiliki route/trip utama.');
        }

        return $routes[0];
    }

    private function resolveOrderedWaypoints(array $stops, array $payload, string $service): array
    {
        $waypoints = is_array($payload['waypoints'] ?? null) ? $payload['waypoints'] : [];

        if ($service === 'route') {
            return array_map(function (array $stop, int $index) use ($waypoints) {
                return $this->buildOrderedWaypoint($stop, $index, $waypoints[$index] ?? null);
            }, $stops, array_keys($stops));
        }

        $sortable = [];
        foreach ($stops as $inputIndex => $stop) {
            $osrmWaypoint = is_array($waypoints[$inputIndex] ?? null) ? $waypoints[$inputIndex] : [];
            $waypointIndex = isset($osrmWaypoint['waypoint_index']) ? (int) $osrmWaypoint['waypoint_index'] : $inputIndex;
            $sortable[] = [
                'waypoint_index' => $waypointIndex,
                'input_index' => $inputIndex,
                'stop' => $stop,
                'osrm_waypoint' => $osrmWaypoint,
            ];
        }

        usort($sortable, static function (array $a, array $b): int {
            return $a['waypoint_index'] <=> $b['waypoint_index'];
        });

        return array_map(function (array $row, int $orderIndex) {
            return $this->buildOrderedWaypoint($row['stop'], $orderIndex, $row['osrm_waypoint'], $row['waypoint_index'], $row['input_index']);
        }, $sortable, array_keys($sortable));
    }

    private function buildOrderedWaypoint(array $stop, int $orderIndex, ?array $osrmWaypoint, ?int $waypointIndex = null, ?int $inputIndex = null): array
    {
        $location = is_array($osrmWaypoint['location'] ?? null) ? $osrmWaypoint['location'] : null;

        return [
            'order' => $orderIndex,
            'waypoint_index' => $waypointIndex ?? $orderIndex,
            'input_index' => $inputIndex ?? ($stop['input_index'] ?? $orderIndex),
            'type' => $stop['type'],
            'mahasiswa_id' => $stop['mahasiswa_id'],
            'nama' => $stop['nama'],
            'alamat' => $stop['alamat'],
            'wilayah_id' => $stop['wilayah_id'],
            'wilayah_nama' => $stop['wilayah_nama'],
            'latitude' => $stop['latitude'],
            'longitude' => $stop['longitude'],
            'snapped_latitude' => $location[1] ?? null,
            'snapped_longitude' => $location[0] ?? null,
            'snapped_name' => $osrmWaypoint['name'] ?? null,
            'snap_distance_meters' => $this->normalizeNullableFloat($osrmWaypoint['distance'] ?? null),
        ];
    }

    private function buildLegSummaries(array $legs, array $orderedWaypoints): array
    {
        $summaries = [];

        foreach ($legs as $index => $leg) {
            $summaries[] = [
                'leg_index' => $index,
                'from' => $orderedWaypoints[$index] ?? null,
                'to' => $orderedWaypoints[$index + 1] ?? null,
                'summary' => $leg['summary'] ?? '',
                'distance_meters' => $this->normalizeNullableFloat($leg['distance'] ?? null),
                'duration_seconds' => $this->normalizeNullableFloat($leg['duration'] ?? null),
                'steps_count' => is_array($leg['steps'] ?? null) ? count($leg['steps']) : 0,
            ];
        }

        return $summaries;
    }

    private function resolveOrderedStudents(array $orderedWaypoints): array
    {
        return array_values(array_filter($orderedWaypoints, static function (array $waypoint): bool {
            return $waypoint['type'] === 'mahasiswa';
        }));
    }

    private function persistSimulation(array $result, array $payload): array
    {
        return DB::transaction(function () use ($result, $payload) {
            $rencanaId = (string) Str::uuid();
            $routeId = (string) Str::uuid();
            $now = Carbon::now();
            $start = $result['titik_awal'];
            $end = $result['titik_akhir'];
            $description = $this->resolveDescription($payload);
            $planName = $this->resolvePlanName($payload, $now);
            $actorUserId = $payload['dibuat_oleh_user_id'] ?? null;
            $dosenUserId = $payload['dosen_user_id'] ?? $actorUserId;

            $rencana = VisitasiRencana::query()->create($this->filterTableColumns('visitasi_rencana', [
                'visitasi_rencana_id' => $rencanaId,
                'nama' => $planName,
                'nama_rencana' => $planName,
                'dosen_user_id' => $dosenUserId,
                'dosen_id' => $dosenUserId,
                'titik_awal_nama' => $start['nama'],
                'titik_awal_label' => $start['nama'],
                'titik_awal_latitude' => $start['latitude'],
                'titik_awal_longitude' => $start['longitude'],
                'titik_akhir_nama' => $end['nama'] ?? null,
                'titik_akhir_latitude' => $end['latitude'] ?? null,
                'titik_akhir_longitude' => $end['longitude'] ?? null,
                'profile' => $result['profile'],
                'kendaraan' => $result['kendaraan'],
                'jenis_kendaraan' => $result['kendaraan'],
                'optimize_order' => $result['optimize_order'],
                'status' => 'simulated',
                'catatan' => $description,
                'deskripsi' => $description,
                'perkiraan_total_jarak_km' => $result['route']['distance_meters'] === null
                    ? null
                    : $result['route']['distance_meters'] / 1000,
                'perkiraan_total_menit' => $result['route']['duration_seconds'] === null
                    ? null
                    : (int) ceil($result['route']['duration_seconds'] / 60),
                'dibuat_oleh_user_id' => $actorUserId,
                'diubah_oleh_user_id' => $actorUserId,
            ]));

            $participantByMahasiswa = [];
            foreach ($result['input_waypoints'] as $stop) {
                if ($stop['type'] !== 'mahasiswa') {
                    continue;
                }

                $participant = VisitasiPeserta::query()->create($this->filterTableColumns('visitasi_peserta', [
                    'visitasi_peserta_id' => (string) Str::uuid(),
                    'visitasi_rencana_id' => $rencana->visitasi_rencana_id,
                    'mahasiswa_id' => $stop['mahasiswa_id'],
                    'urutan_input' => $stop['input_index'],
                    'urutan' => $stop['input_index'],
                    'urutan_rute' => null,
                    'prioritas' => 0,
                    'latitude' => $stop['latitude'],
                    'longitude' => $stop['longitude'],
                    'status_lokasi' => $stop['status_lokasi'],
                ]));

                $participantByMahasiswa[$stop['mahasiswa_id']] = $participant;
            }

            foreach ($result['ordered_mahasiswa'] as $order => $student) {
                $participant = $participantByMahasiswa[$student['mahasiswa_id']] ?? null;
                if ($participant instanceof VisitasiPeserta) {
                    $participant->urutan_rute = $order + 1;
                    $participant->save();
                }
            }

            $route = VisitasiRute::query()->create($this->filterTableColumns('visitasi_rute', [
                'visitasi_rute_id' => $routeId,
                'visitasi_rencana_id' => $rencana->visitasi_rencana_id,
                'provider' => $result['provider'],
                'metode_kalkulasi' => $result['service'],
                'service' => $result['service'],
                'profile' => $result['profile'],
                'osrm_profile' => $result['profile'],
                'distance_meters' => $result['route']['distance_meters'],
                'total_jarak_km' => $result['route']['distance_meters'] === null
                    ? null
                    : $result['route']['distance_meters'] / 1000,
                'duration_seconds' => $result['route']['duration_seconds'],
                'total_estimasi_menit' => $result['route']['duration_seconds'] === null
                    ? null
                    : (int) ceil($result['route']['duration_seconds'] / 60),
                'weight' => $result['route']['weight'],
                'geometry' => $result['route']['geometry'],
                'legs' => $result['route']['legs'],
                'waypoints' => $result['ordered_waypoints'],
                'osrm_response' => $result['osrm']['raw_response'],
                'hasil_osrm_raw' => $result['osrm']['raw_response'],
                'parameter_input' => [
                    'titik_awal' => $result['titik_awal'],
                    'titik_akhir' => $result['titik_akhir'],
                    'mahasiswa_ids' => $payload['mahasiswa_ids'] ?? [],
                    'kendaraan' => $result['kendaraan'],
                    'profile' => $result['profile'],
                    'requested_service' => $result['requested_service'] ?? $result['service'],
                    'service' => $result['service'],
                    'route_candidates' => $result['route_candidates'] ?? null,
                    'comparison' => $result['comparison'] ?? null,
                ],
                'status' => 'success',
            ]));

            foreach ($result['ordered_waypoints'] as $order => $waypoint) {
                $participant = $waypoint['mahasiswa_id'] !== null
                    ? ($participantByMahasiswa[$waypoint['mahasiswa_id']] ?? null)
                    : null;
                $incomingLeg = $order > 0 ? ($result['route']['legs'][$order - 1] ?? null) : null;

                VisitasiRuteDetail::query()->create($this->filterTableColumns('visitasi_rute_detail', [
                    'visitasi_rute_detail_id' => (string) Str::uuid(),
                    'visitasi_rute_id' => $route->visitasi_rute_id,
                    'visitasi_rencana_id' => $rencana->visitasi_rencana_id,
                    'visitasi_peserta_id' => $participant?->visitasi_peserta_id,
                    'mahasiswa_id' => $waypoint['mahasiswa_id'],
                    'urutan' => $order,
                    'urutan_kunjungan' => $order,
                    'tipe' => $waypoint['type'],
                    'tipe_titik' => $waypoint['type'],
                    'nama' => $waypoint['nama'],
                    'label' => $waypoint['nama'],
                    'latitude' => $waypoint['latitude'],
                    'longitude' => $waypoint['longitude'],
                    'leg_index' => $incomingLeg !== null ? $order - 1 : null,
                    'distance_meters' => $this->normalizeNullableFloat($incomingLeg['distance'] ?? null),
                    'jarak_dari_sebelumnya_km' => isset($incomingLeg['distance'])
                        ? $this->normalizeNullableFloat($incomingLeg['distance']) / 1000
                        : 0,
                    'duration_seconds' => $this->normalizeNullableFloat($incomingLeg['duration'] ?? null),
                    'estimasi_ke_sini_menit' => isset($incomingLeg['duration'])
                        ? (int) ceil($this->normalizeNullableFloat($incomingLeg['duration']) / 60)
                        : 0,
                    'estimasi_kumulatif_menit' => 0,
                    'steps' => $incomingLeg['steps'] ?? null,
                ]));
            }

            $result['simulation_id'] = $rencana->visitasi_rencana_id;
            $result['route_id'] = $route->visitasi_rute_id;
            $result['nama_rencana'] = $rencana->nama;
            $result['judul'] = $rencana->nama;
            $result['deskripsi'] = $description;
            $result['catatan'] = $description;
            $result['is_persisted'] = true;

            return $result;
        });
    }

    private function normalizeCoordinate($value, float $min, float $max, string $fieldName): float
    {
        $number = $this->normalizeNullableFloat($value);

        if ($number === null || $number < $min || $number > $max) {
            throw new InvalidArgumentException($fieldName . ' tidak valid.');
        }

        return $number;
    }

    private function filterTableColumns(string $table, array $payload): array
    {
        static $columnsByTable = [];

        if (!array_key_exists($table, $columnsByTable)) {
            $columnsByTable[$table] = array_flip(Schema::getColumnListing($table));
        }

        return array_filter(
            $payload,
            static function (string $column) use ($columnsByTable, $table): bool {
                return isset($columnsByTable[$table][$column]);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    private function normalizeNullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;
        return is_finite($number) ? $number : null;
    }

    private function formatCoordinatePair(float $longitude, float $latitude): string
    {
        return rtrim(rtrim(number_format($longitude, 7, '.', ''), '0'), '.')
            . ','
            . rtrim(rtrim(number_format($latitude, 7, '.', ''), '0'), '.');
    }

    private function buildUserAgent(): string
    {
        $appName = trim((string) env('APP_NAME', 'GeoVisit'));
        $appUrl = trim((string) env('APP_URL', 'http://localhost'));

        return $appName . '/1.0 (' . $appUrl . ')';
    }
}
