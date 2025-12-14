<?php
// file: server/api/admin/update_user.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once '../config/database.php';
require_once '../config/middleware.php';

$admin_data = checkAdminAuth(); 
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->id) || !isset($data->username)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu dữ liệu bắt buộc.']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();

    $query = "UPDATE users SET username = ?, phone = ?, address = ?, role = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt->execute([$data->username, $data->phone, $data->address, $data->role, $data->id])) {
        echo json_encode(['message' => 'Cập nhật thông tin thành công.']);
    } else {
        throw new Exception("Lỗi khi cập nhật.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>