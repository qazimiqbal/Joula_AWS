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

function get_authenticated_user($con) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;

    $token = substr($authHeader, 7);
    $stmt = mysqli_prepare(
        $con,
        "SELECT id, org_id, org_role, Permissions
         FROM Login_user_AWS
         WHERE auth_token = ? AND status = 'true' LIMIT 1"
    );

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
if ($me['permissionLevel'] < 2) {
    respond(403, array('success' => false, 'message' => 'Only admins and editors can review submissions'));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $requestedCreatedBy = isset($_GET['createdBy']) ? intval($_GET['createdBy']) : 0;
    $isSuperAdmin = $me['permissionLevel'] >= 4;
    $effectiveOwnerId = resolve_effective_owner_id($con, $me);

    $createdBy = $isSuperAdmin ? $requestedCreatedBy : $effectiveOwnerId;

    if ($createdBy > 0) {
        $stmt = mysqli_prepare(
            $con,
            "SELECT a.ID, a.Name, a.H_No, a.Apt_No, a.St_Name, a.City, a.State, a.Zip, a.Locality, a.Coordinates,
                a.Comments, a.Last_Visit, a.Verified, a.Masjid,
                    a.uploaded_by, COALESCE(u.username, '') AS submitted_by
             FROM Addresses_AWS a
             LEFT JOIN Login_user_AWS u ON u.id = a.uploaded_by
             WHERE COALESCE(a.`Clear`, 1) = 0 AND a.uploaded_by = ?
             ORDER BY a.City, a.St_Name, a.H_No"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $createdBy);
        }
    } else {
        $stmt = mysqli_prepare(
            $con,
            "SELECT a.ID, a.Name, a.H_No, a.Apt_No, a.St_Name, a.City, a.State, a.Zip, a.Locality, a.Coordinates,
                a.Comments, a.Last_Visit, a.Verified, a.Masjid,
                    a.uploaded_by, COALESCE(u.username, '') AS submitted_by
             FROM Addresses_AWS a
             LEFT JOIN Login_user_AWS u ON u.id = a.uploaded_by
             WHERE COALESCE(a.`Clear`, 1) = 0
             ORDER BY a.City, a.St_Name, a.H_No"
        );
    }

    if (!$stmt) {
        respond(500, array('success' => false, 'message' => 'Failed to prepare review list query'));
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $name, $hNo, $aptNo, $stName, $city, $state, $zip, $locality, $coordinates, $comments, $lastVisit, $verified, $masjid, $uploadedBy, $submittedBy);

    $rows = array();
    while (mysqli_stmt_fetch($stmt)) {
        $lat = null;
        $lng = null;
        $parts = explode(',', (string)$coordinates);
        if (count($parts) === 2) {
            $latRaw = trim($parts[0]);
            $lngRaw = trim($parts[1]);
            if ($latRaw !== '' && $lngRaw !== '' && is_numeric($latRaw) && is_numeric($lngRaw)) {
                $lat = floatval($latRaw);
                $lng = floatval($lngRaw);
            }
        }

        $rows[] = array(
            'id' => intval($id),
            'name' => $name,
            'houseNo' => $hNo,
            'aptNo' => $aptNo,
            'streetName' => $stName,
            'city' => $city,
            'state' => $state,
            'zip' => $zip,
            'locality' => $locality,
            'comments' => $comments,
            'lastVisit' => $lastVisit,
            'verified' => $verified,
            'masjid' => $masjid,
            'coordinates' => $coordinates,
            'latitude' => $lat,
            'longitude' => $lng,
            'uploadedBy' => isset($uploadedBy) ? intval($uploadedBy) : null,
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

    $action = isset($input['action']) ? trim((string)$input['action']) : 'approve';
    $id = isset($input['id']) ? intval($input['id']) : 0;

    $isSuperAdmin = $me['permissionLevel'] >= 4;
    $effectiveOwnerId = resolve_effective_owner_id($con, $me);

    if ($action === 'approve_all') {
        if ($isSuperAdmin) {
            $stmt = mysqli_prepare($con, "UPDATE Addresses_AWS SET `Clear` = 1 WHERE COALESCE(`Clear`, 1) = 0 AND (Coordinates IS NOT NULL AND Coordinates != '')");
        } else {
            $stmt = mysqli_prepare($con, "UPDATE Addresses_AWS SET `Clear` = 1 WHERE COALESCE(`Clear`, 1) = 0 AND uploaded_by = ? AND (Coordinates IS NOT NULL AND Coordinates != '')");
        }

        if (!$stmt) {
            respond(500, array('success' => false, 'message' => 'Failed to prepare approve all query'));
        }

        if (!$isSuperAdmin) {
            mysqli_stmt_bind_param($stmt, 'i', $effectiveOwnerId);
        }

        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        respond(200, array('success' => true, 'message' => 'Pending addresses approved', 'approvedCount' => max(0, intval($affected))));
    }

    if ($id <= 0) {
        respond(400, array('success' => false, 'message' => 'id is required'));
    }

    if (!$isSuperAdmin) {
        $ownerStmt = mysqli_prepare($con, 'SELECT uploaded_by FROM Addresses_AWS WHERE ID = ? LIMIT 1');
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

    if ($action === 'update') {
        $name = isset($input['name']) ? trim((string)$input['name']) : '';
        $houseNo = isset($input['houseNo']) ? trim((string)$input['houseNo']) : '';
        $aptNo = isset($input['aptNo']) ? trim((string)$input['aptNo']) : '';
        $streetName = isset($input['streetName']) ? trim((string)$input['streetName']) : '';
        $city = isset($input['city']) ? trim((string)$input['city']) : '';
        $state = isset($input['state']) ? trim((string)$input['state']) : '';
        $zip = isset($input['zip']) ? trim((string)$input['zip']) : '';
        $locality = isset($input['locality']) ? trim((string)$input['locality']) : '';
        $comments = isset($input['comments']) ? trim((string)$input['comments']) : '';
        $lastVisit = isset($input['lastVisit']) ? trim((string)$input['lastVisit']) : '';
        $masjid = isset($input['masjid']) ? trim((string)$input['masjid']) : '';
        $verifiedRaw = isset($input['verified']) ? strtoupper(trim((string)$input['verified'])) : 'N';
        $coordinatesRaw = isset($input['coordinates']) ? trim((string)$input['coordinates']) : '';

        if ($name === '') {
            respond(400, array('success' => false, 'message' => 'Please fill in name field'));
        }
        if ($houseNo === '') {
            respond(400, array('success' => false, 'message' => 'Please fill in houseNo field'));
        }
        if ($streetName === '') {
            respond(400, array('success' => false, 'message' => 'Please fill in streetName field'));
        }
        if ($city === '') {
            respond(400, array('success' => false, 'message' => 'Please fill in city field'));
        }
        if ($state === '') {
            respond(400, array('success' => false, 'message' => 'Please fill in state field'));
        }
        if ($zip === '') {
            respond(400, array('success' => false, 'message' => 'Please fill in zip field'));
        }

        $verified = ($verifiedRaw === 'Y') ? 'Y' : 'N';

        if ($lastVisit !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastVisit)) {
            respond(400, array('success' => false, 'message' => 'lastVisit must be YYYY-MM-DD'));
        }

        $coordinates = '';
        if ($coordinatesRaw !== '') {
            if (!preg_match('/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*$/', $coordinatesRaw)) {
                respond(400, array('success' => false, 'message' => 'coordinates must be in "lat,lng" format'));
            }
            $parts = explode(',', $coordinatesRaw, 2);
            $coordinates = trim($parts[0]) . ',' . trim($parts[1]);
        }

        $stmt = mysqli_prepare(
            $con,
            'UPDATE Addresses_AWS
             SET Name = ?, H_No = ?, Apt_No = ?, St_Name = ?, City = ?, State = ?, Zip = ?, Locality = ?,
                 Comments = ?, Last_Visit = ?, Masjid = ?, Verified = ?, Coordinates = ?
             WHERE ID = ?'
        );

        if (!$stmt) {
            respond(500, array('success' => false, 'message' => 'Failed to prepare update query'));
        }

        mysqli_stmt_bind_param(
            $stmt,
            'sssssssssssssi',
            $name,
            $houseNo,
            $aptNo,
            $streetName,
            $city,
            $state,
            $zip,
            $locality,
            $comments,
            $lastVisit,
            $masjid,
            $verified,
            $coordinates,
            $id
        );

        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected < 0) {
            respond(500, array('success' => false, 'message' => 'Failed to update address'));
        }

        respond(200, array('success' => true, 'message' => 'Address updated'));
    }

    $stmt = mysqli_prepare($con, 'UPDATE Addresses_AWS SET `Clear` = 1 WHERE ID = ?');
    if (!$stmt) {
        respond(500, array('success' => false, 'message' => 'Failed to prepare approval update query'));
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected <= 0) {
        respond(404, array('success' => false, 'message' => 'Address not found or already approved'));
    }

    respond(200, array('success' => true, 'message' => 'Address approved'));
}

respond(405, array('success' => false, 'message' => 'Method not allowed'));
?>
