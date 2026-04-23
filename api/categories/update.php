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

    if (empty($data->id) || empty($data->name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu dữ liệu (ID hoặc Tên)']);
        exit;
    }

    $db = (new Database())->connect();
    
    $stmt = $db->prepare("UPDATE categories SET name = ? WHERE id = ?");
    
    if ($stmt->execute([$data->name, $data->id])) {
        echo json_encode(['message' => 'Cập nhật thể loại thành công']);
    } else {
        throw new Exception("Lỗi khi cập nhật");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>