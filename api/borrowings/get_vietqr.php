<?php
/**
 * API: Tạo mã QR VietQR để user thanh toán phí ship
 * 
 * GET /borrowings/get_vietqr.php?borrowing_id=5
 * 
 * Dùng chuẩn VietQR (img.vietqr.io) — miễn phí, không cần đăng ký.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    $key     = getenv('JWT_SECRET_KEY') ?: 'B4E_SECRET_KEY_123456';
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $userId  = (int)$decoded->data->id;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Token không hợp lệ.']);
    exit;
}

$borrowingId = (int)($_GET['borrowing_id'] ?? 0);
if (!$borrowingId) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu borrowing_id.']);
    exit;
}

$db = (new Database())->connect();

// Lấy thông tin phiếu mượn
$stmt = $db->prepare(
    "SELECT br.id, br.user_id, br.shipping_fee, br.payment_method, br.payment_status,
            b.title AS book_title
     FROM borrowings br
     JOIN books b ON b.id = br.book_id
     WHERE br.id = ?"
);
$stmt->execute([$borrowingId]);
$borrow = $stmt->fetch();

if (!$borrow) {
    http_response_code(404);
    echo json_encode(['error' => 'Không tìm thấy phiếu mượn.']);
    exit;
}

// Chỉ chủ phiếu mượn mới được xem QR
if ((int)$borrow['user_id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['error' => 'Bạn không có quyền xem QR này.']);
    exit;
}

if ($borrow['payment_method'] !== 'vietqr') {
    http_response_code(400);
    echo json_encode(['error' => 'Phiếu mượn này không dùng phương thức VietQR.']);
    exit;
}

if ($borrow['payment_status'] === 'paid') {
    echo json_encode(['message' => 'Đã thanh toán.', 'paid' => true]);
    exit;
}

// Lấy thông tin ngân hàng từ library_config
$cfgStmt = $db->query(
    "SELECT config_key, config_value FROM library_config 
     WHERE config_key IN ('bank_name','bank_account','bank_owner')"
);
$cfg = [];
while ($row = $cfgStmt->fetch()) {
    $cfg[$row['config_key']] = $row['config_value'];
}

$bankCode   = $cfg['bank_name']    ?? 'Vietcombank';
$bankAcct   = $cfg['bank_account'] ?? '1234567890';
$amount     = (int)$borrow['shipping_fee'];
$addInfo    = 'B4E-SHIP-' . $borrowingId; // Nội dung chuyển khoản
$bookTitle  = mb_substr($borrow['book_title'], 0, 30); // Rút gọn tên sách

// ── Tạo URL VietQR ────────────────────────────────────────────────────────────
// Format: https://img.vietqr.io/image/{bank}-{account}-compact2.png?amount=X&addInfo=Y&accountName=Z
$qrUrl = sprintf(
    'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
    urlencode($bankCode),
    urlencode($bankAcct),
    $amount,
    urlencode($addInfo),
    urlencode($cfg['bank_owner'] ?? 'THU VIEN B4E')
);

echo json_encode([
    'qr_url'       => $qrUrl,
    'bank_name'    => $bankCode,
    'bank_account' => $bankAcct,
    'bank_owner'   => $cfg['bank_owner'] ?? 'THU VIEN B4E',
    'amount'       => $amount,
    'amount_fmt'   => number_format($amount, 0, ',', '.') . ' đ',
    'transfer_ref' => $addInfo,
    'note'         => "Vui lòng chuyển khoản đúng nội dung: {$addInfo}",
    'book_title'   => $borrow['book_title'],
    'paid'         => false,
]);
?>
