<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once '../config/database.php';
require_once '../config/middleware.php';
require_once '../config/notification_helper.php';

try {
    checkAdminAuth();

    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->borrow_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu ID phiếu mượn']);
        exit;
    }

    $db = (new Database())->connect();

    // Lấy thông tin phiếu mượn + tên sách + user_id để gửi thông báo
    $stmtCheck = $db->prepare(
        "SELECT br.id, br.book_id, br.user_id, br.status, b.title AS book_title
         FROM borrowings br
         JOIN books b ON b.id = br.book_id
         WHERE br.id = ?"
    );
    $stmtCheck->execute([$data->borrow_id]);
    $borrowing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$borrowing) {
        throw new Exception("Không tìm thấy phiếu mượn.");
    }

    if ($borrowing['status'] === 'returned') {
        echo json_encode(['message' => 'Sách này đã được trả trước đó rồi.']);
        exit;
    }

    // Cập nhật phiếu mượn -> returned
    $stmt = $db->prepare("UPDATE borrowings SET status = 'returned', return_date = NOW() WHERE id = ?");
    if (!$stmt->execute([$data->borrow_id])) {
        throw new Exception("Lỗi khi cập nhật phiếu mượn.");
    }

    // Trả kho sách -> available
    if ($borrowing['book_id']) {
        $db->prepare("UPDATE books SET status = 'available' WHERE id = ?")
           ->execute([$borrowing['book_id']]);
    }

    // Gửi thông báo cho người mượn
    createNotification(
        $db,
        (int)$borrowing['user_id'],
        'system',
        'Trả sách thành công 📚',
        'Thư viện đã xác nhận nhận lại cuốn "' . $borrowing['book_title'] . '". Cảm ơn bạn đã trả sách đúng hạn!',
        (int)$borrowing['id']
    );

    echo json_encode(['message' => 'Đã xác nhận trả sách thành công!']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>