<?php
// file: api/auth/upload_avatar.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Xử lý OPTIONS request (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Chỉ cho phép POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// ── 1. Xác thực JWT ──────────────────────────────────────────────────────────
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr = explode(" ", $authHeader);
if (count($arr) < 2) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không được cung cấp.']);
    exit;
}
$token = $arr[1];

$secret_key = getenv('JWT_SECRET_KEY');

try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    $user_id = $decoded->data->id;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ: ' . $e->getMessage()]);
    exit;
}

// ── 2. Validate file upload ──────────────────────────────────────────────────
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Không nhận được file ảnh.']);
    exit;
}

$file = $_FILES['avatar'];

// Kiểm tra mime type
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Chỉ chấp nhận file JPG, PNG hoặc WebP.']);
    exit;
}

// Giới hạn kích thước: 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File ảnh quá lớn (tối đa 5MB).']);
    exit;
}

// ── 3. Lưu file ──────────────────────────────────────────────────────────────
$uploadDir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Tạo tên file duy nhất: avatar_{userId}_{timestamp}.{ext}
$ext = match ($mimeType) {
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg',
};
$fileName = "avatar_{$user_id}_" . time() . ".{$ext}";
$destPath = $uploadDir . $fileName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Không thể lưu file. Vui lòng thử lại.']);
    exit;
}

// ── 4. Cập nhật DB — lưu đường dẫn avatar ────────────────────────────────────
$database = new Database();
$db = $database->connect();

// Lấy avatar cũ để xóa file
$stmtOld = $db->prepare('SELECT avatar FROM users WHERE id = ?');
$stmtOld->execute([$user_id]);
$oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);
$oldAvatar = $oldRow['avatar'] ?? null;

// Cập nhật avatar mới
$avatarPath = "avatars/{$fileName}"; // Đường dẫn tương đối từ uploads/
$stmt = $db->prepare('UPDATE users SET avatar = ? WHERE id = ?');

if ($stmt->execute([$avatarPath, $user_id])) {
    // Xóa file avatar cũ (nếu có)
    if ($oldAvatar && $oldAvatar !== $avatarPath) {
        $oldFile = __DIR__ . '/../uploads/' . $oldAvatar;
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }

    http_response_code(200);
    echo json_encode([
        'message' => 'Cập nhật ảnh đại diện thành công.',
        'avatar'  => $avatarPath,
    ]);
} else {
    // Rollback: xóa file vừa upload nếu DB fail
    if (file_exists($destPath)) unlink($destPath);
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi máy chủ khi cập nhật.']);
}
?>
