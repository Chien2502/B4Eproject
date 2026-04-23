<?php
// file: api/notifications/index.php
// GET: Lấy danh sách thông báo của người dùng hiện tại
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không được hỗ trợ.']);
    exit;
}

// 1. Xác thực JWT Token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr   = explode(' ', $authHeader);
$token = $arr[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Vui lòng đăng nhập.']);
    exit;
}

try {
    $key     = 'B4E_SECRET_KEY_123456';
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;

    $db = (new Database())->connect();

    // 2. Đếm số thông báo chưa đọc (trả về để Flutter hiển thị badge)
    $stmt_count = $db->prepare(
        "SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0"
    );
    $stmt_count->execute([$user_id]);
    $unread_count = (int)$stmt_count->fetch()['unread_count'];

    // 3. Lấy danh sách thông báo (mới nhất lên đầu, giới hạn 50 bản ghi)
    $stmt = $db->prepare(
        "SELECT id, title, message, type, ref_id, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 50"
    );
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();

    // Ép kiểu đúng để JSON trả về gọn
    foreach ($notifications as &$n) {
        $n['id']       = (int)$n['id'];
        $n['ref_id']   = $n['ref_id'] ? (int)$n['ref_id'] : null;
        $n['is_read']  = (bool)(int)$n['is_read'];
    }

    http_response_code(200);
    echo json_encode([
        'status'       => 'success',
        'unread_count' => $unread_count,
        'data'         => $notifications,
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ: ' . $e->getMessage()]);
}
?>
