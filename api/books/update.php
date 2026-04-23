<?php
// 1. Cấu hình Headers & CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Dùng POST cho Form Data chứa file
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Xử lý Preflight Request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Nhúng file cấu hình (Lùi ra 2 cấp thư mục: server/api/books/ -> server/)
require_once '../config/database.php';
require_once '../config/middleware.php';

try {
    // 3. Xác thực Admin
    $admin_data = checkAdminAuth();

    $database = new Database();
    $conn = $database->connect();

    // 4. Lấy ID và Validate
    $id = $_POST['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        throw new Exception("Thiếu ID sách cần cập nhật");
    }

    // 5. Lấy thông tin sách cũ 
    $stmtGet = $conn->prepare("SELECT image_url FROM books WHERE id = ?");
    $stmtGet->execute([$id]);
    $oldBook = $stmtGet->fetch(PDO::FETCH_ASSOC);

    if (!$oldBook) {
        http_response_code(404);
        throw new Exception("Sách không tồn tại");
    }

    $image_url_to_save = $oldBook['image_url']; // Mặc định giữ ảnh cũ

    // 6. Xử lý Upload Ảnh Mới (Nếu có)
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_root = "../uploads/img/Book/"; 
        
        // Tạo thư mục nếu chưa có
        if (!is_dir($upload_root)) {
            mkdir($upload_root, 0777, true);
        }

        // Tạo tên file an toàn
        $filename = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $upload_root . $filename;
        
        // Kiểm tra định dạng ảnh
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            throw new Exception("Chỉ cho phép định dạng ảnh (JPG, PNG, GIF, WEBP)");
        }

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_url_to_save = "img/Book/" . $filename;

            if (!empty($oldBook['image_url'])) {
                $old_file_phys = "../uploads/" . $oldBook['image_url'];
                if (file_exists("../" . $oldBook['image_url'])) { 
                     unlink("../" . $oldBook['image_url']);
                }
            }
        } else {
            throw new Exception("Lỗi khi tải ảnh lên server.");
        }
    }

    // 7. Cập nhật Database
    $sql = "UPDATE books SET title=?, author=?, category_id=?, publisher=?, year=?, description=?, image_url=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    
    // Sử dụng $_POST có kiểm tra null để tránh lỗi Undefined index
    $result = $stmt->execute([
        $_POST['title'] ?? '', 
        $_POST['author'] ?? '', 
        $_POST['category_id'] ?? null, 
        $_POST['publisher'] ?? '', 
        $_POST['year'] ?? '', 
        $_POST['description'] ?? '', 
        $image_url_to_save, 
        $id
    ]);

    if ($result) {
        echo json_encode(['message' => 'Cập nhật sách thành công']);
    } else {
        throw new Exception("Lỗi khi cập nhật cơ sở dữ liệu");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>