<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Xử lý preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Xác thực Token (Bắt buộc đăng nhập)
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr = explode(" ", $authHeader);
$token = $arr[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Vui lòng đăng nhập để quyên góp sách.']);
    exit;
}

try {
    // Giải mã Token để lấy user_id
    $key = "B4E_SECRET_KEY_123456"; // Key bí mật (phải trùng với login.php)
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;

    // 2. Nhận dữ liệu từ form
    $data = json_decode(file_get_contents('php://input'));

    // Validate dữ liệu cơ bản
    if (empty($data->book_title) || empty($data->book_author) || empty($data->donation_type)) {
        http_response_code(400);
        echo json_encode(['error' => 'Vui lòng điền tên sách, tác giả và hình thức quyên góp.']);
        exit;
    }

    // 3. Lưu vào CSDL
    $database = new Database();
    $db = $database->connect();

    $query = "INSERT INTO donations 
              (user_id, book_title, book_author, book_publisher, book_year, book_condition, donation_type, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";

    $stmt = $db->prepare($query);

    // Gán giá trị (Xử lý các trường không bắt buộc như publisher, year...)
    $book_publisher = $data->book_publisher ?? null;
    $book_year = $data->book_year ?? null;
    $book_condition = $data->book_condition ?? 'Tốt';

    if ($stmt->execute([
        $user_id, 
        $data->book_title, 
        $data->book_author, 
        $book_publisher, 
        $book_year, 
        $book_condition, 
        $data->donation_type
    ])) {
        http_response_code(201);
        echo json_encode(['message' => 'Cảm ơn bạn! Yêu cầu quyên góp đã được gửi và đang chờ duyệt.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi hệ thống, không thể lưu yêu cầu.']);
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Phiên đăng nhập không hợp lệ: ' . $e->getMessage()]);
}
?>