<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");


// Xử lý Preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../config/middleware.php';

$admin_data = checkAdminAuth(); 

$data = json_decode(file_get_contents('php://input'));

if (empty($data->id)) {
    http_response_code(400); echo json_encode(['error' => 'Thiếu ID người dùng.']); exit;
}

try {
    $db = (new Database())->connect();
    // 3. Lấy thông tin Role của người SẮP bị xóa
    $stmt_check = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_check->execute([$data->id]);
    $target_user = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$target_user) {
        http_response_code(404);
        echo json_encode(['error' => 'Người dùng không tồn tại.']);
        exit;
    }

    $target_role = $target_user['role'];     // Role của người bị xóa
    $current_role = $admin_data->role;       // Role của người đang thao tác (Admin/Super)

    // 4. Logic so sánh quyền hạn
    if ($current_role === 'admin') {
        // Nếu tôi là 'admin' thường:
        // Tôi KHÔNG ĐƯỢC xóa 'admin' khác hoặc 'super-admin'
        if ($target_role === 'admin' || $target_role === 'super-admin') {
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Quyền hạn không đủ: Admin không thể xóa Admin khác hoặc Super-Admin.']);
            exit;
        }
    }
    
    // 5. Thực hiện xóa
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$data->id])) {
        echo json_encode(['message' => 'Đã xóa người dùng thành công.']);
    } else {
        throw new Exception("Lỗi CSDL.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>