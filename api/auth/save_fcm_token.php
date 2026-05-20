<?php
// file: api/auth/save_fcm_token.php
// POST: Lưu FCM device token của user lên server
// Body JSON: { "fcm_token": "ExponentPushToken[...]" hoặc FCM token string }
// Auth: Yêu cầu JWT (user đã đăng nhập)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 1. Xác thực JWT (bất kỳ user đã đăng nhập đều được)
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr   = explode(' ', $authHeader);
$token = $arr[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Token not found']);
    exit;
}

try {
    $key     = getenv('JWT_SECRET_KEY') ?: 'B4E_SECRET_KEY_123456';
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = (int)$decoded->data->id;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
    exit;
}

// 2. Lấy FCM token từ body
$data      = json_decode(file_get_contents('php://input'));
$fcm_token = isset($data->fcm_token) ? trim((string)$data->fcm_token) : '';

if (empty($fcm_token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu tham số: fcm_token']);
    exit;
}

// 3. Lưu vào DB
try {
    $db = (new Database())->connect();
    $stmt = $db->prepare('UPDATE users SET fcm_token = ? WHERE id = ?');
    $stmt->execute([$fcm_token, $user_id]);

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'FCM token đã được cập nhật.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
}
?>
