<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

// --- PHẦN NÀY CẦN KIỂM TRA SESSION ADMIN (PHP SESSION) ---
// Vì admin gọi API này từ trang admin (cùng domain), ta có thể dùng check session
// Thay vì check JWT như app client.
session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin' ||  $_SESSION['role'] !== 'super-admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'));

if (empty($data->donation_id)) {
    http_response_code(400); echo json_encode(['error' => 'Thiếu ID.']); exit;
}

try {
    $db = (new Database())->connect();
    
    $stmt = $db->prepare("UPDATE donations SET status = 'rejected' WHERE id = ?");
    
    if ($stmt->execute([$data->donation_id])) {
        echo json_encode(['message' => 'Đã từ chối yêu cầu quyên góp.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi CSDL.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>