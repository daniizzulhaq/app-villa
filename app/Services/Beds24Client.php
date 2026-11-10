<?php

namespace App\Services;

use App\Exceptions\Beds24Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Read-only Beds24 API V2 client. Never changes reservations, rates or inventory. */
class Beds24Client
{
    private const BASE_URL = 'https://api.beds24.com/v2';

    private const CREDENTIALS_FILE = 'integrations/beds24-credentials.enc';

    public function connect(string $inviteCode): void
    {
        if (trim($inviteCode) === '') {
            throw new Beds24Exception('Invite code is required.');
        }

        Cache::store('file')->lock('beds24-auth', 30)->block(5, function () use ($inviteCode) {
            $inviteHash = hash('sha256', trim($inviteCode));
            try {
                $existing = $this->savedCredentials();
            } catch (Beds24Exception) {
                $existing = []; // A fresh invite code can replace unreadable credentials.
            }
            if (isset($existing['invite_hash']) && hash_equals($existing['invite_hash'], $inviteHash)) {
                return; // Already exchanged; use/refresh the saved tokens instead.
            }
            // No automatic retries: an invite code is single-use.
            $credentials = $this->request('/authentication/setup', ['code' => trim($inviteCode)]);
            $this->saveCredentials($credentials, null, $inviteHash);
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function properties(bool $includeRooms = false): array
    {
        return $this->list('/properties', $includeRooms ? ['includeAllRooms' => 'true'] : []);
    }

    /** @return array<int, array<string, mixed>> */
    public function bookings(int $propertyId, int $roomId): array
    {
        return $this->list('/bookings', [
            'propertyId' => $propertyId,
            'roomId' => $roomId,
            'arrivalFrom' => '2000-01-01',
            'status' => ['confirmed', 'new', 'request', 'cancelled'],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function list(string $path, array $filters): array
    {
        $properties = [];
        $query = $filters;
        $seen = [];
        for ($page = 0; $page < 100; $page++) {
            $response = $this->request($path, ['token' => $this->accessToken()], $query);
            $rows = $response['data'] ?? null;
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new Beds24Exception('Beds24 returned an invalid data list.');
            }
            foreach ($rows as $property) {
                if (! is_array($property) || empty($property['id'])) {
                    throw new Beds24Exception('Beds24 returned a record without an ID.');
                }
                $properties[] = $property;
            }
            if (($response['pages']['nextPageExists'] ?? null) === false) {
                return $properties;
            }
            $next = $response['pages']['nextPageLink'] ?? null;
            if (! $next) {
                return $properties;
            }
            if (! is_string($next) || isset($seen[$next])) {
                throw new Beds24Exception('Beds24 returned invalid pagination.');
            }
            $seen[$next] = true;
            // Never forward credentials to a nextPageLink supplied by another host.
            $parts = parse_url($next);
            if ($parts === false
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
                || (isset($parts['host']) && $parts['host'] !== 'api.beds24.com')
                || (isset($parts['scheme']) && $parts['scheme'] !== 'https')
                || (isset($parts['path']) && ! in_array($parts['path'], ['/v2'.$path, $path, ''], true))) {
                throw new Beds24Exception('Beds24 returned an unexpected pagination URL.');
            }
            parse_str($parts['query'] ?? '', $query);
            // A pagination link may only change page, never the property/room filters.
            $query = $filters + ['page' => $query['page'] ?? null];
        }

        throw new Beds24Exception('Beds24 pagination limit exceeded.');
    }

    private function accessToken(): string
    {
        $direct = trim((string) config('services.beds24.token'));
        if ($direct !== '') {
            return $direct;
        }

        return Cache::store('file')->lock('beds24-auth', 30)->block(5, function () {
            $credentials = $this->savedCredentials();
            if (! empty($credentials['token']) && ($credentials['expires_at'] ?? 0) > now()->timestamp + 60) {
                return $credentials['token'];
            }
            $refreshToken = $credentials['refreshToken'] ?? config('services.beds24.refresh_token');
            if (! is_string($refreshToken) || trim($refreshToken) === '') {
                throw new Beds24Exception('Beds24 is not connected. Run php artisan propertyme:beds24-connect, or configure BEDS24_TOKEN for a long-life token.');
            }
            $response = $this->request('/authentication/token', ['refreshToken' => $refreshToken]);
            $this->saveCredentials($response, $refreshToken, $credentials['invite_hash'] ?? null);

            return $response['token'];
        });
    }

    private function request(string $path, array $headers, array $query = []): array
    {
        if (config('propertyme.channel_manager_provider') !== 'beds24') {
            throw new Beds24Exception('Beds24 is disabled. The active channel manager is Hostex.');
        }
        try {
            $response = Http::acceptJson()->withHeaders($headers)
                ->connectTimeout(10)->timeout(20)->withoutRedirecting()
                ->get(self::BASE_URL.$path, $query);
        } catch (ConnectionException) {
            throw new Beds24Exception('Could not reach Beds24. Check the connection and try again.');
        }
        if (! $response->successful()) {
            // Raw responses/exceptions may contain guest data or credentials: never echo them.
            throw new Beds24Exception(match ($response->status()) {
                401 => 'Beds24 rejected the credential. The invite code/token may be invalid, expired or already used.',
                403 => 'Beds24 access denied. Check read permissions, property access and IP whitelist.',
                429 => 'Beds24 API limit reached. Wait before trying again.',
                default => 'Beds24 request failed (HTTP '.$response->status().').',
            });
        }
        $body = $response->json();
        if (! is_array($body) || ($body['success'] ?? true) === false || isset($body['error'])) {
            throw new Beds24Exception('Beds24 returned an unsuccessful or invalid API response.');
        }

        return $body;
    }

    private function saveCredentials(array $response, ?string $previousRefreshToken = null, ?string $inviteHash = null): void
    {
        $token = $response['token'] ?? null;
        $refreshToken = $response['refreshToken'] ?? $previousRefreshToken;
        if (! is_string($token) || $token === '' || ! is_string($refreshToken) || $refreshToken === '') {
            throw new Beds24Exception('Beds24 did not return the required access and refresh tokens.');
        }
        $credentials = [
            'invite_hash' => $inviteHash,
            'token' => $token,
            'refreshToken' => $refreshToken,
            'expires_at' => now()->timestamp + max(1, min(86400, (int) ($response['expiresIn'] ?? 3600))),
        ];
        if (! Storage::disk('local')->put(self::CREDENTIALS_FILE, Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR)))) {
            throw new Beds24Exception('Could not securely save Beds24 credentials. Check private storage permissions.');
        }
    }

    private function savedCredentials(): array
    {
        if (! Storage::disk('local')->exists(self::CREDENTIALS_FILE)) {
            return [];
        }
        try {
            $credentials = json_decode(Crypt::decryptString(Storage::disk('local')->get(self::CREDENTIALS_FILE)), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new Beds24Exception('Could not decrypt saved Beds24 credentials. Reconnect using propertyme:beds24-connect.');
        }
        if (! is_array($credentials)) {
            throw new Beds24Exception('Saved Beds24 credentials are invalid. Reconnect using propertyme:beds24-connect.');
        }

        return $credentials;
    }
}
