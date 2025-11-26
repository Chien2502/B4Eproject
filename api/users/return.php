<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

// Auth
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = explode(" ", $authHeader)[1] ?? '';
if (!$token) { http_response_code(401); exit; }

try {
    $key = "B4E_SECRET_KEY_123456";
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;

    $data = json_decode(file_get_contents('php://input'));
    $borrow_id = $data->borrow_id;

    $db = (new Database())->connect();

    // Kiểm tra lượt mượn có hợp lệ không
    $check = $db->prepare("SELECT id FROM borrowings WHERE id = ? AND user_id = ? AND status = 'borrowed'");
    $check->execute([$borrow_id, $user_id]);
    
    if ($check->rowCount() == 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Yêu cầu không hợp lệ (Sách đã trả hoặc đang chờ xác nhận).']);
        exit;
    }

    // CHỈ CẬP NHẬT TRẠNG THÁI LƯỢT MƯỢN -> 'returning'
    // Không cập nhật bảng books!
    $stmt = $db->prepare("UPDATE borrowings SET status = 'returning' WHERE id = ?");
    
    if ($stmt->execute([$borrow_id])) {
        echo json_encode(['message' => 'Đã gửi yêu cầu trả sách. Vui lòng mang sách đến thư viện hoặc gửi qua bưu điện.']);
    } else {
        throw new Exception("Lỗi cập nhật CSDL.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>