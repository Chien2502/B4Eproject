<?php
// file: api/ai/list_models.php
// Truy cập: http://localhost/b4eproject/api/ai/list_models.php
// Liệt kê TẤT CẢ model Gemini mà API key này có thể dùng

header('Content-Type: application/json; charset=utf-8');

define('GEMINI_API_KEY', 'AIzaSyBBnBdl21IHSm0_eVPvlX0wXQZqv_IXJPA');

// Google AI Studio dùng v1beta cho ListModels
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . GEMINI_API_KEY;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$response   = curl_exec($ch);
$curlErr    = curl_error($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlErr) {
    echo json_encode(['error' => 'cURL: ' . $curlErr], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($httpStatus !== 200) {
    echo json_encode([
        'http_code' => $httpStatus,
        'body'      => json_decode($response, true),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$data   = json_decode($response, true);
$models = $data['models'] ?? [];

// Lọc chỉ lấy model hỗ trợ generateContent
$generateContentModels = array_values(array_filter($models, function ($m) {
    $methods = $m['supportedGenerationMethods'] ?? [];
    return in_array('generateContent', $methods);
}));

$output = array_map(fn($m) => [
    'name'    => $m['name'],
    'display' => $m['displayName'] ?? '',
    'methods' => $m['supportedGenerationMethods'] ?? [],
], $generateContentModels);

echo json_encode([
    'total_models_supporting_generateContent' => count($output),
    'models' => $output,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
