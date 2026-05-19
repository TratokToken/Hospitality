<?php
/**
 * Runnable demo of the PHP client.
 *
 *   export TRATOK_API_KEY=…
 *   php example-usage.php
 */

declare(strict_types=1);

require_once __DIR__ . '/TratokClient.php';

$apiKey = getenv('TRATOK_API_KEY');
if (!$apiKey) {
    fwrite(STDERR, "Set TRATOK_API_KEY in your environment.\n");
    exit(1);
}

try {
    $client = new TratokClient(['api_key' => $apiKey]);

    // 1. Connectivity check.
    $me = $client->me();
    printf("Connected as: %s (%s) — %s\n", $me['name'], $me['email'], $me['company']);
    printf("Rate limit: %d req/min\n\n", $me['api_rate_limit']);

    // 2. List properties + rooms.
    echo "Your properties:\n";
    foreach ($client->iterProperties() as $prop) {
        $approved = $prop['approved'] ? '✓' : '·pending review';
        printf("  [%d] %s (%s, %s) %s\n",
               $prop['id'], $prop['name'], $prop['city'], $prop['country'], $approved);
        foreach ($client->listRooms($prop['id']) as $room) {
            printf("      └ room %4d  %-28s %s %s/night · %d units\n",
                   $room['id'], $room['name'],
                   $room['currency'], (string)$room['price'], $room['numberOfRooms']);
        }
    }
    echo "\n";

    // 3. Push pricing for the next 30 days on the first room found.
    $firstProp = $client->listProperties(1, 1);
    if (!empty($firstProp['data'])) {
        $rooms = $client->listRooms($firstProp['data'][0]['id']);
        if (!empty($rooms)) {
            $room  = $rooms[0];
            $start = date('Y-m-d');
            $end   = date('Y-m-d', strtotime('+30 days'));
            printf("Setting room %d to %s 149 from %s to %s...\n",
                   $room['id'], $room['currency'], $start, $end);
            $r = $client->setPriceRange($room['id'], $start, $end, 149.00);
            printf("  Updated %d days.\n", $r['days_updated']);
        }
    }
    echo "\n";

    // 4. Recent confirmed bookings.
    echo "Last 10 confirmed bookings (any type):\n";
    $recent = $client->listBookings(status: 'confirmed', limit: 10);
    foreach ($recent['data'] as $b) {
        printf("  #%-6d %-10s %s → %s  %-25s %s %s  (%s TRAT)\n",
            $b['id'], $b['booking_type'],
            $b['check_in'], $b['check_out'] ?? '·',
            $b['guest_name'],
            $b['currency'], (string)$b['total_price'],
            (string)$b['total_trat']);
    }
    echo "\n";

    // 5. Revenue last 30 days.
    $rev = $client->revenue();
    printf("Revenue %s → %s: USD %.2f gross / USD %.2f net (%d bookings)\n",
           $rev['period']['from'], $rev['period']['to'],
           $rev['totals']['gross_revenue'],
           $rev['totals']['net_revenue'],
           $rev['totals']['total_bookings']);

} catch (TratokAPIError $e) {
    fwrite(STDERR, "API error: " . $e->getMessage() . "\n");
    exit(2);
}
