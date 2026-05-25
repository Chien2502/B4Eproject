<?php
/**
 * API: Admin trả lời tin nhắn chat
 * POST /chat/reply.php
 * Body: { "thread_id": 7, "message": "..." }
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';
require_once '../config/middleware.php';
require_once '../config/notification_helper.php';

try {
    $admin = checkAdminAuth();
    $admin_id = (int)$admin->id;

    $data = json_decode(file_get_contents('php://input'));
    $thread_id = (int)($data->thread_id ?? 0);
    $messageText = trim($data->message ?? '');

    if ($thread_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'thread_id là bắt buộc.']);
        exit;
    }

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

    // 1. Kiểm tra thread tồn tại
    $stmt = $db->prepare("SELECT user_id FROM chat_threads WHERE id = ?");
    $stmt->execute([$thread_id]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$thread) {
        http_response_code(404);
        echo json_encode(['error' => 'Thread không tồn tại.']);
        exit;
    }

    $target_user_id = (int)$thread['user_id'];

    // 2. INSERT tin nhắn (sender_type = system, admin_id = admin hiện tại)
    $stmt = $db->prepare(
        "INSERT INTO chat_messages (thread_id, sender_type, admin_id, message) 
         VALUES (?, 'system', ?, ?)"
    );
    $stmt->execute([$thread_id, $admin_id, $messageText]);
    $message_id = (int)$db->lastInsertId();

    // 3. UPDATE thread metadata
    $preview = mb_substr($messageText, 0, 200);
    $stmt = $db->prepare(
        "UPDATE chat_threads 
         SET last_message = ?, last_message_at = NOW(), last_sender = 'system',
             unread_by_user = unread_by_user + 1, unread_by_admin = 0
         WHERE id = ?"
    );
    $stmt->execute([$preview, $thread_id]);

    // 4. Gửi FCM push đến user
    sendFcmToUser($db, $target_user_id,
        '💬 Hệ thống đã trả lời',
        mb_substr($messageText, 0, 100),
        ['type' => 'chat_reply', 'ref_id' => (string)$thread_id]
    );

    echo json_encode([
        'success'    => true,
        'message_id' => $message_id,
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>
