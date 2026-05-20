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
    $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET_KEY') ?: 'B4E_SECRET_KEY_123456', 'HS256'));
    $user_id = (int)$decoded->data->id;

    $db = (new Database())->connect();

    // 2. Đếm số thông báo cá nhân chưa đọc (cho badge 🔔)
    $stmt_count = $db->prepare(
        "SELECT COUNT(*) AS unread_count
         FROM notifications
         WHERE user_id = ? AND is_read = 0"
    );
    $stmt_count->execute([$user_id]);
    $unread_count = (int)$stmt_count->fetch()['unread_count'];

    // 3. Lấy danh sách thông báo cá nhân (mới nhất lên đầu, tối đa 50)
    $stmt_personal = $db->prepare(
        "SELECT id, title, message, type, ref_id, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 50"
    );
    $stmt_personal->execute([$user_id]);
    $personal_rows = $stmt_personal->fetchAll();

    // 4. Lấy danh sách thông báo hệ thống chung (tối đa 30)
    $stmt_system = $db->query(
        "SELECT id, title, message, ref_id, created_at
         FROM system_announcements
         ORDER BY created_at DESC
         LIMIT 30"
    );
    $system_rows = $stmt_system->fetchAll();

    // 5. Gộp và ánh xạ dữ liệu thống nhất
    $merged_list = [];

    foreach ($personal_rows as $p) {
        $merged_list[] = [
            'id'          => (int)$p['id'],
            'title'       => $p['title'],
            'message'     => $p['message'],
            'type'        => $p['type'],
            'ref_id'      => $p['ref_id'] !== null ? (int)$p['ref_id'] : null,
            'is_read'     => (bool)(int)$p['is_read'],
            'is_system'   => false,
            'created_at'  => $p['created_at'],
        ];
    }

    foreach ($system_rows as $s) {
        $merged_list[] = [
            'id'          => (int)$s['id'],
            'title'       => $s['title'],
            'message'     => $s['message'],
            'type'        => 'system_broadcast',
            'ref_id'      => $s['ref_id'] !== null ? (int)$s['ref_id'] : null,
            'is_read'     => false, // Sẽ được kiểm tra và xử lý trạng thái đọc tại local SQLite của Flutter
            'is_system'   => true,
            'created_at'  => $s['created_at'],
        ];
    }

    // Sắp xếp theo ngày tạo mới nhất lên đầu (descending order)
    usort($merged_list, function ($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });

    // Cắt bớt danh sách chỉ lấy 50 thông báo mới nhất tổng cộng
    $merged_list = array_slice($merged_list, 0, 50);

    http_response_code(200);
    echo json_encode([
        'status'       => 'success',
        'unread_count' => $unread_count,
        'data'         => $merged_list,
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
}
?>
