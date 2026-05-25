<?php
/**
 * API: Đánh dấu đã đọc
 * POST /chat/mark_read.php
 * Body: { "thread_id": 7 }
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

try {
    // 1. Xác thực JWT
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = explode(' ', $authHeader)[1] ?? '';
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $key = getenv('JWT_SECRET_KEY') ?: 'B4E_SECRET_KEY_123456';
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = (int)$decoded->data->id;
    $role = $decoded->data->role ?? 'user';
    $is_admin = in_array($role, ['admin', 'super-admin']);

    $data = json_decode(file_get_contents('php://input'));
    $thread_id = (int)($data->thread_id ?? 0);

    $db = (new Database())->connect();

    if ($is_admin) {
        // Admin đọc → reset unread_by_admin, mark messages từ user = read
        if ($thread_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'thread_id là bắt buộc.']);
            exit;
        }

        $db->prepare(
            "UPDATE chat_messages SET is_read = 1 
             WHERE thread_id = ? AND sender_type = 'user' AND is_read = 0"
        )->execute([$thread_id]);

        $db->prepare(
            "UPDATE chat_threads SET unread_by_admin = 0 WHERE id = ?"
        )->execute([$thread_id]);

    } else {
        // User đọc → tìm thread của mình, reset unread_by_user
        $stmt = $db->prepare("SELECT id FROM chat_threads WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($thread) {
            $tid = (int)$thread['id'];

            $db->prepare(
                "UPDATE chat_messages SET is_read = 1 
                 WHERE thread_id = ? AND sender_type = 'system' AND is_read = 0"
            )->execute([$tid]);

            $db->prepare(
                "UPDATE chat_threads SET unread_by_user = 0 WHERE id = ?"
            )->execute([$tid]);
        }
    }

    echo json_encode(['success' => true]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>
