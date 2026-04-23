<?php
// file: api/books/read_single.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu ID sách.']);
    exit;
}

$database = new Database();
$db = $database->connect();

try {
    // JOIN với bảng categories để lấy tên thể loại
    $query = "SELECT b.*, c.name as category_name 
              FROM books b 
              LEFT JOIN categories c ON b.category_id = c.id 
              WHERE b.id = ?
              AND is_deleted = 0";
              
    $stmt = $db->prepare($query);
    $stmt->execute([$_GET['id']]);

    if ($stmt->rowCount() > 0) {
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($book);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy sách.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
}
?>