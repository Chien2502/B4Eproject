<?php
// file: api/admin/send_broadcast.php
// POST: Gửi thông báo hệ thống đến tất cả người dùng (FCM Topic 'all_users') và lưu vào DB
// Body JSON: { "title": "Bảo trì hệ thống", "message": "Thư viện bảo trì từ 22h...", "ref_id": null }
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';
require_once '../config/middleware.php';
require_once '../../vendor/autoload.php';
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

// 1. Xác thực quyền Admin/Superadmin
$admin_data = checkAdminAuth();

// 2. Lấy dữ liệu đầu vào
$data = json_decode(file_get_contents('php://input'));

if (empty($data->title) || empty($data->message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu thông tin bắt buộc: title hoặc message.']);
    exit;
}

$title   = trim($data->title);
$message = trim($data->message);
$ref_id  = isset($data->ref_id) ? (int)$data->ref_id : null;

try {
    $database = new Database();
    $db = $database->connect();
    
    $db->beginTransaction();
    
    // 3. Ghi nhận thông báo vào bảng system_announcements
    $stmt = $db->prepare(
        "INSERT INTO system_announcements (title, message, ref_id)
         VALUES (?, ?, ?)"
    );
    $stmt->execute([$title, $message, $ref_id]);
    $broadcast_id = (int)$db->lastInsertId();
    
    $db->commit();
    
    // 4. Gửi FCM Push Notification tới Topic 'all_users'
    $fcm_success = false;
    $fcm_error = null;
    
    try {
        $factory   = (new Factory())->withServiceAccount(__DIR__ . '/../firebase_credentials.json');
        $messaging = $factory->createMessaging();
        
        // Tạo payload đẩy tin cho cả background/terminated (notification) và foreground (data)
        $fcmMessage = CloudMessage::withTarget('topic', 'all_users')
            ->withNotification([
                'title' => $title,
                'body'  => $message,
            ])
            ->withData([
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'type'         => 'system_broadcast',
                'broadcast_id' => (string)$broadcast_id,
                'ref_id'       => (string)($ref_id ?? ''),
            ]);
            
        $messaging->send($fcmMessage);
        $fcm_success = true;
    } catch (Exception $fcmEx) {
        $fcm_error = $fcmEx->getMessage();
        error_log('[FCM Broadcast Error] ' . $fcm_error);
    }
    
    http_response_code(200);
    echo json_encode([
        'status'       => 'success',
        'message'      => 'Đã gửi thông báo hệ thống thành công.',
        'broadcast_id' => $broadcast_id,
        'fcm_success'  => $fcm_success,
        'fcm_error'    => $fcm_error
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}
?>
