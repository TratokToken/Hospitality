<?php
/**
 * Minimal PHP webhook receiver. Drop on any PHP-capable host at
 * something like https://yoursite.example/tratok-webhook.php and
 * register that URL on https://hospitality.tratok.net/webhooks.php.
 *
 *   Set TRATOK_WEBHOOK_SECRET as an env var or replace the lookup below.
 */

declare(strict_types=1);

require_once __DIR__ . '/TratokClient.php';

$secret = getenv('TRATOK_WEBHOOK_SECRET') ?: '';
if ($secret === '') {
    http_response_code(500);
    echo 'misconfigured';
    exit;
}

// CRITICAL: read raw input BEFORE any framework parses it.
$raw = file_get_contents('php://input') ?: '';
$sig = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

if (!tratok_verify_webhook($secret, $raw, $sig)) {
    http_response_code(401);
    echo 'bad signature';
    exit;
}

$payload = json_decode($raw, true) ?: [];
$event   = $payload['event'] ?? '';
$data    = $payload['data']  ?? [];

switch ($event) {
    case 'booking.created':
        error_log(sprintf('[booking.created] #%s %s status=%s',
            $data['booking_id'] ?? '?', $data['booking_type'] ?? '?', $data['status'] ?? '?'));
        break;
    case 'booking.confirmed':
        error_log(sprintf('[booking.confirmed] #%s %s TRAT=%s',
            $data['booking_id'] ?? '?', $data['guest_name'] ?? '?', $data['total_trat'] ?? '?'));
        break;
    case 'booking.cancelled':
        error_log(sprintf('[booking.cancelled] #%s refund_trat=%s',
            $data['booking_id'] ?? '?', $data['refund_trat'] ?? '?'));
        break;
    case 'payment.failed':
        error_log(sprintf('[payment.failed] #%s expired_at=%s',
            $data['booking_id'] ?? '?', $data['expired_at'] ?? '?'));
        break;
    default:
        // Unknown event — ack and ignore. New events may be added without notice.
        break;
}

http_response_code(204);
