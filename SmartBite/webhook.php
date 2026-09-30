<?php
/**
 * PayMongo webhook endpoint.
 * Register this URL in the PayMongo Dashboard > Developers > Webhooks as:
 *   https://YOUR-DOMAIN/webhook.php
 * Subscribe it to at least: checkout_session.payment.paid, payment.paid, payment.failed
 *
 * This is the ONLY place that is allowed to mark a payment as paid.
 * The browser redirect (payment_success.php) is never trusted on its own,
 * since a user can hit that URL without actually paying.
 */
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

$rawBody = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

function paymongo_verify_signature(string $rawBody, string $signatureHeader, string $secret): bool
{
    if ($signatureHeader === '' || $secret === '') return false;

    // Header looks like: t=1687423200,te=<test-sig>,li=<live-sig>
    $parts = [];
    foreach (explode(',', $signatureHeader) as $chunk) {
        [$k, $v] = array_pad(explode('=', $chunk, 2), 2, '');
        $parts[$k] = $v;
    }
    if (empty($parts['t'])) return false;

    $expectedSig = $parts['li'] ?? ($parts['te'] ?? '');
    if ($expectedSig === '') return false;

    $signedPayload = $parts['t'] . '.' . $rawBody;
    $computed = hash_hmac('sha256', $signedPayload, $secret);

    return hash_equals($computed, $expectedSig);
}

if (!paymongo_verify_signature($rawBody, $signatureHeader, PAYMONGO_WEBHOOK_SECRET)) {
    http_response_code(400);
    log_activity(null, 'webhook_rejected', 'Invalid or missing PayMongo signature');
    echo json_encode(['error' => 'invalid signature']);
    exit;
}

$event = json_decode($rawBody, true);
$type = $event['data']['attributes']['type'] ?? '';
$eventData = $event['data']['attributes']['data'] ?? [];

// Try to resolve which order this event is about, from whichever
// reference PayMongo attached: the checkout session id we stored on
// creation, or the reference_number we set to our own order_number.
$sessionId = $eventData['id'] ?? null;
$referenceNumber = $eventData['attributes']['reference_number']
    ?? $eventData['attributes']['payments'][0]['attributes']['description'] ?? null;

$pdo = db();
$order = null;

if ($sessionId) {
    $s = $pdo->prepare('SELECT o.* FROM orders o JOIN payments p ON p.order_id=o.id WHERE p.provider_reference=?');
    $s->execute([$sessionId]);
    $order = $s->fetch();
}
if (!$order && $referenceNumber) {
    $s = $pdo->prepare('SELECT * FROM orders WHERE order_number=?');
    $s->execute([$referenceNumber]);
    $order = $s->fetch();
}

if (!$order) {
    http_response_code(200); // Acknowledge so PayMongo doesn't keep retrying an event we can't map.
    log_activity(null, 'webhook_unmatched', $rawBody);
    echo json_encode(['received' => true, 'matched' => false]);
    exit;
}

$isPaid = str_contains($type, 'payment.paid');
$isFailed = str_contains($type, 'payment.failed');

if ($isPaid || $isFailed) {
    $pdo->beginTransaction();
    try {
        $status = $isPaid ? 'paid' : 'failed';
        $stmt = $pdo->prepare(
            "UPDATE payments SET status=?, paid_at=IF(?='paid', NOW(), paid_at), raw_response=? WHERE order_id=?"
        );
        $stmt->execute([$status, $status, $rawBody, $order['id']]);

        if ($isPaid) {
            notify((int)$order['user_id'], 'Payment received', "Payment for order {$order['order_number']} was received. It's now in the queue.");
        } else {
            notify((int)$order['user_id'], 'Payment failed', "Payment for order {$order['order_number']} did not go through. You can try again from My Orders.");
        }
        log_activity((int)$order['user_id'], 'webhook_' . $status, $order['order_number']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'processing failed']);
        exit;
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
