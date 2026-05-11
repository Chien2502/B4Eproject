<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../config/middleware.php';

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

try {
    $admin_data = checkAdminAuth();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => $e->getMessage()]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->borrow_id) || !isset($data->action)) {
    http_response_code(400);
    echo json_encode(["error" => "Thiếu thông tin (borrow_id, action)"]);
    exit;
}

$borrow_id = intval($data->borrow_id);
$action = $data->action; // 'approve' hoặc 'reject'

$db = (new Database())->connect();

// Lấy thông tin phiếu mượn hiện tại
$stmt = $db->prepare("SELECT renew_status, renew_days, due_date FROM borrowings WHERE id = ?");
$stmt->execute([$borrow_id]);
$borrow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$borrow || $borrow['renew_status'] !== 'pending') {
    http_response_code(400);
    echo json_encode(["error" => "Không tìm thấy yêu cầu gia hạn hợp lệ"]);
    exit;
}

if ($action === 'approve') {
    $renew_days = intval($borrow['renew_days']);
    
    // Cập nhật due_date bằng cách cộng thêm số ngày
    $update = $db->prepare("
        UPDATE borrowings 
        SET due_date = DATE_ADD(due_date, INTERVAL ? DAY),
            renew_status = 'approved',
            renew_count = renew_count + 1
        WHERE id = ?
    ");
    
    if ($update->execute([$renew_days, $borrow_id])) {
        http_response_code(200);
        echo json_encode(["message" => "Đã duyệt gia hạn thành công"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Lỗi cập nhật database"]);
    }
} else if ($action === 'reject') {
    $update = $db->prepare("UPDATE borrowings SET renew_status = 'rejected' WHERE id = ?");
    if ($update->execute([$borrow_id])) {
        http_response_code(200);
        echo json_encode(["message" => "Đã từ chối gia hạn"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Lỗi cập nhật database"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Hành động không hợp lệ"]);
}
?>
