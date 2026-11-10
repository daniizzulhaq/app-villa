<?php

namespace App\Services;

use App\Exceptions\HostexException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Hostex OpenAPI v3 read-only client for PropertyMe reporting. */
class HostexClient
{
    private const BASE_URL = 'https://api.hostex.io/v3';

    /** @return array<int, array<string, mixed>> */
    public function properties(?int $id = null): array
    {
        if ($id !== null && $id < 1) {
            throw new HostexException('Hostex property ID must be a positive number.');
        }

        return $this->listing('/properties', 'properties', 'id', $id === null ? [] : ['id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function reservations(int $propertyId, string $from, string $to): array
    {
        if ($propertyId < 1 || ! $this->validDate($from) || ! $this->validDate($to) || $to < $from) {
            throw new HostexException('A valid property ID and date range are required.');
        }

        $cursor = new \DateTimeImmutable($from);
        $until = new \DateTimeImmutable($to);
        if ($cursor->diff($until)->days > 3660) {
            throw new HostexException('Use a reservation date range of at most ten years.');
        }
        $reservations = [];
        while ($cursor <= $until) {
            // Hostex requires paired date filters and a maximum 180-day window.
            $end = min($cursor->modify('+179 days'), $until);
            $rows = $this->listing('/reservations', 'reservations', 'stay_code', [
                'property_id' => $propertyId,
                'start_check_in_date' => $cursor->format('Y-m-d'),
                'end_check_in_date' => $end->format('Y-m-d'),
            ]);
            foreach ($rows as $row) {
                $reservations[$row['stay_code']] = $row;
            }
            $cursor = $end->modify('+1 day');
        }

        return array_values($reservations);
    }

    private function listing(string $path, string $key, string $identity, array $filters): array
    {
        $items = [];
        $seen = [];
        $limit = 100;
        for ($page = 0; $page < 100; $page++) {
            // Construct our own offsets; never follow URLs supplied by the API.
            $data = $this->request($path, $filters + ['offset' => $page * $limit, 'limit' => $limit]);
            $rows = $data[$key] ?? null;
            if (! is_array($rows) || ! array_is_list($rows) || count($rows) > $limit) {
                throw new HostexException('Hostex returned an invalid '.$key.' list.');
            }
            foreach ($rows as $row) {
                $id = is_array($row) ? ($row[$identity] ?? null) : null;
                if ((! is_int($id) && ! is_string($id)) || (string) $id === '' || isset($seen[(string) $id])) {
                    throw new HostexException('Hostex returned a missing or repeated record ID. Retry the check.');
                }
                if (isset($filters['id']) && (string) $id !== (string) $filters['id']) {
                    throw new HostexException('Hostex returned a different property than requested.');
                }
                if (isset($filters['property_id']) && (string) ($row['property_id'] ?? '') !== (string) $filters['property_id']) {
                    throw new HostexException('Hostex returned a reservation for a different property.');
                }
                $seen[(string) $id] = true;
                $items[] = $row;
            }
            // Reservations have no total field; a full page needs another read.
            // Property totals are validated but not used to truncate a full page.
            if (isset($data['total']) && (! is_int($data['total']) || $data['total'] < 0)) {
                throw new HostexException('Hostex returned an invalid total.');
            }
            if (count($rows) < $limit) {
                if (isset($data['total']) && $data['total'] > count($items)) {
                    throw new HostexException('Hostex returned an incomplete property list. Retry the check.');
                }

                return $items;
            }
        }

        throw new HostexException('Hostex pagination limit reached. Use a smaller reservation date range.');
    }

    private function request(string $path, array $query): array
    {
        if (config('propertyme.channel_manager_provider') !== 'hostex') {
            throw new HostexException('Hostex is not the configured channel manager.');
        }
        $token = trim((string) config('services.hostex.access_token'));
        if ($token === '') {
            throw new HostexException('Hostex is not connected. Set HOSTEX_ACCESS_TOKEN in .env, then run php artisan config:clear.');
        }
        try {
            $response = Http::acceptJson()->withHeaders(['Hostex-Access-Token' => $token])
                ->connectTimeout(10)->timeout(20)->withoutRedirecting()
                ->get(self::BASE_URL.$path, $query);
        } catch (ConnectionException) {
            throw new HostexException('Could not reach Hostex. Check the connection and try again.');
        }
        if (! $response->successful()) {
            throw new HostexException('Hostex request failed (HTTP '.$response->status().').');
        }
        $body = $response->json();
        if (! is_array($body) || ! is_int($body['error_code'] ?? null)) {
            throw new HostexException('Hostex returned an invalid API response.');
        }
        // Hostex returns HTTP 200 even for authentication/subscription failures.
        if ($body['error_code'] !== 200) {
            throw new HostexException(match ($body['error_code']) {
                401 => 'Hostex rejected the Access Token. Check or replace it in OpenAPI settings.',
                403 => 'Hostex denied access to this resource. Check property access.',
                404 => 'Hostex could not find the requested resource.',
                420 => 'Hostex account/subscription does not allow API access. Check Pro access and subscription status.',
                429 => 'Hostex API rate limit reached. Wait before trying again.',
                default => 'Hostex request failed (API code '.$body['error_code'].').',
            });
        }
        if (! is_array($body['data'] ?? null)) {
            throw new HostexException('Hostex returned an invalid data object.');
        }

        return $body['data'];
    }

    private function validDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date;
    }
}
