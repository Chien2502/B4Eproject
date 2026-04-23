<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

function checkAdminAuth() {
    // 1. Lấy Token từ Header
    $headers = apache_request_headers(); 
    $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    $arr = explode(" ", $authHeader);
    $token = $arr[1] ?? '';

    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Token not found']);
        exit();
    }

    try {
        // 2. Giải mã Token
        $key = "B4E_SECRET_KEY_123456"; 
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        
        // 3. Kiểm tra Role trong Token
        $role = $decoded->data->role;
        if ($role !== 'admin' && $role !== 'super-admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Admin access required']);
            exit();
        }

        // Trả về thông tin user để API sử dụng nếu cần
        return $decoded->data;

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Invalid Token']);
        exit();
    }
}
?>