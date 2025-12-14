<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../config/database.php';
require_once '../config/middleware.php';

try {
$admin_data= checkAdminAuth();

    $database = new Database();
    $conn = $database->connect();

    // Lấy dữ liệu từ $_POST (vì dùng FormData để gửi ảnh)
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $category_id = $_POST['category_id'] ?? null;
    $publisher = $_POST['publisher'] ?? '';
    $year = $_POST['year'] ?? '';
    $description = $_POST['description'] ?? '';

    // Xử lý Upload Ảnh
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../uploads/img/Book/"; // Lưu vào thư mục api/uploads
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $filename = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $filename;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_url = "img/Book/" . $filename; // Đường dẫn tương đối để lưu DB
        }
    }

    $sql = "INSERT INTO books (title, author, category_id, publisher, year, description, image_url, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'available')";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$title, $author, $category_id, $publisher, $year, $description, $image_url])) {
        http_response_code(201);
        echo json_encode(['message' => 'Thêm sách thành công', 'id' => $conn->lastInsertId()]);
    } else {
        throw new Exception("Lỗi khi thêm sách");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>