<?php
header('Content-Type: application/json');
session_start();

// Kiểm tra quyền Admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['admin', 'super-admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';
$data = json_decode(file_get_contents('php://input'));

if (empty($data->id)) {
    http_response_code(400); echo json_encode(['error' => 'Thiếu ID sách.']); exit;
}

try {
    $db = (new Database())->connect();
    
    // Kiểm tra xem sách có đang được mượn không? (Nếu đang mượn thì không được xóa)
    $check = $db->prepare("SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND status = 'borrowed'");
    $check->execute([$data->id]);
    if ($check->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Không thể xóa! Sách này đang có người mượn.']);
        exit;
    }

    // Thực hiện xóa
    $stmt = $db->prepare("DELETE FROM books WHERE id = ?");
    if ($stmt->execute([$data->id])) {
        echo json_encode(['message' => 'Đã xóa sách thành công.']);
    } else {
        throw new Exception("Lỗi CSDL.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>