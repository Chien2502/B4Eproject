<?php
/**
 * API: User gửi tin nhắn chat
 * POST /chat/send.php
 * Body: { "message": "..." }
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
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

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

    // 2. Nhận dữ liệu
    $data = json_decode(file_get_contents('php://input'));
    $messageText = trim($data->message ?? '');

    if (empty($messageText)) {
        http_response_code(400);
        echo json_encode(['error' => 'Tin nhắn không được để trống.']);
        exit;
    }

    if (mb_strlen($messageText) > 2000) {
        http_response_code(400);
        echo json_encode(['error' => 'Tin nhắn không được vượt quá 2000 ký tự.']);
        exit;
    }

    $db = (new Database())->connect();

    // 3. Tìm hoặc tạo thread cho user (UPSERT)
    $stmt = $db->prepare("SELECT id FROM chat_threads WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($thread) {
        $thread_id = (int)$thread['id'];
    } else {
        $stmt = $db->prepare("INSERT INTO chat_threads (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        $thread_id = (int)$db->lastInsertId();
    }

    // 4. INSERT tin nhắn
    $stmt = $db->prepare(
        "INSERT INTO chat_messages (thread_id, sender_type, message) VALUES (?, 'user', ?)"
    );
    $stmt->execute([$thread_id, $messageText]);
    $message_id = (int)$db->lastInsertId();

    // 5. UPDATE thread metadata
    $preview = mb_substr($messageText, 0, 200);
    $stmt = $db->prepare(
        "UPDATE chat_threads 
         SET last_message = ?, last_message_at = NOW(), last_sender = 'user', 
             unread_by_admin = unread_by_admin + 1 
         WHERE id = ?"
    );
    $stmt->execute([$preview, $thread_id]);

    // 6. Lấy username để gửi FCM
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $username = $user['username'] ?? 'User';

    // 7. Gửi FCM đến topic admin_chat
    try {
        $factory = (new Factory())->withServiceAccount(__DIR__ . '/../firebase_credentials.json');
        $messaging = $factory->createMessaging();
        $fcmMessage = CloudMessage::withTarget('topic', 'admin_chat')
            ->withNotification([
                'title' => "💬 $username gửi tin nhắn",
                'body'  => mb_substr($messageText, 0, 100),
            ])
            ->withData([
                'action'    => 'new_chat_message',
                'thread_id' => (string)$thread_id,
                'user_id'   => (string)$user_id,
                'username'  => $username,
            ]);
        $messaging->send($fcmMessage);
    } catch (\Exception $fcmEx) {
        error_log("[Chat FCM] Error: " . $fcmEx->getMessage());
    }

    echo json_encode([
        'success'    => true,
        'message_id' => $message_id,
        'thread_id'  => $thread_id,
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>
