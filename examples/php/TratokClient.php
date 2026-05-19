<?php
/**
 * Tratok Hospitality API — PHP client.
 *
 * Single-file SDK. Requires PHP 8.1+ and the cURL extension.
 *
 *   require_once 'TratokClient.php';
 *   $client = new TratokClient(['api_key' => getenv('TRATOK_API_KEY')]);
 *
 *   $me = $client->me();
 *   foreach ($client->iterProperties() as $prop) { ... }
 *   $client->setPrice(roomId: 101, date: '2026-06-04', price: 219.00);
 */

declare(strict_types=1);

final class TratokAPIError extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $apiError,
        public readonly array $body = []
    ) {
        parent::__construct("[$status] $apiError");
    }
}

final class TratokRateLimitError extends TratokAPIError
{
    public function __construct(
        int $status,
        string $apiError,
        public readonly int $retryAfter,
        array $body = []
    ) {
        parent::__construct($status, $apiError, $body);
    }
}

final class TratokClient
{
    private string $apiKey;
    private string $baseUrl;
    private int    $timeoutMs;
    private bool   $autoRetry429;
    private int    $maxRetries;

    public function __construct(array $opts)
    {
        if (empty($opts['api_key'])) {
            throw new \InvalidArgumentException('api_key is required');
        }
        $this->apiKey       = $opts['api_key'];
        $this->baseUrl      = $opts['base_url']        ?? 'https://hospitality.tratok.net/api.php';
        $this->timeoutMs    = $opts['timeout_ms']      ?? 30000;
        $this->autoRetry429 = $opts['auto_retry_429']  ?? true;
        $this->maxRetries   = $opts['max_retries']     ?? 3;
    }

    // ─── Low-level dispatcher ────────────────────────────────────────
    private function call(string $endpoint, array $opts = []): array
    {
        $method = strtoupper($opts['method'] ?? 'GET');
        $params = array_merge(['endpoint' => $endpoint], $opts['params'] ?? []);
        $json   = $opts['json'] ?? null;

        $url    = $this->baseUrl . '?' . http_build_query($params);
        $attempt = 0;

        while (true) {
            $ch = curl_init($url);
            $headers = [
                'X-API-Key: ' . $this->apiKey,
                'Accept: application/json',
                'User-Agent: tratok-php/2026.05',
            ];

            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST   => $method,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_TIMEOUT_MS      => $this->timeoutMs,
                CURLOPT_HEADER          => true,
                CURLOPT_SSL_VERIFYPEER  => true,
                CURLOPT_SSL_VERIFYHOST  => 2,
            ]);

