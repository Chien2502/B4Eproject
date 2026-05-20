<?php
// file: api/borrowings/update_status.php
// POST: Admin cập nhật trạng thái mượn sách + tự động tạo thông báo
// Body JSON: { "borrowing_id": 5, "status": "approved" }
//
// Trạng thái hợp lệ:
//   pickup:   pending_approval → approved → borrowed → return_requested → returned
//   delivery: pending_approval → approved → preparing → shipped → borrowed → return_requested → return_shipping → returned
//   Khác: overdue, cancelled

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../config/notification_helper.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 1. Chỉ admin
checkAdminAuth();

// 2. Validate input
$raw = file_get_contents('php://input');
// Fallback to $_POST if json_decode fails (e.g. form-data)
$data = json_decode($raw, true);
$borrowing_id = $data['borrowing_id'] ?? $_POST['borrowing_id'] ?? null;
$status = $data['status'] ?? $_POST['status'] ?? null;

if (empty($borrowing_id) || empty($status)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu borrowing_id hoặc status. Raw: ' . $raw]);
    exit;
}

$allowed_statuses = [
    'pending_approval', 'approved', 'preparing', 'shipped',
    'borrowed', 'return_requested', 'return_shipping',
    'returned', 'overdue', 'cancelled'
];

if (!in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Status không hợp lệ. Các giá trị được phép: ' . implode(', ', $allowed_statuses)]);
    exit;
}

