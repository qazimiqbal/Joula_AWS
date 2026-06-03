<?php
// ---------------------------------------------------------------
// Stripe Webhook Handler
// POST /api/stripe_webhook.php
//
// Register this URL in your Stripe Dashboard under
// Developers → Webhooks.  Events to listen for:
//   checkout.session.completed
//   customer.subscription.updated
//   customer.subscription.deleted
//   invoice.payment_failed
// ---------------------------------------------------------------

require_once 'db.pgsql.php';
include('stripe_config.php');

$payload   = file_get_contents('php://input');
$sigHeader = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

// ------------------------------------------------------------------
// Verify webhook signature (prevents spoofed requests)
// ------------------------------------------------------------------
function verify_stripe_signature($payload, $sigHeader, $secret) {
    // Parse t= and v1= from the signature header
    $parts = [];
    foreach (explode(',', $sigHeader) as $part) {
        [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
        $parts[$k] = $v;
    }
    if (empty($parts['t']) || empty($parts['v1'])) return false;

    $timestamp    = $parts['t'];
    $expectedSig  = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    return hash_equals($expectedSig, $parts['v1']);
}

if (!verify_stripe_signature($payload, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($payload, true);
if (!isset($event['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event type']);
    exit;
}

$eventType = $event['type'];
$obj       = $event['data']['object'] ?? [];

// ------------------------------------------------------------------
// Helper: update org plan_status by stripe_customer_id
// ------------------------------------------------------------------
function update_org_status($pdo, $customerId, $status, $subId = null) {
    if ($subId) {
        $stmt = $pdo->prepare('UPDATE "organizations" SET "plan_status" = :status, "stripe_subscription_id" = :subId WHERE "stripe_customer_id" = :customerId');
        $stmt->execute([':status' => $status, ':subId' => $subId, ':customerId' => $customerId]);
    } else {
        $stmt = $pdo->prepare('UPDATE "organizations" SET "plan_status" = :status WHERE "stripe_customer_id" = :customerId');
        $stmt->execute([':status' => $status, ':customerId' => $customerId]);
    }
}

switch ($eventType) {

    // Payment succeeded — subscription activated
    case 'checkout.session.completed':
        $customerId = $obj['customer'] ?? null;
        $subId      = $obj['subscription'] ?? null;
        if ($customerId) {
            update_org_status($pdo, $customerId, 'active', $subId);
        }
        break;

    // Subscription renewed, reactivated, or modified
    case 'customer.subscription.updated':
        $customerId = $obj['customer'] ?? null;
        $subId      = $obj['id'] ?? null;
        $stripeStatus = $obj['status'] ?? '';
        $mappedStatus = 'active';
        if ($stripeStatus === 'past_due')   $mappedStatus = 'past_due';
        if ($stripeStatus === 'canceled')   $mappedStatus = 'cancelled';
        if ($stripeStatus === 'unpaid')     $mappedStatus = 'past_due';
        if ($customerId) {
            update_org_status($pdo, $customerId, $mappedStatus, $subId);
        }
        break;

    // Subscription cancelled (user cancelled from portal or admin)
    case 'customer.subscription.deleted':
        $customerId = $obj['customer'] ?? null;
        if ($customerId) {
            update_org_status($pdo, $customerId, 'cancelled');
        }
        break;

    // Payment failed (e.g., card expired)
    case 'invoice.payment_failed':
        $customerId = $obj['customer'] ?? null;
        if ($customerId) {
            update_org_status($pdo, $customerId, 'past_due');
        }
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
