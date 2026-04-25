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

include('db.php');
include('stripe_config.php');
mysqli_select_db($con, $db);

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
function update_org_status($con, $customerId, $status, $subId = null) {
    if ($subId) {
        $stmt = mysqli_prepare($con,
            "UPDATE organizations
             SET plan_status = ?, stripe_subscription_id = ?
             WHERE stripe_customer_id = ?");
        mysqli_stmt_bind_param($stmt, 'sss', $status, $subId, $customerId);
    } else {
        $stmt = mysqli_prepare($con,
            "UPDATE organizations SET plan_status = ? WHERE stripe_customer_id = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $status, $customerId);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

switch ($eventType) {

    // Payment succeeded — subscription activated
    case 'checkout.session.completed':
        $customerId = $obj['customer'] ?? null;
        $subId      = $obj['subscription'] ?? null;
        if ($customerId) {
            update_org_status($con, $customerId, 'active', $subId);
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
            update_org_status($con, $customerId, $mappedStatus, $subId);
        }
        break;

    // Subscription cancelled (user cancelled from portal or admin)
    case 'customer.subscription.deleted':
        $customerId = $obj['customer'] ?? null;
        if ($customerId) {
            update_org_status($con, $customerId, 'cancelled');
        }
        break;

    // Payment failed (e.g., card expired)
    case 'invoice.payment_failed':
        $customerId = $obj['customer'] ?? null;
        if ($customerId) {
            update_org_status($con, $customerId, 'past_due');
        }
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