            if ($json !== null) {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $raw = curl_exec($ch);
            if ($raw === false) {
                $err = curl_error($ch);
                curl_close($ch);
                throw new \RuntimeException("cURL error: $err");
            }
            $status      = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerBlock = substr($raw, 0, $headerSize);
            $bodyRaw     = substr($raw, $headerSize);
            curl_close($ch);

            $retryAfter = 60;
            if (preg_match('/^Retry-After:\s*(\d+)/im', $headerBlock, $m)) {
                $retryAfter = (int) $m[1];
            }

            if ($status === 429 && $this->autoRetry429 && $attempt < $this->maxRetries) {
                sleep($retryAfter);
                $attempt++;
                continue;
            }

            $body = json_decode($bodyRaw, true) ?? [];
            if ($status >= 400 || ($body['success'] ?? false) !== true) {
                $message = $body['error'] ?? "HTTP $status";
                if ($status === 429) {
                    throw new TratokRateLimitError($status, $message, $retryAfter, $body);
                }
                throw new TratokAPIError($status, $message, $body);
            }
            return $body;
        }
    }

    // ─── Account ────────────────────────────────────────────────────
    public function me(): array { return $this->call('me')['data']; }

    public function rotateApiKey(): array {
        return $this->call('api_key', ['method' => 'POST'])['data'];
    }

    // ─── Properties ─────────────────────────────────────────────────
    public function listProperties(int $page = 1, int $limit = 20): array {
        return $this->call('properties', ['params' => ['page' => $page, 'limit' => $limit]]);
    }

    /** @return \Generator<array> */
    public function iterProperties(int $limit = 100): \Generator {
        $page = 1;
        while (true) {
            $r = $this->listProperties($page, $limit);
            foreach ($r['data'] as $item) yield $item;
            if ($page >= $r['pagination']['pages']) return;
            $page++;
        }
    }

    public function getProperty(int $id): array {
        return $this->call('properties', ['params' => ['id' => $id]])['data'];
    }

    // ─── Rooms ──────────────────────────────────────────────────────
    public function listRooms(int $propertyId): array {
        return $this->call('rooms', ['params' => ['property_id' => $propertyId]])['data'];
    }

    public function getRoom(int $roomId): array {
        return $this->call('rooms', ['params' => ['id' => $roomId]])['data'];
    }

    // ─── Pricing ────────────────────────────────────────────────────
    public function getPricing(int $roomId, ?string $from = null, ?string $to = null): array {
        $params = ['room_id' => $roomId];
        if ($from) $params['from'] = $from;
        if ($to)   $params['to']   = $to;
        return $this->call('pricing', ['params' => $params])['data'];
    }

    public function setPrice(int $roomId, string $date, float $price): array {
        return $this->call('pricing', [
            'method' => 'POST',
            'json'   => ['room_id' => $roomId, 'date' => $date, 'price' => $price],
        ])['data'];
    }

    public function setPriceRange(int $roomId, string $start, string $end, float $price): array {
        return $this->call('pricing_bulk', [
            'method' => 'POST',
            'json'   => ['room_id' => $roomId, 'start_date' => $start, 'end_date' => $end, 'price' => $price],
        ])['data'];
    }

    // ─── Inventory ──────────────────────────────────────────────────
    public function getInventory(int $roomId, ?string $from = null, ?string $to = null): array {
        $params = ['room_id' => $roomId];
        if ($from) $params['from'] = $from;
        if ($to)   $params['to']   = $to;
        return $this->call('inventory', ['params' => $params])['data'];
    }

    public function setInventory(int $roomId, string $date, int $available): array {
        return $this->call('inventory', [
            'method' => 'POST',
            'json'   => ['room_id' => $roomId, 'date' => $date, 'available' => $available],
        ])['data'];
    }

    public function setInventoryRange(int $roomId, string $start, string $end, int $available): array {
        return $this->call('inventory_bulk', [
            'method' => 'POST',
            'json'   => ['room_id' => $roomId, 'start_date' => $start, 'end_date' => $end, 'available' => $available],
        ])['data'];
    }

    // ─── Activities + Restaurants ───────────────────────────────────
    public function listActivities(int $page = 1, int $limit = 20): array {
        return $this->call('activities', ['params' => ['page' => $page, 'limit' => $limit]]);
    }
    public function getActivity(int $id): array {
        return $this->call('activities', ['params' => ['id' => $id]])['data'];
    }
    public function listRestaurants(int $page = 1, int $limit = 20): array {
        return $this->call('restaurants', ['params' => ['page' => $page, 'limit' => $limit]]);
    }
    public function getRestaurant(int $id): array {
        return $this->call('restaurants', ['params' => ['id' => $id]])['data'];
    }

    // ─── Bookings ───────────────────────────────────────────────────
    public function listBookings(?string $type = null, ?string $status = null, int $page = 1, int $limit = 20): array {
        $params = ['page' => $page, 'limit' => $limit];
        if ($type)   $params['type']   = $type;
        if ($status) $params['status'] = $status;
        return $this->call('bookings', ['params' => $params]);
    }

    public function getBooking(int $id): array {
        return $this->call('bookings', ['params' => ['id' => $id]])['data'];
    }

    // ─── Revenue ────────────────────────────────────────────────────
    public function revenue(?string $from = null, ?string $to = null, ?string $type = null): array {
        $params = [];
        if ($from) $params['from'] = $from;
        if ($to)   $params['to']   = $to;
        if ($type) $params['type'] = $type;
        return $this->call('revenue', ['params' => $params])['data'];
    }
}

/**
 * Verify an X-Webhook-Signature header against the raw POST body. Use the
 * RAW body bytes — re-serializing parsed JSON will not match the signature.
 */
function tratok_verify_webhook(string $secret, string $rawBody, string $signature): bool
{
    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, $signature);
}