try {
    $db = (new Database())->connect();

    // 3. Lấy thông tin phiếu mượn
    $stmt = $db->prepare(
        "SELECT br.id, br.user_id, br.book_id, br.due_date, br.delivery_type,
                br.payment_method, br.payment_status, br.shipping_fee,
                b.title AS book_title
         FROM borrowings br
         JOIN books b ON b.id = br.book_id
         WHERE br.id = ?"
    );
    $stmt->execute([(int)$borrowing_id]);
    $borrow = $stmt->fetch();

    if (!$borrow) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy phiếu mượn.']);
        exit;
    }

    $db->beginTransaction();
    $new_status = $status;
    $book_title = $borrow['book_title'];
    $borrow_id  = (int)$borrow['id'];
    $user_id    = (int)$borrow['user_id'];
    $due_date   = $borrow['due_date'];
    $isDelivery = $borrow['delivery_type'] === 'delivery';

    // 4. Cập nhật trạng thái + các timestamp tương ứng
    switch ($new_status) {
        case 'approved':
            $db->prepare("UPDATE borrowings SET status = ?, approved_at = NOW() WHERE id = ?")
               ->execute([$new_status, $borrow_id]);
            // Nếu pickup: cũng lock sách ngay khi approved
            if (!$isDelivery) {
                $db->prepare("UPDATE books SET status = 'borrowed' WHERE id = ?")
                   ->execute([$borrow['book_id']]);
            }
            break;

        case 'preparing':
            // Delivery: sách bị lock khi bắt đầu chuẩn bị
            $db->prepare("UPDATE borrowings SET status = ? WHERE id = ?")
               ->execute([$new_status, $borrow_id]);
            $db->prepare("UPDATE books SET status = 'borrowed' WHERE id = ?")
               ->execute([$borrow['book_id']]);
            break;

        case 'shipped':
            $db->prepare("UPDATE borrowings SET status = ?, shipped_at = NOW() WHERE id = ?")
               ->execute([$new_status, $borrow_id]);
            break;

        case 'borrowed':
            $db->prepare("UPDATE borrowings SET status = ?, approved_at = IFNULL(approved_at, NOW()) WHERE id = ?")
               ->execute([$new_status, $borrow_id]);
            $db->prepare("UPDATE books SET status = 'borrowed' WHERE id = ?")
               ->execute([$borrow['book_id']]);
            break;

        case 'returned':
            // Trả sách xong → mở lại cho người khác mượn
            $db->prepare("UPDATE borrowings SET status = ?, return_date = CURDATE() WHERE id = ?")
               ->execute([$new_status, $borrow_id]);
            $db->prepare("UPDATE books SET status = 'available' WHERE id = ?")
               ->execute([$borrow['book_id']]);
            break;

        case 'cancelled':
            $db->prepare("UPDATE borrowings SET status = ?, cancelled_at = NOW() WHERE id = ?")
               ->execute([$new_status, $borrow_id]);
            // Đảm bảo sách trở về available nếu đã bị lock
            $db->prepare("UPDATE books SET status = 'available' WHERE id = ? AND status = 'borrowed'")
               ->execute([$borrow['book_id']]);
            break;

        default:
            $db->prepare("UPDATE borrowings SET status = ? WHERE id = ?")
               ->execute([$new_status, $borrow_id]);
    }

    // 5. Gửi thông báo cho user theo từng trạng thái
    $feeFmt = number_format((int)$borrow['shipping_fee'], 0, ',', '.') . 'đ';

    switch ($new_status) {
        case 'approved':
            if ($isDelivery && $borrow['payment_method'] === 'vietqr') {
                createNotification($db, $user_id, 'borrow_approved',
                    'Yêu cầu mượn sách được duyệt ✅',
                    "Yêu cầu mượn \"{$book_title}\" đã được duyệt. Vui lòng thanh toán phí ship ({$feeFmt}) qua VietQR để chúng tôi chuẩn bị sách.",
                    $borrow_id);
            } elseif ($isDelivery && $borrow['payment_method'] === 'cod') {
                createNotification($db, $user_id, 'borrow_approved',
                    'Yêu cầu mượn sách được duyệt ✅',
                    "Yêu cầu mượn \"{$book_title}\" đã được duyệt. Sách sẽ được giao đến bạn. Phí ship ({$feeFmt}) thanh toán khi nhận.",
                    $borrow_id);
            } else {
                createNotification($db, $user_id, 'borrow_approved',
                    'Yêu cầu mượn sách được duyệt ✅',
                    "Yêu cầu mượn \"{$book_title}\" đã được duyệt. Hạn trả: {$due_date}. Vui lòng đến thư viện nhận sách.",
                    $borrow_id);
            }
            break;

        case 'preparing':
            createNotification($db, $user_id, 'borrow_approved',
                'Sách đang được chuẩn bị 📦',
                "Thư viện đang chuẩn bị cuốn \"{$book_title}\" để giao đến bạn. Chúng tôi sẽ thông báo khi sách được gửi đi.",
                $borrow_id);
            break;

        case 'shipped':
            createNotification($db, $user_id, 'borrow_approved',
                'Sách đang trên đường đến bạn 🚚',
                "Cuốn \"{$book_title}\" đã được giao cho đơn vị vận chuyển. Hạn trả sau khi nhận sách: {$due_date}.",
                $borrow_id);
            break;

        case 'borrowed':
            createNotification($db, $user_id, 'borrow_approved',
                'Xác nhận đã nhận sách 📚',
                "Bạn đã nhận cuốn \"{$book_title}\". Hạn trả: {$due_date}. Chúc bạn đọc vui!",
                $borrow_id);
            break;

        case 'return_shipping':
            createNotification($db, $user_id, 'system',
                'Sách đang trên đường về thư viện 🔄',
                "Thư viện đã nhận yêu cầu trả. Cuốn \"{$book_title}\" đang được vận chuyển về. Chúng tôi sẽ xác nhận khi nhận được.",
                $borrow_id);
            break;

        case 'returned':
            createNotification($db, $user_id, 'system',
                'Trả sách thành công ✅',
                "Thư viện đã xác nhận nhận lại cuốn \"{$book_title}\". Cảm ơn bạn!",
                $borrow_id);
            break;

        case 'overdue':
            createNotification($db, $user_id, 'return_overdue',
                'Sách quá hạn trả 🚨',
                "Cuốn \"{$book_title}\" đã quá hạn trả từ ngày {$due_date}. Vui lòng liên hệ thư viện ngay.",
                $borrow_id);
            break;

        case 'cancelled':
            createNotification($db, $user_id, 'system',
                'Yêu cầu mượn sách đã bị hủy',
                "Yêu cầu mượn cuốn \"{$book_title}\" đã bị hủy. Nếu có thắc mắc, vui lòng liên hệ thư viện.",
                $borrow_id);
            break;
    }

    $db->commit();

    // 6. Gửi FCM Realtime nếu sách được trả (có sẵn trở lại)
    if ($new_status === 'returned') {
        try {
            $factory   = (new Factory())->withServiceAccount(__DIR__.'/../firebase_credentials.json');
            $messaging = $factory->createMessaging();
            $message   = CloudMessage::withTarget('topic', 'book_updates')
                ->withData([
                    'action'       => 'status_changed',
                    'book_id'      => (string)$borrow['book_id'],
                    'is_available' => '1',
                ]);
            $messaging->send($message);
        } catch (\Exception $fcmEx) {
            error_log("FCM Send Error: " . $fcmEx->getMessage());
        }
    }

    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'message' => "Cập nhật trạng thái thành '{$new_status}' thành công.",
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
}
?>
