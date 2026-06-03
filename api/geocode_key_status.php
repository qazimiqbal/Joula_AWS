<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once 'db.pgsql.php';

function permission_to_level($permissionRaw)
{
    $value = trim((string)$permissionRaw);
    if ($value === '4' || strcasecmp($value, 'Super Administrator') === 0) return 4;
    if ($value === '3' || strcasecmp($value, 'Administrator') === 0 || strcasecmp($value, 'Admin') === 0) return 3;
    if ($value === '2' || strcasecmp($value, 'Editor') === 0) return 2;
    if ($value === '1' || strcasecmp($value, 'Viewer') === 0) return 1;
    if (is_numeric($value)) return intval($value);
    return 0;
}

function get_auth_user($pdo)
{
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (strpos($authHeader, 'Bearer ') !== 0) return null;
    $token = substr($authHeader, 7);
    $sql = 'SELECT id, permissions FROM "Login_user_AWS" WHERE auth_token = :token AND status = :status LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token, ':status' => 'true']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    return [
        'id' => intval($row['id']),
        'permissionLevel' => permission_to_level($row['permissions']),
    ];
}

function get_key_from_sources()
{
    $sources = [
        'GOOGLE_GEOCODING_API_KEY (getenv)' => getenv('GOOGLE_GEOCODING_API_KEY') ?: '',
        'GOOGLE_MAPS_API_KEY (getenv)' => getenv('GOOGLE_MAPS_API_KEY') ?: '',
        'VITE_GOOGLE_MAPS_API_KEY (getenv)' => getenv('VITE_GOOGLE_MAPS_API_KEY') ?: '',
        'GOOGLE_GEOCODING_API_KEY ($_ENV)' => isset($_ENV['GOOGLE_GEOCODING_API_KEY']) ? (string)$_ENV['GOOGLE_GEOCODING_API_KEY'] : '',
        'GOOGLE_MAPS_API_KEY ($_ENV)' => isset($_ENV['GOOGLE_MAPS_API_KEY']) ? (string)$_ENV['GOOGLE_MAPS_API_KEY'] : '',
        'VITE_GOOGLE_MAPS_API_KEY ($_ENV)' => isset($_ENV['VITE_GOOGLE_MAPS_API_KEY']) ? (string)$_ENV['VITE_GOOGLE_MAPS_API_KEY'] : '',
        'GOOGLE_GEOCODING_API_KEY ($_SERVER)' => isset($_SERVER['GOOGLE_GEOCODING_API_KEY']) ? (string)$_SERVER['GOOGLE_GEOCODING_API_KEY'] : '',
        'GOOGLE_MAPS_API_KEY ($_SERVER)' => isset($_SERVER['GOOGLE_MAPS_API_KEY']) ? (string)$_SERVER['GOOGLE_MAPS_API_KEY'] : '',
        'VITE_GOOGLE_MAPS_API_KEY ($_SERVER)' => isset($_SERVER['VITE_GOOGLE_MAPS_API_KEY']) ? (string)$_SERVER['VITE_GOOGLE_MAPS_API_KEY'] : '',
    ];

    $foundAt = null;
    foreach ($sources as $label => $value) {
        if (trim((string)$value) !== '') {
            $foundAt = $label;
            break;
        }
    }

    $status = [];
    foreach ($sources as $label => $value) {
        $status[] = [
            'source' => $label,
            'present' => trim((string)$value) !== '',
        ];
    }

    return [
        'keyFound' => $foundAt !== null,
        'detectedSource' => $foundAt,
        'sources' => $status,
    ];
}

$user = get_auth_user($pdo);
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($user['permissionLevel'] < 4) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only super admin can view key diagnostics']);
    exit;
}

$diag = get_key_from_sources();

echo json_encode([
    'success' => true,
    'keyFound' => $diag['keyFound'],
    'detectedSource' => $diag['detectedSource'],
    'sources' => $diag['sources'],
]);
