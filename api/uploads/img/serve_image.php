<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$file = $_GET['file'] ?? '';

// Bảo mật: ngăn path traversal attack
$file = str_replace(['..', '\\'], '', $file);
$file = ltrim($file, '/');

// __DIR__ = thư mục chứa serve_image.php = api/uploads/
// Nên đường dẫn thực tế = api/uploads/img/Book/xxx.webp
$filePath = __DIR__ . '/' . $file;

if (empty($file) || !file_exists($filePath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found: ' . $file]);
    exit();
}

$mimeType = mime_content_type($filePath);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: public, max-age=86400');

readfile($filePath);
?>
