<?php
// file: api/borrowings/update_status.php
// PUT/POST: Admin cập nhật trạng thái mượn sách
// Body JSON: { "borrowing_id": 5, "status": "returned" }
// Các status hợp lệ: borrowed, returning, returned, overdue
// → Tự động tạo thông báo cho user khi status thay đổi có ý nghĩa
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../config/notification_helper.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// 1. Xác thực JWT + kiểm tra quyền Admin
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr   = explode(' ', $authHeader);
$token = $arr[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Vui lòng đăng nhập.']);
    exit;
}

try {
    $key     = 'B4E_SECRET_KEY_123456';
    $decoded = JWT::decode($token, new Key($key, 'HS256'));

    // Chỉ admin và super-admin mới được phép
    $role = $decoded->data->role ?? 'user';
    if (!in_array($role, ['admin', 'super-admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Bạn không có quyền thực hiện thao tác này.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'));

    if (empty($data->borrowing_id) || empty($data->status)) {
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu tham số: borrowing_id hoặc status.']);
        exit;
    }

    $allowed_statuses = ['borrowed', 'returning', 'returned', 'overdue'];
    if (!in_array($data->status, $allowed_statuses)) {
        http_response_code(400);
        echo json_encode(['error' => 'Trạng thái không hợp lệ.']);
        exit;
    }

    $db = (new Database())->connect();

    // 2. Lấy thông tin hiện tại của bản ghi mượn (để lấy user_id, book title)
    $stmt_get = $db->prepare(
        "SELECT br.id, br.user_id, br.book_id, br.status AS old_status, br.due_date,
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

    try {
        $new_status = $data->status;

        // 3. Cập nhật trạng thái mượn
        $return_date_sql = ($new_status === 'returned') ? ', return_date = CURDATE()' : '';
        $stmt_update = $db->prepare(
            "UPDATE borrowings SET status = ? {$return_date_sql} WHERE id = ?"
        );
        $stmt_update->execute([$new_status, (int)$data->borrowing_id]);

        // 4. Nếu trả sách, mở lại trạng thái sách thành available
        if ($new_status === 'returned') {
            $db->prepare("UPDATE books SET status = 'available' WHERE id = ?")
               ->execute([$borrow['book_id']]);
        }

        // 5. Tạo thông báo dựa trên trạng thái mới
        $user_id    = (int)$borrow['user_id'];
        $book_title = $borrow['book_title'];
        $borrow_id  = (int)$borrow['id'];
        $due_date   = $borrow['due_date'];

        switch ($new_status) {
            case 'borrowed':
                // Thông báo khi Admin xác nhận đã cho mượn (bắt đầu hạn)
                createNotification($db, $user_id, 'borrow_approved',
                    'Yêu cầu mượn sách được duyệt ✅',
                    "Yêu cầu mượn cuốn \"{$book_title}\" đã được duyệt. Hạn trả: {$due_date}. Vui lòng đến thư viện nhận sách.",
                    $borrow_id
                );
                break;

            case 'overdue':
                createNotification($db, $user_id, 'return_overdue',
                    'Sách quá hạn trả 🚨',
                    "Cuốn \"{$book_title}\" đã quá hạn trả từ {$due_date}. Vui lòng liên hệ thư viện ngay.",
                    $borrow_id
                );
                break;

            case 'returned':
                createNotification($db, $user_id, 'system',
                    'Trả sách thành công 📚',
                    "Thư viện đã xác nhận nhận lại cuốn \"{$book_title}\". Cảm ơn bạn!",
                    $borrow_id
                );
                break;

            case 'returning':
                // Trạng thái user yêu cầu trả (không cần thông báo thêm)
                break;
        }

        $db->commit();

        http_response_code(200);
        echo json_encode([
            'status'  => 'success',
            'message' => "Đã cập nhật trạng thái mượn sách thành '{$new_status}'.",
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>
