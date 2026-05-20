<?php
// file: api/config/notification_helper.php
// Hàm tiện ích tái sử dụng để tạo thông báo và gửi FCM push notification.
//
// Cách dùng trong bất kỳ API PHP nào:
//   require_once '../config/notification_helper.php';
//   createNotification($db, $user_id, 'borrow_approved', 'Tiêu đề', 'Nội dung', $ref_id);

require_once __DIR__ . '/../../vendor/autoload.php';
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

/**
 * Tạo một thông báo mới cho người dùng VÀ gửi FCM push notification đến thiết bị của họ.
 *
 * @param PDO    $db      Kết nối PDO đang hoạt động
 * @param int    $user_id ID người nhận
 * @param string $type    Loại: borrow_approved|borrow_rejected|return_reminder|
 *                               return_overdue|donation_approved|donation_rejected|system
 * @param string $title   Tiêu đề hiển thị
 * @param string $message Nội dung chi tiết
 * @param int|null $ref_id ID tham chiếu (borrowing_id, donation_id, ...)
 */
function createNotification(
    PDO    $db,
    int    $user_id,
    string $type,
    string $title,
    string $message,
    ?int   $ref_id = null
): bool {
    try {
        // 1. Ghi thông báo vào DB (in-app notification list)
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, type, title, message, ref_id)
             VALUES (?, ?, ?, ?, ?)"
        );
        $result = $stmt->execute([$user_id, $type, $title, $message, $ref_id]);

        // 2. Gửi FCM push notification đến thiết bị (kể cả khi app bị tắt)
        sendFcmToUser($db, $user_id, $title, $message, [
            'type'   => $type,
            'ref_id' => (string)($ref_id ?? ''),
        ]);

        return $result;
    } catch (Exception $e) {
        // Không để lỗi thông báo làm hỏng luồng chính của API
        error_log('[Notification Error] ' . $e->getMessage());
        return false;
    }
}

/**
 * Gửi FCM push notification đến thiết bị cụ thể của user.
 * Hoạt động ở cả 3 trạng thái: foreground, background, và terminated (app bị tắt).
 *
 * @param PDO    $db      Kết nối PDO đang hoạt động
 * @param int    $user_id ID người nhận
 * @param string $title   Tiêu đề notification
 * @param string $body    Nội dung notification
 * @param array  $data    Dữ liệu bổ sung (type, ref_id, ...) để Flutter điều hướng khi tap
 */
function sendFcmToUser(PDO $db, int $user_id, string $title, string $body, array $data = []): void {
    try {
        // 1. Lấy FCM token của user từ DB
        $stmt = $db->prepare("SELECT fcm_token FROM users WHERE id = ? AND fcm_token IS NOT NULL AND fcm_token != ''");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if (!$row || empty($row['fcm_token'])) {
            // User chưa đăng nhập trên thiết bị hoặc chưa cấp quyền FCM — bỏ qua
            error_log("[FCM] No token found for user_id=$user_id");
            return;
        }

        // 2. Gửi FCM đến device token cụ thể
        $factory   = (new Factory())->withServiceAccount(__DIR__ . '/../firebase_credentials.json');
        $messaging = $factory->createMessaging();

        // Payload gồm 2 phần:
        // - notification: OS dùng để hiển thị notification popup (background & terminated)
        // - data: Flutter dùng để điều hướng khi user tap notification
        $fcmMessage = CloudMessage::withTarget('token', $row['fcm_token'])
            ->withNotification([
                'title' => $title,
                'body'  => $body,
            ])
            ->withData(array_merge($data, [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]));

        $messaging->send($fcmMessage);
        error_log("[FCM] Sent to user_id=$user_id, type=" . ($data['type'] ?? 'unknown'));

    } catch (Exception $e) {
        // Không để lỗi FCM ảnh hưởng đến luồng nghiệp vụ chính
        error_log('[FCM Error] ' . $e->getMessage());
    }
}
?>
