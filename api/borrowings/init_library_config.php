<?php
/**
 * Cập nhật library_config với thông tin thực tế của thư viện B4E:
 *   - 470 Trần Đại Nghĩa, Phường Ngũ Hành Sơn, TP Đà Nẵng
 *   - Ngân hàng ACB, STK 41608977, NGUYEN THANH CHIEN
 * 
 * Chạy 1 lần: http://localhost/B4Eproject/api/borrowings/init_library_config.php
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$db = (new Database())->connect();

// Tọa độ GPS: 470 Trần Đại Nghĩa, Ngũ Hành Sơn, Đà Nẵng
// (xác minh qua Google Maps)
$configs = [
    // ── Vị trí thư viện ──────────────────────────────────────────
    'library_name'    => 'Thư Viện B4E',
    'library_address' => '470 Trần Đại Nghĩa, Phường Ngũ Hành Sơn, TP Đà Nẵng',
    'library_lat'     => '16.0170',   // Latitude  - Ngũ Hành Sơn, Đà Nẵng
    'library_lng'     => '108.2420',  // Longitude - Ngũ Hành Sơn, Đà Nẵng
    'max_delivery_km' => '30',        // Phạm vi giao hàng tối đa (km)

    // ── Ngân hàng ACB ────────────────────────────────────────────
    'bank_name'    => 'ACB',
    'bank_account' => '41608977',
    'bank_owner'   => 'NGUYEN THANH CHIEN',

    // ── Timeout policy ───────────────────────────────────────────
    'vietqr_timeout_hours' => '24',  // Auto-cancel VietQR sau 24h
    'cod_timeout_hours'    => '48',  // Auto-cancel COD sau 48h
];

$stmt = $db->prepare(
    "INSERT INTO library_config (config_key, config_value)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)"
);

$updated = [];
foreach ($configs as $key => $value) {
    $stmt->execute([$key, $value]);
    $updated[] = "$key = $value";
}

echo json_encode([
    'success' => true,
    'message' => 'Đã cập nhật library_config thành công.',
    'updated' => $updated,
]);
?>
