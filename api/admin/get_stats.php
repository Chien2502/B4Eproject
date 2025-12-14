<?php
// 1. Cấu hình CORS và Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Xử lý Preflight Request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Nhúng file cấu hình 
require_once '../config/database.php';
require_once '../config/middleware.php'; // Middleware chứa hàm checkAdminAuth

try {
    // 3. Xác thực JWT bằng Middleware
    // Nếu token sai/hết hạn, hàm này tự động trả về 401 và exit.
    $admin_data = checkAdminAuth(); 
    
    // 4. Kết nối Database
    $database = new Database();
    $conn = $database->connect();

    // 5. Tính toán số liệu
    $stats = [];
    $stats['books'] = $conn->query("SELECT COUNT(*) FROM books")->fetchColumn();
    $stats['users'] = $conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
    $stats['pending_donations'] = $conn->query("SELECT COUNT(*) FROM donations WHERE status='pending'")->fetchColumn();
    $stats['returning_books'] = $conn->query("SELECT COUNT(*) FROM borrowings WHERE status='returning'")->fetchColumn();

    // 6. Đính kèm ID Admin (để đồng bộ với logic Frontend)
    $stats['current_admin_id'] = $admin_data->id;

    echo json_encode($stats);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>