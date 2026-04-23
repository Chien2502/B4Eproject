<?php
// 1. Cấu hình CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Xử lý Preflight Request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once '../config/database.php'; 
    require_once '../config/middleware.php';  

    $admin_data = checkAdminAuth(); 
    $current_admin_id = $admin_data->id;

    // 4. Kết nối Database
    $database = new Database();
    $conn = $database->connect();

    // 5. Truy vấn danh sách
    $query = "SELECT id, username, email, phone, address, role, created_at FROM users ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Trả về JSON sạch
    echo json_encode([
        'data' => $users,
        'current_admin_id' => $current_admin_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}