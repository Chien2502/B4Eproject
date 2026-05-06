<?php
// file: api/ai/test_gemini.php
// Truy cập thẳng trên trình duyệt: http://localhost/b4eproject/api/ai/test_gemini.php
// Kiểm tra kết nối Gemini API mà không cần ảnh

header('Content-Type: application/json; charset=utf-8');

define('GEMINI_API_KEY', 'AIzaSyBBnBdl21IHSm0_eVPvlX0wXQZqv_IXJPA');
define('GEMINI_MODEL',   'gemini-2.0-flash'); // Confirmed in ListModels
define('GEMINI_ENDPOINT',
    'https://generativelanguage.googleapis.com/v1beta/models/'
    . GEMINI_MODEL
    . ':generateContent?key='
    . GEMINI_API_KEY
);

// Gọi Gemini với prompt đơn giản (không cần ảnh)
$payload = [
    'contents' => [[
        'parts' => [
            ['text' => 'Trả lời bằng JSON: {"status": "ok", "message": "Gemini kết nối thành công"}'],
        ],
    ]],
    'generationConfig' => [
        'temperature'     => 0.0,
        'maxOutputTokens' => 50,
    ],
];

$ch = curl_init(GEMINI_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$response   = curl_exec($ch);
$curlErr    = curl_error($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErr) {
    echo json_encode([
        'test'   => 'FAILED',
        'reason' => 'cURL error: ' . $curlErr,
        'fix'    => 'XAMPP không thể kết nối internet. Kiểm tra firewall / proxy.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$data = json_decode($response, true);

if ($httpStatus !== 200) {
    echo json_encode([
        'test'       => 'FAILED',
        'http_code'  => $httpStatus,
        'gemini_error' => $data['error'] ?? $response,
        'hints'      => [
            '401 / API_KEY_INVALID' => 'API key sai hoặc chưa được kích hoạt. Vào aistudio.google.com để lấy key mới.',
            '403 / PERMISSION_DENIED' => 'API key chưa bật Gemini API. Vào console.cloud.google.com → Enable "Generative Language API".',
            '429 / RESOURCE_EXHAUSTED' => 'Hết quota miễn phí. Chờ reset hoặc nâng cấp.',
            '404 / NOT_FOUND' => 'Model không tồn tại. Thử đổi sang gemini-1.5-flash hoặc gemini-pro.',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '(no text)';

echo json_encode([
    'test'       => 'OK',
    'http_code'  => $httpStatus,
    'model_used' => GEMINI_MODEL,
    'response'   => $rawText,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
