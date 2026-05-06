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
define('GEMINI_API_KEY', 'AIzaSyCKa-9nSD1YQR8PDNIhud5bCX-LC-NDAPM');
define('GEMINI_MODEL',   'gemini-2.5-flash-lite'); // Dùng bản nhỏ gọn Lite để tránh quá tải 503 và lỗi 404
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
QUAN TRỌNG MỨC ĐỘ CAO NHẤT: Nhiệm vụ ĐẦU TIÊN VÀ KIÊN QUYẾT của bạn là xác định xem ảnh này CÓ ĐÚNG CHUẨN LÀ BÌA CỦA MỘT CUỐN SÁCH ĐƯỢC XUẤT BẢN hay không.
Nếu ảnh rơi vào MỘT TRONG CÁC TRƯỜNG HỢP SAU, bạn BẮT BUỘC phải dừng lại và CHỈ trả về đúng chuỗi JSON lỗi `{"error": "not_a_book"}` (không giải thích gì thêm):
1. Ảnh là tài liệu, văn bản, giấy tờ, tờ rơi, hồ sơ, hóa đơn, bài kiểm tra, mẫu tài liệu (document templates).
2. Ảnh phong cảnh, động vật, người, đồ vật linh tinh, màn hình máy tính.
3. Ảnh không phải là bìa ngoài của một cuốn sách hoàn chỉnh.

CHỈ KHI ảnh CHẮC CHẮN 100% là bìa của một cuốn sách thực sự, hãy phân tích và đọc thật kỹ mọi chữ, logo, số trên bìa (kể cả thông tin tái bản, năm xuất bản, logo nhà xuất bản) và trả về thông tin theo đúng format JSON sau:
{
  "title": "tên sách (string)",
  "author": "tên tác giả hoặc nhiều tác giả, phân cách bằng dấu phẩy (string)",
  "publisher": "tên nhà xuất bản (nếu thấy chữ NXB, Nhà xuất bản, hoặc nhận diện được logo của NXB) (string hoặc null)",
  "publish_year": năm xuất bản hoặc năm tái bản (nếu thấy con số năm xuất bản trên bìa) (number hoặc null),
  "description": "mô tả ngắn nội dung sách từ 2-4 câu. CHÚ Ý: Nếu trên bìa có ghi 'tái bản lần thứ...' hoặc các thông tin đặc biệt khác, hãy ghi chú vào phần đầu của mô tả này (string)",
  "category": "thể loại sách ví dụ: Văn học, Khoa học, Kinh tế, Tâm lý, Lịch sử, Thiếu nhi, Giáo khoa... (string)",
  "isbn": "số ISBN nếu nhìn thấy trên bìa (string hoặc null)"
}
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
        'temperature'      => 0.1,
        'maxOutputTokens'  => 512,
        'responseMimeType' => 'application/json',
    ],
];

$maxRetries = 2;
$attempt = 0;
$success = false;
$geminiResponse = '';
$httpStatus = 0;
$curlErr = '';

while ($attempt <= $maxRetries && !$success) {
    $attempt++;
    
    $ch = curl_init(GEMINI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 45, // Tăng timeout do có thể phải chờ sleep
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    
    $geminiResponse = curl_exec($ch);
    $curlErr        = curl_error($ch);
    $httpStatus     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        break; // Lỗi mạng từ phía server thì thoát vòng lặp luôn
    }

    if ($httpStatus === 200) {
        $success = true;
    } elseif ($httpStatus === 429 && $attempt <= $maxRetries) {
        // Xử lý giới hạn tần suất (Rate Limit: 15 req/min của Free Tier)
        $errData = json_decode($geminiResponse, true);
        $errMsg = $errData['error']['message'] ?? '';
        
        $waitTime = 3; // Mặc định chờ 3 giây nếu không phân tích được số
        // Tìm chữ "Please retry in X.Xs." từ Gemini API
        if (preg_match('/retry in ([0-9.]+)s/i', $errMsg, $matches)) {
            $waitTime = (int)ceil((float)$matches[1]) + 1; // Cộng dư 1 giây cho an toàn
        }
        
        sleep($waitTime); // Tạm dừng PHP chạy để chờ Gemini reset quota
    } else {
        break; // Các lỗi khác (400, 403, 500) thì không thử lại
    }
}

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Không thể kết nối Gemini API: ' . $curlErr]);
    exit;
}

if ($httpStatus !== 200) {
    $errData   = json_decode($geminiResponse, true);
    $geminiMsg = $errData['error']['message']
                 ?? $errData['error']['status']
                 ?? substr($geminiResponse, 0, 300); // Trả tối đa 300 ký tự đầu của response thô
    http_response_code(502);
    echo json_encode([
        'error'  => 'Gemini API lỗi [HTTP ' . $httpStatus . ']: ' . $geminiMsg,
    ]);
    exit;
}

// ── 5. Parse kết quả ──────────────────────────────────────────────────────────
$geminiData = json_decode($geminiResponse, true);
$rawText    = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Loại bỏ các đoạn text thừa nếu có, chỉ lấy từ dấu { đầu tiên đến dấu } cuối cùng
$rawText = trim($rawText);
if (($start = strpos($rawText, '{')) !== false && ($end = strrpos($rawText, '}')) !== false) {
    $rawText = substr($rawText, $start, $end - $start + 1);
}

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
