<?php
// file: api/auth/update_profile.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Xử lý OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Chỉ cho phép POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    exit;
}

// Lấy Token từ header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr = explode(" ", $authHeader);
if (count($arr) < 2) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không được cung cấp.']);
    exit;
}
$token = $arr[1];

$secret_key = "B4E_SECRET_KEY_123456"; // Phải giống hệt key trong login.php

try {
    // 1. Xác thực Token
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $user_id = $decoded->data->id;

    // 2. Lấy dữ liệu mới từ body
    $data = json_decode(file_get_contents('php://input'));

    if (empty($data->username) || empty($data->phone) || empty($data->address)) {
        http_response_code(400);
        echo json_encode(['error' => 'Vui lòng cung cấp đầy đủ username, phone và address.']);
        exit;
    }

    // 3. Cập nhật CSDL
    $database = new Database();
    $db = $database->connect();
    
    // Kiểm tra xem username mới có bị trùng không (ngoại trừ chính user này)
    $query_check_user = 'SELECT id FROM users WHERE username = ? AND id != ?';
    $stmt_check_user = $db->prepare($query_check_user);
    $stmt_check_user->execute([$data->username, $user_id]);
    
    if ($stmt_check_user->rowCount() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Username này đã được người khác sử dụng.']);
        exit;
    }

    // Tiến hành cập nhật
    $query_update = 'UPDATE users SET username = ?, phone = ?, address = ? WHERE id = ?';
    $stmt_update = $db->prepare($query_update);
    
    if ($stmt_update->execute([$data->username, $data->phone, $data->address, $user_id])) {
        
        // Trả về thông tin user mới để FE cập nhật localStorage
        $new_user_data = [
            'username' => $data->username,
            'email' => $decoded->data->email, // Email không đổi
            'role' => $decoded->data->role    // Role không đổi
        ];

        http_response_code(200);
        echo json_encode([
            'message' => 'Cập nhật thông tin thành công.',
            'user' => $new_user_data
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi máy chủ khi cập nhật.']);
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ: ' . $e->getMessage()]);
}
?>