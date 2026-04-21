<?php
// file: api/notifications/index.php
// GET: Lấy danh sách thông báo của user đang đăng nhập
// Header yêu cầu: Authorization: Bearer <token>
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 1. Xác thực token người dùng (dùng lại middleware có sẵn nhưng không yêu cầu admin)
require_once __DIR__ . '/../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

$headers     = apache_request_headers();
$authHeader  = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr         = explode(' ', $authHeader);
$token       = $arr[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Token not found']);
    exit;
}

try {
    $decoded = JWT::decode($token, new Key('B4E_SECRET_KEY_123456', 'HS256'));
    $user_id = (int)$decoded->data->id;

    $db = (new Database())->connect();

    // 2. Đếm số thông báo chưa đọc (cho badge 🔔)
    $stmt_count = $db->prepare(
        "SELECT COUNT(*) AS unread_count
         FROM notifications
         WHERE user_id = ? AND is_read = 0"
    );
    $stmt_count->execute([$user_id]);
    $unread_count = (int)$stmt_count->fetch()['unread_count'];

    // 3. Lấy danh sách thông báo (mới nhất lên đầu, tối đa 50)
    $stmt = $db->prepare(
        "SELECT id, title, message, type, ref_id, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 50"
    );
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll();

    // Ép kiểu để JSON sạch
    $notifications = array_map(function ($n) {
        return [
            'id'         => (int)$n['id'],
            'title'      => $n['title'],
            'message'    => $n['message'],
            'type'       => $n['type'],
            'ref_id'     => $n['ref_id'] !== null ? (int)$n['ref_id'] : null,
            'is_read'    => (bool)(int)$n['is_read'],
            'created_at' => $n['created_at'],
        ];
    }, $rows);

    http_response_code(200);
    echo json_encode([
        'status'       => 'success',
        'unread_count' => $unread_count,
        'data'         => $notifications,
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
}
?>
