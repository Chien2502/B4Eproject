<?php
/**
 * API: Tính phí vận chuyển dựa theo khoảng cách (Haversine formula)
 * 
 * POST /borrowings/calculate_shipping.php
 * Body: { "address": "...", "lat": 21.03, "lng": 105.84 }
 * 
 * Không yêu cầu API key bên ngoài — dùng công thức Haversine (độ chính xác ~85%)
 * để tính khoảng cách đường chim bay, sau đó nhân 1.4 (hệ số đường bộ VN trung bình).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Xác thực JWT
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = explode(' ', $authHeader)[1] ?? '';
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Vui lòng đăng nhập.']);
    exit;
}

try {
    $key = getenv('JWT_SECRET_KEY') ?: 'B4E_SECRET_KEY_123456';
    JWT::decode($token, new Key($key, 'HS256'));
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Phải có ít nhất lat/lng hoặc address text
$userLat = isset($data['lat'])  ? (float)$data['lat']  : null;
$userLng = isset($data['lng'])  ? (float)$data['lng']  : null;

if ($userLat === null || $userLng === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Cần cung cấp lat và lng của địa chỉ giao hàng.']);
    exit;
}

$db = (new Database())->connect();

// Lấy cấu hình thư viện
$stmt = $db->query("SELECT config_key, config_value FROM library_config WHERE config_key IN ('library_lat','library_lng','max_delivery_km')");
$configs = [];
while ($row = $stmt->fetch()) {
    $configs[$row['config_key']] = $row['config_value'];
}

$libLat     = (float)($configs['library_lat']     ?? 16.0170);
$libLng     = (float)($configs['library_lng']     ?? 108.2420);
$maxDelivery = (float)($configs['max_delivery_km'] ?? 30);

// ── Công thức Haversine ──────────────────────────────────────────────────────
function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R  = 6371; // Bán kính Trái Đất (km)
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $R * 2 * asin(sqrt($a));
}

// Khoảng cách đường chim bay × 1.4 (hệ số đường bộ nội thành Việt Nam)
$straightKm = haversineKm($libLat, $libLng, $userLat, $userLng);
$roadKm     = round($straightKm * 1.4, 2);

// Kiểm tra vượt ngưỡng tối đa
if ($roadKm > $maxDelivery) {
    echo json_encode([
        'available'    => false,
        'distance_km'  => $roadKm,
        'message'      => "Địa chỉ của bạn cách thư viện khoảng {$roadKm} km, vượt quá phạm vi giao hàng ({$maxDelivery} km). Vui lòng chọn hình thức mượn trực tiếp.",
    ]);
    exit;
}

// Tra bảng phí ship
$stmt = $db->prepare(
    "SELECT fee FROM shipping_fee_config 
     WHERE min_km <= ? AND max_km > ? AND is_active = 1 
     ORDER BY min_km ASC LIMIT 1"
);
$stmt->execute([$roadKm, $roadKm]);
$row = $stmt->fetch();
$fee = $row ? (int)$row['fee'] : 60000; // fallback max fee

echo json_encode([
    'available'        => true,
    'distance_km'      => $roadKm,
    'shipping_fee'     => $fee,
    'shipping_fee_fmt' => number_format($fee, 0, ',', '.') . ' đ',
    'note'             => "Khoảng cách ước tính (đường bộ): {$roadKm} km",
]);
?>
