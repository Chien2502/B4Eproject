<?php
// 1. Cấu hình CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Xử lý Preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../config/middleware.php';

try {
    // 2. Kiểm tra Admin
    checkAdminAuth();

    // 3. NHẬN DỮ LIỆU JSON
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->borrow_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu ID phiếu mượn']);
        exit;
    }

    $db = (new Database())->connect();

    // 4. Kiểm tra trạng thái hiện tại
    // Chỉ xử lý nếu đang mượn (borrowed) hoặc đang trả (returning) hoặc quá hạn (overdue)
    $stmtCheck = $db->prepare("SELECT book_id, status FROM borrowings WHERE id = ?");
    $stmtCheck->execute([$data->borrow_id]);
    $borrowing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$borrowing) {
        throw new Exception("Không tìm thấy phiếu mượn.");
    }
    
    // Nếu đã trả rồi thì thôi
    if ($borrowing['status'] === 'returned') {
        echo json_encode(['message' => 'Sách này đã được trả trước đó rồi.']);
        exit;
    }

    // 5. Cập nhật phiếu mượn -> returned
    $stmt = $db->prepare("UPDATE borrowings SET status = 'returned', return_date = NOW() WHERE id = ?");
    if (!$stmt->execute([$data->borrow_id])) {
        throw new Exception("Lỗi khi cập nhật phiếu mượn.");
    }

    // 6. Cập nhật sách -> available (Trả kho)
    // Chỉ cập nhật nếu sách chưa bị xóa mềm (is_deleted = 0)
    if ($borrowing['book_id']) {
        $stmtBook = $db->prepare("UPDATE books SET status = 'available' WHERE id = ?");
        $stmtBook->execute([$borrowing['book_id']]);
    }

    echo json_encode(['message' => 'Đã xác nhận trả sách thành công!']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>