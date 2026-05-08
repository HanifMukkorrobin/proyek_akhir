<?php

namespace App\Repositories;

use Throwable;

class NominatimGeocodingRepository
{
    public function geocode(string $address): ?array
    {
        $queryAddress = trim($address);

        if ($queryAddress === '') {
            return null;
        }

        try {
            $query = [
                'q' => $queryAddress,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => 1,
            ];

            $countryCodes = trim((string) env('NOMINATIM_COUNTRYCODES', 'id'));

            if ($countryCodes !== '') {
                $query['countrycodes'] = $countryCodes;
            }

            $baseUrl = rtrim((string) env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'), '/');
            $timeout = (float) env('NOMINATIM_TIMEOUT', 6);
            $url = $baseUrl . '/search?' . http_build_query($query);

            $headers = [
                'User-Agent: ' . $this->buildUserAgent(),
                'Accept: application/json',
                'Referer: ' . (string) env('APP_URL', 'http://localhost'),
            ];

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);

            $rawBody = @file_get_contents($url, false, $context);

            if ($rawBody === false) {
                return null;
            }

            $payload = json_decode($rawBody, true);

            if (!is_array($payload) || empty($payload[0]) || !is_array($payload[0])) {
                return null;
            }

            $row = $payload[0];

            return [
                'latitude' => $this->normalizeNullableString($row['lat'] ?? null),
                'longitude' => $this->normalizeNullableString($row['lon'] ?? null),
                'display_name' => $this->normalizeNullableString($row['display_name'] ?? null),
                'importance' => isset($row['importance']) ? (float) $row['importance'] : null,
                'address' => is_array($row['address'] ?? null) ? $row['address'] : [],
            ];
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function buildUserAgent(): string
    {
        $appName = trim((string) env('APP_NAME', 'LumenApp'));
        $appUrl = trim((string) env('APP_URL', 'http://localhost'));
        $contact = trim((string) env('NOMINATIM_CONTACT', ''));

        $userAgent = $appName . '/1.0 (' . $appUrl . ')';

        if ($contact !== '') {
            $userAgent .= ' ' . $contact;
        }

        return $userAgent;
    }

    private function normalizeNullableString($value): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return $text;
    }
}