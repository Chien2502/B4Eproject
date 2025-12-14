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

    $id = $_POST['id'] ?? null;
    if(!$id) throw new Exception("Thiếu ID sách");

    // Lấy thông tin cũ để giữ ảnh nếu không upload ảnh mới
    $oldBook = $conn->query("SELECT image_url FROM books WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
    $image_url = $oldBook['image_url'];

    // Xử lý Upload Ảnh Mới
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../uploads/img/Book/";
        $filename = time() . "_" . basename($_FILES["image"]["name"]);
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $filename)) {
            $image_url = "img/Book/" . $filename;
        }
    }

    $sql = "UPDATE books SET title=?, author=?, category_id=?, publisher=?, year=?, description=?, image_url=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([
        $_POST['title'], $_POST['author'], $_POST['category_id'], 
        $_POST['publisher'], $_POST['year'], $_POST['description'], 
        $image_url, $id
    ])) {
        echo json_encode(['message' => 'Cập nhật thành công']);
    } else {
        throw new Exception("Lỗi CSDL");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>