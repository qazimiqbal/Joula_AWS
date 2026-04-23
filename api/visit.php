<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include('db.php');
mysqli_select_db($con, $db);

// ── GET: fetch existing Comments, Ethinicity, Potential for an address ────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        exit;
    }

    $stmt = mysqli_prepare($con, 'SELECT Comments, Ethinicity, Potential FROM Addresses_AWS WHERE ID = ?');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Query prepare failed']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $comments, $ethinicity, $potential);

    if (mysqli_stmt_fetch($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode([
            'success' => true,
            'data'    => [
                'comments'   => $comments  ?? '',
                'ethinicity' => $ethinicity ?? 'Others',
                'potential'  => $potential  ?? 'No',
            ],
        ]);
    } else {
        mysqli_stmt_close($stmt);
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }
    exit;
}

// ── POST: update visit data ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $id         = isset($input['id'])         ? intval($input['id'])         : 0;
    $today      = isset($input['today'])      ? trim($input['today'])        : date('Y-m-d');
    $actionTaken = isset($input['actionTaken']) ? trim($input['actionTaken']) : '';
    $comments   = isset($input['comments'])   ? trim($input['comments'])     : '';
    $ethinicity = isset($input['ethinicity']) ? trim($input['ethinicity'])   : 'Others';
    $potential  = isset($input['potential'])  ? trim($input['potential'])    : 'No';

    if ($id === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        exit;
    }

    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
        $today = date('Y-m-d');
    }

    // Determine Status / Verified changes based on action taken
    $newStatus   = null;
    $newVerified = null;
    switch ($actionTaken) {
        case 'met':
        case 'left_message':
            $newStatus = 'Muslim'; $newVerified = 'Y'; break;
        case 'No_Response':
            $newVerified = 'N'; break;
        case 'Ismailee':
            $newStatus = 'Ismailee'; $newVerified = 'Y'; break;
        case 'Owner_muslim_rented_non_muslim':
            $newStatus = 'Owner_Muslim'; $newVerified = 'Y'; break;
        case 'Non_muslim':
            $newStatus = 'Non_muslim'; $newVerified = 'Y'; break;
    }

    // Check if this is the first meet (R1 is '0000-00-00' or empty)
    $r1Stmt = mysqli_prepare($con, 'SELECT COALESCE(R1, "") FROM Addresses_AWS WHERE ID = ?');
    mysqli_stmt_bind_param($r1Stmt, 'i', $id);
    mysqli_stmt_execute($r1Stmt);
    mysqli_stmt_bind_result($r1Stmt, $firstmeet);
    mysqli_stmt_fetch($r1Stmt);
    mysqli_stmt_close($r1Stmt);
    $isFirstMeet = (trim($firstmeet) === '0000-00-00' || trim($firstmeet) === '');

    // Build parameterised UPDATE
    // Base columns always updated
    $setCols  = 'Last_Visit=?, R1_comments=?, Comments=?, Ethinicity=?, Potential=?';
    $types    = 'sssss';
    $params   = [$today, $actionTaken, $comments, $ethinicity, $potential];

    if ($isFirstMeet) {
        $setCols .= ', R1=?';
        $types   .= 's';
        $params[] = $today;
    }
    if ($newStatus !== null) {
        $setCols .= ', Status=?';
        $types   .= 's';
        $params[] = $newStatus;
    }
    if ($newVerified !== null) {
        $setCols .= ', Verified=?';
        $types   .= 's';
        $params[] = $newVerified;
    }

    $types   .= 'i';
    $params[] = $id;

    $stmt = mysqli_prepare($con, "UPDATE Addresses_AWS SET $setCols WHERE ID=?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Query prepare failed']);
        exit;
    }

    // Bind params dynamically
    $bindArgs = array_merge([$stmt, $types], $params);
    $refs     = [];
    foreach ($bindArgs as $k => $v) {
        $refs[$k] = &$bindArgs[$k];
    }
    call_user_func_array('mysqli_stmt_bind_param', $refs);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
    } else {
        mysqli_stmt_close($stmt);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update record']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>
