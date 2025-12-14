<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../config/middleware.php';

try {

    $admin_data = checkAdminAuth();
    $database = new Database();
    $conn = $database->connect();

    // Query phức tạp: Join bảng borrowings, users và books
    $query = "SELECT br.*, u.username, u.phone, b.title as book_title, b.image_url 
              FROM borrowings br
              JOIN users u ON br.user_id = u.id
              JOIN books b ON br.book_id = b.id
              ORDER BY br.borrow_date DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>