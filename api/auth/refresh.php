<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS'); 
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../config/database.php';
require_once '../../vendor/autoload.php'; 

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Vui lòng sử dụng POST.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'));

if (empty($data->refresh_token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Vui lòng cung cấp refresh token.']);
    exit;
}

$secret_key = getenv('JWT_SECRET_KEY') ?: getenv('JWT_SECRET_KEY') ?: 'B4E_SECRET_KEY_123456';

try {
    // Giải mã refresh token
    $decoded = JWT::decode($data->refresh_token, new Key($secret_key, 'HS256'));
    
    // Kiểm tra type
    if (!isset($decoded->type) || $decoded->type !== 'refresh') {
        http_response_code(401);
        echo json_encode(['error' => 'Token không hợp lệ.']);
        exit;
    }

    $user_id = $decoded->data->id;

    $database = new Database();
    $db = $database->connect();

    // Lấy thông tin mới nhất của user
    $query = 'SELECT * FROM users WHERE id = ?';
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);

    if ($stmt->rowCount() == 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Người dùng không tồn tại.']);
        exit;
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Tạo ACCESS TOKEN mới
    $issuer_claim = "http://localhost/B4Eproject";
    $audience_claim = "http://localhost"; 
    $issuedat_claim = time(); 
    $notbefore_claim = $issuedat_claim; 
    $expire_claim = $issuedat_claim + 3600; // Access token 1 giờ

    $payload = array(
        "iss" => $issuer_claim,
        "aud" => $audience_claim,
        "iat" => $issuedat_claim,
        "nbf" => $notbefore_claim,
        "exp" => $expire_claim,
        "data" => array(
            "id" => $user['id'],
            "username" => $user['username'],
            "email" => $user['email'],
            "role" => $user['role']
        )
    );

    $jwt = JWT::encode($payload, $secret_key, 'HS256');

    // Tạo REFRESH TOKEN mới (xoay vòng)
    $refresh_expire_claim = $issuedat_claim + 2592000; // 30 ngày
    $refresh_payload = array(
        "iss" => $issuer_claim,
        "aud" => $audience_claim,
        "iat" => $issuedat_claim,
        "nbf" => $notbefore_claim,
        "exp" => $refresh_expire_claim,
        "data" => array(
            "id" => $user['id'],
            "username" => $user['username']
        ),
        "type" => "refresh"
    );
    $new_refresh_token = JWT::encode($refresh_payload, $secret_key, 'HS256');

    http_response_code(200);
    echo json_encode(array(
        "message" => "Token được làm mới thành công.",
        "token" => $jwt,
        "refresh_token" => $new_refresh_token
    ));

} catch (\Firebase\JWT\ExpiredException $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Refresh token đã hết hạn. Vui lòng đăng nhập lại.']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Refresh token không hợp lệ: ' . $e->getMessage()]);
}
?>
