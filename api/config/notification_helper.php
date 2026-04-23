<?php
// file: api/config/notification_helper.php
// Hàm tiện ích để tạo thông báo từ bất kỳ API nào.
// Cách dùng:
//   require_once '../config/notification_helper.php';
//   createNotification($db, $user_id, 'borrow_approved', 'Tiêu đề', 'Nội dung', $borrow_id);

/**
 * Tạo một thông báo mới cho người dùng.
 *
 * @param PDO    $db      Kết nối PDO đang hoạt động
 * @param int    $user_id ID người nhận thông báo
 * @param string $type    Loại thông báo (borrow_approved, donation_rejected, v.v.)
 * @param string $title   Tiêu đề hiển thị
 * @param string $message Nội dung chi tiết
 * @param int|null $ref_id ID tham chiếu (VD: borrowing_id, donation_id)
 * @return bool  true nếu tạo thành công, false nếu thất bại
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
        // Không để lỗi thông báo làm hỏng luồng chính
        error_log('[Notification Error] ' . $e->getMessage());
        return false;
    }
}


// ================================================================
// DANH SÁCH CÁC TYPE VÀ TEMPLATE THÔNG BÁO CHUẨN
// Dán trực tiếp vào API khi gọi createNotification()
// ================================================================

//
// -- MƯỢN SÁCH --
// createNotification($db, $user_id, 'borrow_approved',
//     'Yêu cầu mượn sách được duyệt ✅',
//     'Yêu cầu mượn cuốn "' . $book_title . '" của bạn đã được Admin duyệt. Vui lòng đến thư viện để nhận sách.',
//     $borrow_id);
//
// createNotification($db, $user_id, 'borrow_rejected',
//     'Yêu cầu mượn sách bị từ chối ❌',
//     'Yêu cầu mượn cuốn "' . $book_title . '" của bạn đã bị từ chối.',
//     $borrow_id);
//
// createNotification($db, $user_id, 'return_reminder',
//     'Nhắc nhở trả sách ⏰',
//     'Cuốn "' . $book_title . '" sẽ đến hạn trả vào ngày ' . $due_date . '. Vui lòng trả đúng hạn.',
//     $borrow_id);
//
// createNotification($db, $user_id, 'return_overdue',
//     'Sách quá hạn trả 🚨',
//     'Cuốn "' . $book_title . '" đã quá hạn trả từ ngày ' . $due_date . '. Vui lòng liên hệ thư viện.',
//     $borrow_id);
//
// -- QUYÊN GÓP --
// createNotification($db, $user_id, 'donation_approved',
//     'Quyên góp được chấp nhận ❤️',
//     'Cảm ơn bạn đã quyên góp cuốn "' . $book_title . '". Thư viện đã tiếp nhận thành công!',
//     $donation_id);
//
// createNotification($db, $user_id, 'donation_rejected',
//     'Quyên góp bị từ chối',
//     'Yêu cầu quyên góp cuốn "' . $book_title . '" không được chấp nhận.',
//     $donation_id);
//
// -- HỆ THỐNG --
// createNotification($db, $user_id, 'system',
//     'Thông báo từ Thư viện B4E 📢',
//     'Thư viện sẽ đóng cửa vào ngày lễ 30/4. Vui lòng trả sách trước ngày đó.',
//     null);
?>
