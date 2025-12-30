<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once '../config/database.php';
require_once '../config/middleware.php';

try {
    checkAdminAuth();

    $data = json_decode(file_get_contents("php://input"));
    if (empty($data->id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu ID']);
        exit;
    }

    $db = (new Database())->connect();

    // 1. Kiểm tra xem có sách nào thuộc thể loại này không?
    // (Bao gồm cả sách đã xóa mềm is_deleted=1 cũng nên check để bảo toàn lịch sử, hoặc tùy bạn)
    $checkQuery = "SELECT COUNT(*) FROM books WHERE category_id = ?";
    $stmtCheck = $db->prepare($checkQuery);
    $stmtCheck->execute([$data->id]);
    $bookCount = $stmtCheck->fetchColumn();

    if ($bookCount > 0) {
        http_response_code(409); // Conflict
        echo json_encode([
            'error' => "Không thể xóa! Có $bookCount cuốn sách đang thuộc thể loại này. Hãy chuyển sách sang thể loại khác trước."
        ]);
        exit;
    }

    // 2. Nếu không có sách nào thì xóa
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
    if ($stmt->execute([$data->id])) {
        echo json_encode(['message' => 'Đã xóa thể loại thành công']);
    } else {
        throw new Exception("Lỗi CSDL");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>