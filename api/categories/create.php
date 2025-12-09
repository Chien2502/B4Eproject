<?php
header('Content-Type: application/json');
session_start();

// 1. Kiểm tra quyền Admin
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['admin', 'super-admin'])) {
    http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
}

require_once '../config/database.php';
$data = json_decode(file_get_contents('php://input'));

if (empty($data->name)) {
    http_response_code(400); echo json_encode(['error' => 'Tên thể loại không được để trống.']); exit;
}

try {
    $db = (new Database())->connect();
    
    // Kiểm tra trùng tên
    $check = $db->prepare("SELECT id FROM categories WHERE name = ?");
    $check->execute([$data->name]);
    if ($check->rowCount() > 0) {
        throw new Exception("Thể loại này đã tồn tại!");
    }

    // Thêm mới
    $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
    if ($stmt->execute([$data->name])) {
        // Trả về ID vừa tạo để frontend tự động chọn
        $new_id = $db->lastInsertId();
        echo json_encode([
            'message' => 'Thêm thể loại thành công.',
            'id' => $new_id,
            'name' => $data->name
        ]);
    } else {
        throw new Exception("Lỗi CSDL.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>