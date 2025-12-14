<?php
// 1. Cấu hình CORS và JSON Header (BẮT BUỘC ĐẶT TRÊN CÙNG)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Xử lý Preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


try {
    require_once "../config/database.php";
    require_once "../config/middleware.php";

    $admin_data = checkAdminAuth();

    // 3. Kết nối CSDL
    $database = new Database();
    $conn = $database->connect();

    // 4. Truy vấn dữ liệu
    $query = "SELECT d.*, u.username, u.email 
              FROM donations d 
              LEFT JOIN users u ON d.user_id = u.id 
              WHERE d.status = 'pending' 
              ORDER BY d.created_at ASC";
              
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Trả về kết quả JSON
    echo json_encode($donations);

} catch (Exception $e) {
    // Nếu có lỗi, trả về JSON lỗi (HTTP 500)
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>