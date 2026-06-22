<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr = explode(" ", $authHeader);
$token = $arr[1] ?? '';
if (!$token) { http_response_code(401); echo json_encode(["error" => "Chưa đăng nhập"]); exit; }

try {
    $key = getenv('JWT_SECRET_KEY');
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Phiên đăng nhập hết hạn"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->borrow_id) || !isset($data->renew_days)) {
    http_response_code(400);
    echo json_encode(["error" => "Thiếu thông tin (borrow_id, renew_days)"]);
    exit;
}

$borrow_id = intval($data->borrow_id);
$renew_days = intval($data->renew_days);

if ($renew_days < 1 || $renew_days > 15) {
    http_response_code(400);
    echo json_encode(["error" => "Số ngày gia hạn không hợp lệ (1-15 ngày)"]);
    exit;
}

$db = (new Database())->connect();

// Kiểm tra trạng thái phiếu mượn
$stmt = $db->prepare("SELECT status, renew_status, renew_count FROM borrowings WHERE id = ?");
$stmt->execute([$borrow_id]);
$borrow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$borrow) {
    http_response_code(404);
    echo json_encode(["error" => "Không tìm thấy phiếu mượn"]);
    exit;
}

if ($borrow['status'] !== 'borrowed' && $borrow['status'] !== 'overdue') {
    http_response_code(400);
    echo json_encode(["error" => "Chỉ có thể gia hạn khi sách đang được mượn"]);
    exit;
}

if ($borrow['renew_status'] === 'pending') {
    http_response_code(400);
    echo json_encode(["error" => "Đã có yêu cầu gia hạn đang chờ duyệt"]);
    exit;
}

// Cập nhật trạng thái xin gia hạn
$update = $db->prepare("UPDATE borrowings SET renew_status = 'pending', renew_days = ? WHERE id = ?");
if ($update->execute([$renew_days, $borrow_id])) {
    http_response_code(200);
    echo json_encode(["message" => "Đã gửi yêu cầu gia hạn thành công"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Không thể gửi yêu cầu"]);
}
?>
