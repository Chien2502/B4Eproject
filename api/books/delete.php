<?php
// 1. Cấu hình CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../config/middleware.php';

try {
    // 2. Kiểm tra Auth
    $admin_data = checkAdminAuth();

    // 3. Lấy dữ liệu ID
    $data = json_decode(file_get_contents("php://input"));
    if (!isset($data->id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu ID sách cần xóa.']);
        exit;
    }

    $db = (new Database())->connect();
    
    // 4. Kiểm tra sách có đang được mượn không?
    $check = $db->prepare("SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND (status = 'borrowed' OR status = 'returning' OR status = 'overdue')");
    $check->execute([$data->id]);
    
    if ($check->fetchColumn() > 0) {
        http_response_code(409); 
        echo json_encode(['error' => 'Không thể xóa! Sách đang trong quá trình mượn/trả.']);
        exit;
    }

    // 5. THỰC HIỆN SOFT DELETE
    // Chỉ cập nhật trạng thái is_deleted thành 1
    $stmt = $db->prepare("UPDATE books SET is_deleted = 1 WHERE id = ?");
    
    if ($stmt->execute([$data->id])) {
        echo json_encode(['message' => 'Đã chuyển sách vào thùng rác.']);
    } else {
        throw new Exception("Lỗi khi cập nhật trạng thái sách.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>