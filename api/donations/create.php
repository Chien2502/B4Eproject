<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr = explode(" ", $authHeader);
$token = $arr[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Vui lòng đăng nhập để quyên góp sách.']);
    exit;
}

try {
    $key = getenv('JWT_SECRET_KEY') ?: getenv('JWT_SECRET_KEY') ?: 'B4E_SECRET_KEY_123456';
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;

    // Handle multipart/form-data OR application/json
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        $data = $_POST;
    }

    if (empty($data['book_title']) || empty($data['book_author']) || empty($data['donation_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Vui lòng điền tên sách, tác giả và hình thức quyên góp.']);
        exit;
    }

    $image_url = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $fileName;
        
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($imageFileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $image_url = $fileName;
            }
        }
    }

    $database = new Database();
    $db = $database->connect();

    $donation_type = $data['donation_type'] ?? 'directDelivery';
    $pickup_type = ($donation_type === 'shipToLibrary') ? 'user_ship' : 'self_deliver';

    $query = "INSERT INTO donations 
              (user_id, book_title, book_author, book_publisher, book_year, book_condition, donation_type, pickup_type, image_url, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

    $stmt = $db->prepare($query);

    $book_publisher = $data['book_publisher'] ?? null;
    $book_year = $data['book_year'] ?? null;
    $book_condition = $data['book_condition'] ?? 'Tốt';

    if ($stmt->execute([
        $user_id, 
        $data['book_title'], 
        $data['book_author'], 
        $book_publisher, 
        $book_year, 
        $book_condition, 
        $donation_type,
        $pickup_type,
        $image_url
    ])) {
        http_response_code(201);
        echo json_encode(['message' => 'Cảm ơn bạn! Yêu cầu quyên góp đã được gửi.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi hệ thống, không thể lưu yêu cầu.']);
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Phiên đăng nhập không hợp lệ: ' . $e->getMessage()]);
}
?>