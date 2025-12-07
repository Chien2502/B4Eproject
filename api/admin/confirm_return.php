<?php
header('Content-Type: application/json');
session_start();

// 1. Kiểm tra quyền Admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['admin', 'super-admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';
$data = json_decode(file_get_contents('php://input'));

if (empty($data->borrow_id)) {
    http_response_code(400); echo json_encode(['error' => 'Thiếu ID lượt mượn.']); exit;
}

$database = new Database();
$db = $database->connect();

try {
    $db->beginTransaction();

    // 2. Lấy thông tin lượt mượn để biết Book ID là gì
    $stmt = $db->prepare("SELECT book_id, status FROM borrowings WHERE id = ?");
    $stmt->execute([$data->borrow_id]);
    $borrow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$borrow) {
        throw new Exception("Lượt mượn không tồn tại.");
    }
    
    // Chỉ cho phép xác nhận nếu đang mượn hoặc đang trả
    if ($borrow['status'] == 'returned') {
        throw new Exception("Lượt mượn này đã được hoàn tất trước đó.");
    }

    // 3. Cập nhật bảng Borrowings (Đánh dấu đã trả + Ngày trả thực tế)
    $update_borrow = "UPDATE borrowings SET status = 'returned', return_date = CURDATE() WHERE id = ?";
    $stmt1 = $db->prepare($update_borrow);
    $stmt1->execute([$data->borrow_id]);

    // 4. Cập nhật bảng Books (Đánh dấu sách có sẵn)
    $update_book = "UPDATE books SET status = 'available' WHERE id = ?";
    $stmt2 = $db->prepare($update_book);
    $stmt2->execute([$borrow['book_id']]);

    $db->commit();
    echo json_encode(['message' => 'Đã xác nhận trả sách. Sách đã có sẵn trong kho.']);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>