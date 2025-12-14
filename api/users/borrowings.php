<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr = explode(" ", $authHeader);
$token = $arr[1] ?? '';
if (!$token) { http_response_code(401); exit; }

try {
    $key = "B4E_SECRET_KEY_123456";
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;

    $db = (new Database())->connect();
    
    // JOIN bảng borrowings với books để lấy tên sách và ảnh
    $query = "SELECT br.*, b.title, b.image_url, b.author 
              FROM borrowings br
              JOIN books b ON br.book_id = b.id
              WHERE br.user_id = ?
              ORDER BY br.borrow_date DESC";
              
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
}
?>