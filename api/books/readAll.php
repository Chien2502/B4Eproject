<?php
// 1. Cấu hình Headers (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Nhúng các file cấu hình
require_once '../config/database.php';

try {

    // 4. Kết nối Database
    $database = new Database();
    $conn = $database->connect();

    $query = "SELECT 
                b.*, 
                c.name as category_name 
              FROM books b
              LEFT JOIN categories c ON b.category_id = c.id
              WHERE b.is_deleted = 0
              ORDER BY b.id DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Trả về kết quả JSON
    echo json_encode([
        'success' => true,
        'count' => count($books),
        'data' => $books
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>