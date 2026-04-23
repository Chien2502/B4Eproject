<?php
// file: api/notifications/mark_read.php
// POST: Đánh dấu thông báo đã đọc (1 hoặc tất cả)
// Body JSON: { "notification_id": 5 }   ← đánh dấu 1 thông báo
//         OR { "all": true }             ← đánh dấu tất cả
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    $data = json_decode(file_get_contents('php://input'));

    $db = (new Database())->connect();

    // 2. Đánh dấu tất cả hoặc 1 thông báo
    if (!empty($data->all) && $data->all === true) {
        // Đánh dấu TẤT CẢ thông báo của user là đã đọc
        $stmt = $db->prepare(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0"
        );
        $stmt->execute([$user_id]);
        $affected = $stmt->rowCount();

        http_response_code(200);
        echo json_encode([
            'status'  => 'success',
            'message' => "Đã đánh dấu {$affected} thông báo là đã đọc.",
        ]);

    } elseif (!empty($data->notification_id)) {
        // Đánh dấu 1 thông báo cụ thể (phải thuộc về đúng user)
        $stmt = $db->prepare(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([(int)$data->notification_id, $user_id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Không tìm thấy thông báo hoặc bạn không có quyền.']);
            exit;
        }

        http_response_code(200);
        echo json_encode([
            'status'  => 'success',
            'message' => 'Đã đánh dấu thông báo là đã đọc.',
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu tham số: notification_id hoặc all.']);
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ: ' . $e->getMessage()]);
}
?>
