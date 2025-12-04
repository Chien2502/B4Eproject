<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Xử lý Preflight Request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

// 2. Kết nối Database
require_once '../config/database.php';

// 3. Kiểm tra Quyền Admin (Sử dụng PHP Session)
// Vì file này được gọi từ trang Admin Dashboard (cùng domain), ta dùng Session để bảo mật.
session_start();
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['admin', 'super-admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

// 4. Nhận dữ liệu đầu vào
$data = json_decode(file_get_contents('php://input'));

if (empty($data->donation_id)) {
    http_response_code(400); 
    echo json_encode(['error' => 'Thiếu ID quyên góp.']); 
    exit;
}

$database = new Database();
$db = $database->connect();

try {
    // 5. BẮT ĐẦU GIAO DỊCH (TRANSACTION)
    // Quan trọng: Đảm bảo cả 2 việc (Thêm sách & Cập nhật quyên góp) cùng thành công hoặc cùng thất bại.
    $db->beginTransaction();

    // BƯỚC 5.1: Lấy thông tin từ bảng Donations
    $query_get = "SELECT * FROM donations WHERE id = ? AND status = 'pending'";
    $stmt_get = $db->prepare($query_get);
    $stmt_get->execute([$data->donation_id]);
    
    if ($stmt_get->rowCount() == 0) {
        throw new Exception("Yêu cầu quyên góp không tồn tại hoặc đã được xử lý trước đó.");
    }

    $donation = $stmt_get->fetch(PDO::FETCH_ASSOC);

    // BƯỚC 5.2: Thêm sách mới vào bảng Books
    // Lưu ý: category_id tạm thời để NULL (Admin có thể cập nhật sau khi sách vào kho)
    // image_url để NULL hoặc set ảnh mặc định
    $query_insert = "INSERT INTO books (title, author, publisher, year, description, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, 'available', NOW())";
    
    // Tạo mô tả tự động từ thông tin quyên góp
    $description = "Sách được quyên góp từ cộng đồng. Tình trạng: " . ($donation['book_condition'] ?? 'Tốt');

    $stmt_insert = $db->prepare($query_insert);
    $insert_success = $stmt_insert->execute([
        $donation['book_title'],
        $donation['book_author'],
        $donation['book_publisher'],
        $donation['book_year'],
        $description
    ]);

    if (!$insert_success) {
        throw new Exception("Lỗi khi thêm sách vào kho.");
    }

    // BƯỚC 5.3: Cập nhật trạng thái bảng Donations thành 'approved'
    $query_update = "UPDATE donations SET status = 'approved' WHERE id = ?";
    $stmt_update = $db->prepare($query_update);
    $update_success = $stmt_update->execute([$data->donation_id]);

    if (!$update_success) {
        throw new Exception("Lỗi khi cập nhật trạng thái quyên góp.");
    }

    // 6. HOÀN TẤT GIAO DỊCH
    $db->commit();

    http_response_code(200);
    echo json_encode([
        'message' => 'Thành công! Sách đã được thêm vào kho và hiển thị cho độc giả.'
    ]);

} catch (Exception $e) {
    // Nếu có bất kỳ lỗi nào, hoàn tác mọi thay đổi (Rollback)
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi xử lý: ' . $e->getMessage()]);
}
?>