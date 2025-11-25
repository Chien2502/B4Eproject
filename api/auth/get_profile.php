<?php
// file: api/auth/get_profile.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

    // 2. Lấy thông tin user từ CSDL
    $database = new Database();
    $db = $database->connect();
    
    $query = 'SELECT id, username, email, phone, address, role FROM users WHERE id = ?';
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($user);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy người dùng.']);
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ: ' . $e->getMessage()]);
}
?>