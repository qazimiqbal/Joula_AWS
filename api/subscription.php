<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

include('db.php');
include('stripe_config.php');
mysqli_select_db($con, $db);

// ---------------------------------------------------------------
// Require authenticated user via token header
// ---------------------------------------------------------------
function get_authenticated_user_id($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') === 0) {
        $token = substr($authHeader, 7);
    } else {
        return null;
    }
    $stmt = mysqli_prepare($con, "SELECT id FROM Login_user_AWS WHERE auth_token = ? AND status = 'true' LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $userId = null;
    mysqli_stmt_bind_result($stmt, $userId);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $userId ? intval($userId) : null;
}

// ---------------------------------------------------------------
// Helper: Stripe API call via cURL (no SDK required)
// ---------------------------------------------------------------
function stripe_request($method, $path, $params = []) {
    $url = 'https://api.stripe.com/v1' . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($response, true);
    return ['code' => $httpCode, 'body' => $data];
}

// ---------------------------------------------------------------
// GET /api/subscription.php  — return subscription status for the
//                               current user's organization
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = get_authenticated_user_id($con);
    if (!$userId) {
        respond(401, ['success' => false, 'message' => 'Unauthorized']);
    }

    $stmt = mysqli_prepare($con,
        "SELECT u.org_id, u.org_role,
                o.id, o.name, o.plan_status, o.trial_ends_at,
                o.stripe_customer_id, o.stripe_subscription_id,
                o.max_editors, o.max_viewers, o.monthly_price_cents
         FROM Login_user_AWS u
         LEFT JOIN organizations o ON o.id = u.org_id
         WHERE u.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $row = null;
    mysqli_stmt_bind_result($stmt,
        $orgId, $orgRole,
        $oId, $oName, $planStatus, $trialEndsAt,
        $stripeCustomerId, $stripeSubscriptionId,
        $maxEditors, $maxViewers, $monthlyCents);
    if (mysqli_stmt_fetch($stmt)) {
        $row = compact(
            'orgId','orgRole','oId','oName','planStatus','trialEndsAt',
            'stripeCustomerId','stripeSubscriptionId',
            'maxEditors','maxViewers','monthlyCents'
        );
    }
    mysqli_stmt_close($stmt);

    if (!$row || !$row['orgId']) {
        respond(404, ['success' => false, 'message' => 'No organization found for this user']);
    }

    // Auto-expire trial if date has passed and still marked as trial
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $trialEnd = new DateTime($row['trialEndsAt'], new DateTimeZone('UTC'));
    if ($row['planStatus'] === 'trial' && $now > $trialEnd) {
        $expireStmt = mysqli_prepare($con,
            "UPDATE organizations SET plan_status = 'expired' WHERE id = ?");
        mysqli_stmt_bind_param($expireStmt, 'i', $row['orgId']);
        mysqli_stmt_execute($expireStmt);
        mysqli_stmt_close($expireStmt);
        $row['planStatus'] = 'expired';
    }

    $trialDaysLeft = max(0, (int)ceil(($trialEnd->getTimestamp() - $now->getTimestamp()) / 86400));

    respond(200, [
        'success' => true,
        'data' => [
            'orgId'                  => intval($row['orgId']),
            'orgName'                => $row['oName'],
            'orgRole'                => $row['orgRole'],
            'planStatus'             => $row['planStatus'],
            'trialEndsAt'            => $row['trialEndsAt'],
            'trialDaysLeft'          => $trialDaysLeft,
            'hasPaymentMethod'       => !empty($row['stripeSubscriptionId']),
            'maxEditors'             => intval($row['maxEditors']),
            'maxViewers'             => intval($row['maxViewers']),
            'monthlyPriceCents'      => intval($row['monthlyCents']),
            'stripePublishableKey'   => STRIPE_PUBLISHABLE_KEY,
        ]
    ]);
}

// ---------------------------------------------------------------
// POST /api/subscription.php
// action=create_checkout  → create Stripe Checkout Session
// action=billing_portal   → create Stripe Billing Portal session
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = get_authenticated_user_id($con);
    if (!$userId) {
        respond(401, ['success' => false, 'message' => 'Unauthorized']);
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) $input = $_POST;
    $action = isset($input['action']) ? trim($input['action']) : '';

    // Load org data for this user
    $stmt = mysqli_prepare($con,
        "SELECT u.org_id, u.org_role, u.email,
                o.stripe_customer_id, o.stripe_subscription_id,
                o.plan_status, o.stripe_price_id
         FROM Login_user_AWS u
         LEFT JOIN organizations o ON o.id = u.org_id
         WHERE u.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $orgId = $orgRole = $userEmail = $customerId = $subId = $planStatus = $orgPriceId = null;
    mysqli_stmt_bind_result($stmt, $orgId, $orgRole, $userEmail, $customerId, $subId, $planStatus, $orgPriceId);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$orgId) {
        respond(404, ['success' => false, 'message' => 'No organization found for this user']);
    }
    if ($orgRole !== 'org_admin') {
        respond(403, ['success' => false, 'message' => 'Only the organization admin can manage billing']);
    }

    // ----------------------------------------------------------
    // ACTION: create_checkout
    // ----------------------------------------------------------
    if ($action === 'create_checkout') {
        $priceId = $orgPriceId ?: STRIPE_PRICE_ID;

        // Create or reuse Stripe customer
        if (empty($customerId)) {
            $res = stripe_request('POST', '/customers', [
                'email'    => $userEmail,
                'metadata' => ['org_id' => $orgId],
            ]);
            if ($res['code'] !== 200 || empty($res['body']['id'])) {
                respond(500, ['success' => false, 'message' => 'Failed to create Stripe customer']);
            }
            $customerId = $res['body']['id'];
            $updStmt = mysqli_prepare($con,
                "UPDATE organizations SET stripe_customer_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($updStmt, 'si', $customerId, $orgId);
            mysqli_stmt_execute($updStmt);
            mysqli_stmt_close($updStmt);
        }

        // Create Checkout Session (subscription mode)
        $res = stripe_request('POST', '/checkout/sessions', [
            'customer'            => $customerId,
            'mode'                => 'subscription',
            'line_items[0][price]'    => $priceId,
            'line_items[0][quantity]' => 1,
            'success_url'         => STRIPE_SUCCESS_URL,
            'cancel_url'          => STRIPE_CANCEL_URL,
            'metadata[org_id]'    => $orgId,
        ]);

        if ($res['code'] !== 200 || empty($res['body']['url'])) {
            respond(500, ['success' => false, 'message' => 'Failed to create Checkout session']);
        }
        respond(200, ['success' => true, 'checkoutUrl' => $res['body']['url']]);
    }

    // ----------------------------------------------------------
    // ACTION: billing_portal  (manage existing subscription)
    // ----------------------------------------------------------
    if ($action === 'billing_portal') {
        if (empty($customerId)) {
            respond(400, ['success' => false, 'message' => 'No Stripe customer on file. Please subscribe first.']);
        }
        $res = stripe_request('POST', '/billing_portal/sessions', [
            'customer'   => $customerId,
            'return_url' => STRIPE_CANCEL_URL, // returns user back to billing page
        ]);
        if ($res['code'] !== 200 || empty($res['body']['url'])) {
            respond(500, ['success' => false, 'message' => 'Failed to create billing portal session']);
        }
        respond(200, ['success' => true, 'portalUrl' => $res['body']['url']]);
    }

    respond(400, ['success' => false, 'message' => 'Unknown action']);
}

respond(405, ['success' => false, 'message' => 'Method not allowed']);
