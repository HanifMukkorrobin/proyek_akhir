<?php

namespace App\Repositories;

use Throwable;

/**
 * NominatimGeocodingRepository
 *
 * Geocoding via Nominatim menggunakan format free-text q= yang sudah terbukti valid:
 *   "Desa, Kecamatan, Kabupaten X, Provinsi Y"
 *
 * Strategi fallback progresif (drop dari bagian paling spesifik):
 *   1. "Bojong Kulur, Gunung Putri, Kabupaten Bogor, Jawa Barat"
 *   2. "Gunung Putri, Kabupaten Bogor, Jawa Barat"
 *   3. "Kabupaten Bogor, Jawa Barat"
 *   4. "Jawa Barat"
 *
 * Semua hasil dicache berdasarkan hash query agar tidak ada request duplikat
 * saat import massal.
 */
class NominatimGeocodingRepository
{
    /** Cache in-memory, hidup sepanjang satu lifecycle request PHP. */
    private array $cache = [];

    /** Waktu request terakhir (microtime) untuk rate-limiting Nominatim public. */
    private float $lastRequestTime = 0.0;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Geocode dari string free-text tunggal.
     *
     * @param  string     $address  Query Nominatim (bebas)
     * @return array|null           ['latitude', 'longitude', 'display_name', 'importance', 'address']
     */
    public function geocode(string $address): ?array
    {
        $query = trim($address);

        if ($query === '') {
            return null;
        }

        $cacheKey = md5(mb_strtolower($query));

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $result = $this->doRequest($query);
        $this->cache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Geocode dengan progressive fallback dari komponen wilayah hasil normalisasi.
     *
     * Format query yang dikirim ke Nominatim:
     *   "Bojong Kulur, Gunung Putri, Kabupaten Bogor, Jawa Barat"
     *
     * Jika tidak ditemukan, bagian paling spesifik (desa) dibuang dan dicoba ulang.
     * Proses berlanjut hingga hanya tersisa provinsi.
     *
     * @param  array      $parts  ['desa', 'kecamatan', 'kabupaten', 'provinsi']
     *                            Setiap value sudah dalam format Title Case yang benar.
     *                            Kabupaten HARUS menyertakan prefix, contoh: "Kabupaten Bogor"
     * @return array|null
     */
    public function geocodeWithFallback(array $parts): ?array
    {
        // Susun urutan dari paling spesifik ke paling umum
        $sequence = array_values(array_filter([
            $parts['desa']      ?? '',
            $parts['kecamatan'] ?? '',
            $parts['kabupaten'] ?? '',
            $parts['provinsi']  ?? '',
        ], static fn (string $p) => trim($p) !== ''));

        if (empty($sequence)) {
            return null;
        }

        // Coba dari paling lengkap, progressif buang bagian depan (paling spesifik)
        while (!empty($sequence)) {
            $query  = implode(', ', $sequence);
            $result = $this->geocode($query);

            if ($result !== null) {
                return $result;
            }

            // Buang bagian paling spesifik dan coba lagi
            array_shift($sequence);
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // HTTP Layer
    // -------------------------------------------------------------------------

    /**
     * Kirim request ke Nominatim dan kembalikan hasil terbaik.
     * Pilih berdasarkan importance tertinggi di antara semua hasil yang dikembalikan.
     */
    private function doRequest(string $query): ?array
    {
        try {
            // Rate-limit: Nominatim Usage Policy requires max 1 request/second.
            // Jika request sebelumnya kurang dari 1.1 detik lalu, tunggu sisa waktunya.
            if ($this->lastRequestTime > 0) {
                $elapsed = microtime(true) - $this->lastRequestTime;

                if ($elapsed < 1.1) {
                    usleep((int) ((1.1 - $elapsed) * 1_000_000));
                }
            }

            $this->lastRequestTime = microtime(true);

            $baseUrl = rtrim((string) env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'), '/');
            $timeout = (float) env('NOMINATIM_TIMEOUT', 12);

            $params = [
                'q'              => $query,
                'format'         => 'jsonv2',
                'addressdetails' => 1,
                'limit'          => 5,
                'countrycodes'   => trim((string) env('NOMINATIM_COUNTRYCODES', 'id')),
            ];

            $url = $baseUrl . '/search?' . http_build_query($params);

            $headers = implode("\r\n", [
                'User-Agent: '    . $this->buildUserAgent(),
                'Accept: application/json',
                'Accept-Language: id,en;q=0.9',
                'Referer: '       . (string) env('APP_URL', 'http://localhost'),
            ]);

            $context = stream_context_create([
                'http' => [
                    'method'        => 'GET',
                    'header'        => $headers,
                    'timeout'       => $timeout,
                    'ignore_errors' => true,
                ],
            ]);

            $rawBody = @file_get_contents($url, false, $context);

            if ($rawBody === false) {
                return null;
            }

            $payload = json_decode($rawBody, true);

            if (!is_array($payload) || empty($payload)) {
                return null;
            }

            $best = $this->pickBestResult($payload);

            if ($best === null) {
                return null;
            }

            return [
                'latitude'     => $this->normalizeString($best['lat'] ?? null),
                'longitude'    => $this->normalizeString($best['lon'] ?? null),
                'display_name' => $this->normalizeString($best['display_name'] ?? null),
                'importance'   => isset($best['importance']) ? (float) $best['importance'] : null,
                'address'      => is_array($best['address'] ?? null) ? $best['address'] : [],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Pilih hasil dengan importance tertinggi dari array hasil Nominatim.
     * Hanya hasil dengan lat/lon yang valid yang dipertimbangkan.
     */
    private function pickBestResult(array $payload): ?array
    {
        $valid = array_filter($payload, static function (mixed $row): bool {
            return is_array($row)
                && trim((string) ($row['lat'] ?? '')) !== ''
                && trim((string) ($row['lon'] ?? '')) !== '';
        });

        if (empty($valid)) {
            return null;
        }

        usort($valid, static function (array $a, array $b): int {
            return ((float) ($b['importance'] ?? 0)) <=> ((float) ($a['importance'] ?? 0));
        });

        return array_values($valid)[0];
    }

    private function buildUserAgent(): string
    {
        $appName = trim((string) env('APP_NAME', 'LumenApp'));
        $appUrl  = trim((string) env('APP_URL', 'http://localhost'));
        $contact = trim((string) env('NOMINATIM_CONTACT', ''));

        $ua = $appName . '/1.0 (' . $appUrl . ')';

        if ($contact !== '') {
            $ua .= ' ' . $contact;
        }

        return $ua;
    }

    private function normalizeString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}