<?php
// file: api/borrowings/update_status.php
// POST: Admin cập nhật trạng thái mượn sách + tự động tạo thông báo cho user
// Body JSON: { "borrowing_id": 5, "status": "borrowed" }
// status hợp lệ: borrowed | returning | returned | overdue
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../config/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 1. Chỉ admin mới được gọi API này (dùng checkAdminAuth() từ middleware.php)
$admin = checkAdminAuth();

// 2. Validate input
$data = json_decode(file_get_contents('php://input'));

if (empty($data->borrowing_id) || empty($data->status)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu tham số: borrowing_id hoặc status.']);
    exit;
}

$allowed_statuses = ['borrowed', 'returning', 'returned', 'overdue'];
if (!in_array($data->status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Status không hợp lệ. Các giá trị được phép: ' . implode(', ', $allowed_statuses)]);
    exit;
}

try {
    $db = (new Database())->connect();

    // 3. Lấy thông tin bản ghi mượn + tên sách
    $stmt_get = $db->prepare(
        "SELECT br.id, br.user_id, br.book_id, br.due_date,
                b.title AS book_title
         FROM borrowings br
         JOIN books b ON b.id = br.book_id
         WHERE br.id = ?"
    );
    $stmt_get->execute([(int)$data->borrowing_id]);
    $borrow = $stmt_get->fetch();

    if (!$borrow) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy bản ghi mượn sách.']);
        exit;
    }

    $db->beginTransaction();

    $new_status = $data->status;

    // 4. Cập nhật trạng thái
    if ($new_status === 'returned') {
        // Ghi ngày trả thực tế
        $db->prepare("UPDATE borrowings SET status = ?, return_date = CURDATE() WHERE id = ?")
           ->execute([$new_status, (int)$data->borrowing_id]);
        // Mở lại sách cho người khác mượn
        $db->prepare("UPDATE books SET status = 'available' WHERE id = ?")
           ->execute([$borrow['book_id']]);
    } else {
        $db->prepare("UPDATE borrowings SET status = ? WHERE id = ?")
           ->execute([$new_status, (int)$data->borrowing_id]);
    }

    // 5. Tạo thông báo cho user dựa trên trạng thái mới
    $user_id    = (int)$borrow['user_id'];
    $book_title = $borrow['book_title'];
    $borrow_id  = (int)$borrow['id'];
    $due_date   = $borrow['due_date'];

    switch ($new_status) {
        case 'borrowed':
            createNotification($db, $user_id, 'borrow_approved',
                'Yêu cầu mượn sách được duyệt ✅',
                "Yêu cầu mượn cuốn \"{$book_title}\" đã được Admin duyệt. Hạn trả: {$due_date}. Vui lòng đến thư viện để nhận sách.",
                $borrow_id
            );
            break;
        case 'overdue':
            createNotification($db, $user_id, 'return_overdue',
                'Sách quá hạn trả 🚨',
                "Cuốn \"{$book_title}\" đã quá hạn trả từ ngày {$due_date}. Vui lòng liên hệ thư viện ngay.",
                $borrow_id
            );
            break;
        case 'returned':
            createNotification($db, $user_id, 'system',
                'Trả sách thành công 📚',
                "Thư viện đã xác nhận nhận lại cuốn \"{$book_title}\". Cảm ơn bạn đã trả sách đúng hạn!",
                $borrow_id
            );
            break;
        case 'returning':
            // User yêu cầu trả → admin chưa xác nhận → không gửi thông báo
            break;
    }

    $db->commit();

    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'message' => "Cập nhật trạng thái mượn thành '{$new_status}' thành công.",
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
}
?>
