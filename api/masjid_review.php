<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond($statusCode, $payload) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function permission_to_level($permissionRaw) {
    $value = trim((string)$permissionRaw);
    if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
    if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
    if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
    if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
    if (is_numeric($value)) return intval($value);
    return 0;
}

function has_table_column($con, $table, $column) {
    $tableSafe = mysqli_real_escape_string($con, $table);
    $columnSafe = mysqli_real_escape_string($con, $column);
    $result = mysqli_query($con, "SHOW COLUMNS FROM `{$tableSafe}` LIKE '{$columnSafe}'");
    if (!$result) return false;
    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    return $exists;
}

function get_authenticated_user($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;

    $token = substr($authHeader, 7);
    $stmt = mysqli_prepare($con,
        "SELECT id, org_id, org_role, Permissions
         FROM Login_user_AWS
         WHERE auth_token = ? AND status = 'true' LIMIT 1");

    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $userId = $orgId = null;
    $orgRole = $permissionsRaw = null;
    mysqli_stmt_bind_result($stmt, $userId, $orgId, $orgRole, $permissionsRaw);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$found || !$userId) return null;

    return [
        'id' => intval($userId),
                'orgId' => intval($orgId),
                'orgRole' => $orgRole,
        'permissionLevel' => permission_to_level($permissionsRaw),
    ];
}

function resolve_effective_owner_id($con, $me) {
        if ($me['permissionLevel'] >= 3 || empty($me['orgId'])) {
                return intval($me['id']);
        }

        $ownerStmt = mysqli_prepare(
                $con,
                "SELECT id
                 FROM Login_user_AWS
                 WHERE org_id = ?
                     AND status = 'true'
                     AND (org_role = 'org_admin' OR org_role = 'admin' OR Permissions = '3' OR Permissions = '4')
                 ORDER BY
                     CASE
                         WHEN org_role = 'org_admin' THEN 0
                         WHEN org_role = 'admin' THEN 1
                         ELSE 2
                     END,
                     id ASC
                 LIMIT 1"
        );

        if (!$ownerStmt) return intval($me['id']);
        mysqli_stmt_bind_param($ownerStmt, 'i', $me['orgId']);
        mysqli_stmt_execute($ownerStmt);
        $ownerId = null;
        mysqli_stmt_bind_result($ownerStmt, $ownerId);
        $found = mysqli_stmt_fetch($ownerStmt);
        mysqli_stmt_close($ownerStmt);

        return ($found && $ownerId) ? intval($ownerId) : intval($me['id']);
}

include('db.php');

$me = get_authenticated_user($con);
if (!$me) {
    respond(401, array('success' => false, 'message' => 'Unauthorized'));
}

$submittedByExpr = has_table_column($con, 'Masjids_AWS', 'Submitted_by')
    ? 'COALESCE(m.Submitted_by, m.Created_by)'
    : 'm.Created_by';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $requestedCreatedBy = isset($_GET['createdBy']) ? intval($_GET['createdBy']) : 0;
    $isSuperAdmin = $me['permissionLevel'] >= 4;
    $effectiveOwnerId = resolve_effective_owner_id($con, $me);

    // Non-super users review against effective owner (parent for child users).
    $createdBy = $isSuperAdmin ? $requestedCreatedBy : $effectiveOwnerId;

    if ($createdBy > 0) {
        $stmt = mysqli_prepare(
            $con,
            "SELECT m.ID, m.Name, m.H_No, m.Apt_No, m.St_Name, m.City, m.State, m.Zip, m.Coordinates,
                    m.Created_by, COALESCE(u.username, '') AS submitted_by
             FROM Masjids_AWS m
               LEFT JOIN Login_user_AWS u ON u.id = {$submittedByExpr}
             WHERE COALESCE(m.`Clear`, 1) = 0 AND m.Created_by = ?
             ORDER BY m.City, m.St_Name, m.H_No"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $createdBy);
        }
    } else {
        $stmt = mysqli_prepare(
            $con,
            "SELECT m.ID, m.Name, m.H_No, m.Apt_No, m.St_Name, m.City, m.State, m.Zip, m.Coordinates,
                    m.Created_by, COALESCE(u.username, '') AS submitted_by
             FROM Masjids_AWS m
               LEFT JOIN Login_user_AWS u ON u.id = {$submittedByExpr}
             WHERE COALESCE(m.`Clear`, 1) = 0
             ORDER BY m.City, m.St_Name, m.H_No"
        );
    }

    if (!$stmt) {
        respond(500, array('success' => false, 'message' => 'Failed to prepare review list query'));
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $name, $hNo, $aptNo, $stName, $city, $state, $zip, $coordinates, $createdById, $submittedBy);

    $rows = array();
    while (mysqli_stmt_fetch($stmt)) {
        $rows[] = array(
            'id' => intval($id),
            'name' => $name,
            'houseNo' => $hNo,
            'aptNo' => $aptNo,
            'streetName' => $stName,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'Coordinates' => $coordinates,
            'createdBy' => isset($createdById) ? intval($createdById) : null,
            'submittedBy' => $submittedBy,
        );
    }

    mysqli_stmt_close($stmt);
    respond(200, array('success' => true, 'data' => $rows, 'count' => count($rows)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $id = isset($input['id']) ? intval($input['id']) : 0;
    if ($id <= 0) {
        respond(400, array('success' => false, 'message' => 'id is required'));
    }

    $isSuperAdmin = $me['permissionLevel'] >= 4;
    $effectiveOwnerId = resolve_effective_owner_id($con, $me);

    if (!$isSuperAdmin) {
        // Non-super users can only approve their own pending masjids.
        $ownerStmt = mysqli_prepare($con, 'SELECT Created_by FROM Masjids_AWS WHERE ID = ? LIMIT 1');
        if (!$ownerStmt) {
            respond(500, array('success' => false, 'message' => 'Failed to verify ownership'));
        }
        mysqli_stmt_bind_param($ownerStmt, 'i', $id);
        mysqli_stmt_execute($ownerStmt);
        $ownerId = null;
        mysqli_stmt_bind_result($ownerStmt, $ownerId);
        mysqli_stmt_fetch($ownerStmt);
        mysqli_stmt_close($ownerStmt);

        if (intval($ownerId) !== $effectiveOwnerId) {
            respond(403, array('success' => false, 'message' => 'You can only approve submissions for your parent account'));
        }
    }

    $stmt = mysqli_prepare($con, 'UPDATE Masjids_AWS SET `Clear` = 1 WHERE ID = ?');
    if (!$stmt) {
        respond(500, array('success' => false, 'message' => 'Failed to prepare approval update query'));
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected <= 0) {
        respond(404, array('success' => false, 'message' => 'Masjid not found or already approved'));
    }

    respond(200, array('success' => true, 'message' => 'Masjid approved'));
}

respond(405, array('success' => false, 'message' => 'Method not allowed'));
?>