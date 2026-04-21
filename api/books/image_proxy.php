<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$file = $_GET['file'] ?? '';

// Bảo mật: ngăn path traversal
$file = str_replace(['..', '\\'], '', $file);
$file = ltrim($file, '/');

// Đường dẫn tới thư mục uploads (từ books/ lên 1 cấp rồi vào uploads/)
$filePath = dirname(__DIR__) . '/uploads/' . $file;

if (empty($file) || !file_exists($filePath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found', 'path' => $filePath]);
    exit();
}

$mimeType = mime_content_type($filePath);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=86400');

readfile($filePath);
?>
