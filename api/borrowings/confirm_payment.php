<?php
/**
 * API: Admin xác nhận đã nhận thanh toán VietQR
 * 
 * POST /borrowings/confirm_payment.php
 * Body: { "borrowing_id": 5, "payment_ref": "B4E-SHIP-5" }
 * Chỉ Admin mới được gọi.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../config/notification_helper.php';

// Chỉ Admin
checkAdminAuth();

$data = json_decode(file_get_contents('php://input'));
if (empty($data->borrowing_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu borrowing_id.']);
    exit;
}

$db = (new Database())->connect();

// Lấy thông tin phiếu mượn
$stmt = $db->prepare(
    "SELECT br.*, b.title AS book_title
     FROM borrowings br
     JOIN books b ON b.id = br.book_id
     WHERE br.id = ?"
);
$stmt->execute([(int)$data->borrowing_id]);
$borrow = $stmt->fetch();

if (!$borrow) {
    http_response_code(404);
    echo json_encode(['error' => 'Không tìm thấy phiếu mượn.']);
    exit;
}

if ($borrow['payment_status'] === 'paid') {
    http_response_code(409);
    echo json_encode(['error' => 'Phiếu mượn này đã được xác nhận thanh toán trước đó.']);
    exit;
}

if ($borrow['payment_method'] !== 'vietqr') {
    http_response_code(400);
    echo json_encode(['error' => 'Phiếu mượn này không dùng VietQR (COD không cần xác nhận trước).']);
    exit;
}

$db->beginTransaction();
try {
    $paymentRef = $data->payment_ref ?? ('B4E-SHIP-' . $borrow['id']);

    // Xác nhận thanh toán + chuyển sang 'preparing'
    $db->prepare(
        "UPDATE borrowings SET
           payment_status        = 'paid',
           payment_ref           = ?,
           payment_confirmed_at  = NOW(),
           status                = 'preparing'
         WHERE id = ?"
    )->execute([$paymentRef, (int)$data->borrowing_id]);

    // Gửi thông báo cho user
    createNotification(
        $db,
        (int)$borrow['user_id'],
        'borrow_approved',
        'Thanh toán xác nhận thành công 💳',
        'Thư viện đã nhận được phí ship cho cuốn "' . $borrow['book_title'] . '". Sách của bạn đang được chuẩn bị!',
        (int)$borrow['id']
    );

    $db->commit();
    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'message' => 'Đã xác nhận thanh toán. Trạng thái chuyển sang "Đang chuẩn bị".',
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
}
?>
