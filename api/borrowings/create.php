<?php
// file: api/borrowings/create.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../../vendor/autoload.php'; // Gọi thư viện JWT
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

// 1. Xác thực Token (Bảo vệ API)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr = explode(" ", $authHeader);
$token = $arr[1] ?? '';

if (!$token) {
    http_response_code(401); echo json_encode(['error' => 'Vui lòng đăng nhập để mượn sách.']); exit;
}

try {
    $key = "B4E_SECRET_KEY_123456"; // Khóa bí mật (phải trùng với login.php)
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id; // Lấy ID người dùng từ token

    // 2. Nhận dữ liệu sách cần mượn
    $data = json_decode(file_get_contents('php://input'));
    if (empty($data->book_id)) {
        http_response_code(400); echo json_encode(['error' => 'Thiếu ID sách.']); exit;
    }

    $db = (new Database())->connect();

    // 3. Kiểm tra xem sách có "available" không?
    $stmt = $db->prepare("SELECT status FROM books WHERE id = ?");
    $stmt->execute([$data->book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$book || $book['status'] !== 'available') {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Sách này hiện không có sẵn để mượn.']);
        exit;
    }

    // 4. Thực hiện Giao dịch (Transaction)
    $db->beginTransaction(); // Bắt đầu giao dịch an toàn

    try {
        // B4.1: Tạo bản ghi mượn sách
        // Mặc định hạn trả là 14 ngày sau
        $sql_borrow = "INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status) 
                       VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'borrowed')";
        $stmt_borrow = $db->prepare($sql_borrow);
        $stmt_borrow->execute([$user_id, $data->book_id]);

        // B4.2: Cập nhật trạng thái sách thành 'borrowed'
        $sql_update = "UPDATE books SET status = 'borrowed' WHERE id = ?";
        $stmt_update = $db->prepare($sql_update);
        $stmt_update->execute([$data->book_id]);

        $db->commit(); // Chốt giao dịch (Lưu thay đổi)
        
        http_response_code(201);
        echo json_encode(['message' => 'Mượn sách thành công!']);

    } catch (Exception $e) {
        $db->rollBack(); // Nếu lỗi, hoàn tác mọi thứ
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>