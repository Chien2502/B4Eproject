<?php
// file: api/ai/analyze_cover.php
// POST multipart/form-data với field 'image' (file ảnh bìa sách)
// Server gọi Gemini Vision API và trả về thông tin sách được trích xuất.
// Yêu cầu xác thực JWT để tránh lạm dụng.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không được hỗ trợ.']);
    exit;
}

// ── 0. Cấu hình ──────────────────────────────────────────────────────────────
// !! Thay YOUR_GEMINI_API_KEY bằng key thật lấy từ https://aistudio.google.com
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY');
define('GEMINI_MODEL',   'gemini-1.5-flash-latest');
define('GEMINI_ENDPOINT',
    'https://generativelanguage.googleapis.com/v1beta/models/'
    . GEMINI_MODEL
    . ':generateContent?key='
    . GEMINI_API_KEY
);

// ── 1. Xác thực JWT (chỉ Admin được dùng) ────────────────────────────────────
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$parts      = explode(' ', $authHeader);
$token      = $parts[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Vui lòng đăng nhập.']);
    exit;
}

try {
    $decoded = JWT::decode($token, new Key('B4E_SECRET_KEY_123456', 'HS256'));
    // Cho phép tất cả user đã đăng nhập (admin + user thường)
    // Tính năng dùng cho cả Admin (thêm sách) và User (quyên góp sách)
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ.']);
    exit;
}

// ── 2. Validate file ảnh ─────────────────────────────────────────────────────
if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Vui lòng gửi file ảnh qua field "image".']);
    exit;
}

$file     = $_FILES['image'];
$mimeType = mime_content_type($file['tmp_name']);
$allowed  = ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mimeType, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Chỉ hỗ trợ ảnh JPEG, PNG hoặc WebP.']);
    exit;
}

// Giới hạn 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Ảnh quá lớn. Tối đa 5MB.']);
    exit;
}

// ── 3. Encode ảnh sang base64 ─────────────────────────────────────────────────
$imageData   = file_get_contents($file['tmp_name']);
$base64Image = base64_encode($imageData);

// ── 4. Gọi Gemini Vision API ──────────────────────────────────────────────────
$prompt = <<<PROMPT
Đây là ảnh bìa sách. Hãy phân tích và trả về thông tin theo đúng format JSON sau.
Không thêm markdown, không thêm text bên ngoài JSON.

{
  "title": "tên sách (string)",
  "author": "tên tác giả hoặc nhiều tác giả, phân cách bằng dấu phẩy (string)",
  "description": "mô tả ngắn nội dung sách từ 2-4 câu (string)",
  "category": "thể loại sách ví dụ: Văn học, Khoa học, Kinh tế, Tâm lý, Lịch sử, Thiếu nhi, ... (string)",
  "publish_year": năm xuất bản nếu thấy trên bìa (number hoặc null),
  "isbn": "số ISBN nếu nhìn thấy trên bìa (string hoặc null)"
}

Nếu ảnh không phải bìa sách, trả về chính xác:
{"error": "not_a_book"}
PROMPT;

$payload = [
    'contents' => [[
        'parts' => [
            ['text' => $prompt],
            [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data'      => $base64Image,
                ],
            ],
        ],
    ]],
    'generationConfig' => [
        'temperature'     => 0.1,   // Low temperature → more deterministic
        'maxOutputTokens' => 512,
    ],
];

$ch = curl_init(GEMINI_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$geminiResponse = curl_exec($ch);
$curlErr        = curl_error($ch);
$httpStatus     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Không thể kết nối Gemini API: ' . $curlErr]);
    exit;
}

if ($httpStatus !== 200) {
    $errData = json_decode($geminiResponse, true);
    http_response_code(502);
    echo json_encode([
        'error'  => 'Gemini API lỗi.',
        'detail' => $errData['error']['message'] ?? $geminiResponse,
    ]);
    exit;
}

// ── 5. Parse kết quả ──────────────────────────────────────────────────────────
$geminiData = json_decode($geminiResponse, true);
$rawText    = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Loại bỏ markdown code fences nếu Gemini vẫn thêm vào
$rawText = preg_replace('/```json\s*/i', '', $rawText);
$rawText = preg_replace('/```\s*/i', '', $rawText);
$rawText = trim($rawText);

$bookInfo = json_decode($rawText, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(422);
    echo json_encode([
        'error'   => 'Gemini trả về kết quả không đúng định dạng.',
        'raw'     => $rawText,
    ]);
    exit;
}

// Trả về kết quả cho Flutter
http_response_code(200);
echo json_encode(array_merge(['source' => 'gemini_vision'], $bookInfo));
?>
