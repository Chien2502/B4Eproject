<?php
// file: api/config/notification_helper.php
// Hàm tiện ích tái sử dụng để tạo thông báo từ bất kỳ API nào.
//
// Cách dùng trong bất kỳ API PHP nào:
//   require_once '../config/notification_helper.php';
//   createNotification($db, $user_id, 'borrow_approved', 'Tiêu đề', 'Nội dung', $ref_id);

/**
 * Tạo một thông báo mới cho người dùng.
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
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, type, title, message, ref_id)
             VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$user_id, $type, $title, $message, $ref_id]);
    } catch (Exception $e) {
        // Không để lỗi thông báo làm hỏng luồng chính của API
        error_log('[Notification Error] ' . $e->getMessage());
        return false;
    }
}
?>
