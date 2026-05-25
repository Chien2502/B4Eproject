<?php
/**
 * API: Lấy tin nhắn chat
 * GET /chat/messages.php
 * Params: ?thread_id=7 (admin bắt buộc, user tự lấy)
 *         &after=100   (optional, polling - chỉ lấy id > after)
 *         &limit=50    (optional)
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

    $db = (new Database())->connect();

    // 2. Xác định thread_id
    if ($is_admin) {
        $thread_id = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
        if ($thread_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'thread_id là bắt buộc cho admin.']);
            exit;
        }
    } else {
        // User: tìm thread của mình
        $stmt = $db->prepare("SELECT id FROM chat_threads WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);
        $thread_id = $thread ? (int)$thread['id'] : 0;

        if ($thread_id === 0) {
            echo json_encode(['data' => [], 'thread_id' => null, 'has_more' => false]);
            exit;
        }
    }

    // 3. Query tin nhắn
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $after = isset($_GET['after']) ? (int)$_GET['after'] : 0;

    $where = "WHERE cm.thread_id = ?";
    $params = [$thread_id];

    if ($after > 0) {
        $where .= " AND cm.id > ?";
        $params[] = $after;
    }

    // Admin view: hiện thêm admin_name
    $adminJoin = $is_admin
        ? "LEFT JOIN users u ON cm.admin_id = u.id"
        : "";
    $adminSelect = $is_admin
        ? ", u.username as admin_name"
        : "";

    $sql = "SELECT cm.id, cm.sender_type, cm.message, cm.is_read, cm.created_at $adminSelect
            FROM chat_messages cm 
            $adminJoin
            $where
            ORDER BY cm.created_at ASC
            LIMIT $limit";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Check has_more
    $has_more = count($messages) >= $limit;

    echo json_encode([
        'data'      => $messages,
        'thread_id' => $thread_id,
        'has_more'  => $has_more,
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>
