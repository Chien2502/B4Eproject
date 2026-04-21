<?php
// file: api/donations/update_status.php
// POST: Admin duyệt hoặc từ chối quyên góp + tạo thông báo cho user
// Body JSON: { "donation_id": 3, "status": "approved" }
// status hợp lệ: approved | rejected
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../config/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 1. Chỉ admin mới được gọi API này
$admin = checkAdminAuth();

// 2. Validate input
$data = json_decode(file_get_contents('php://input'));

if (empty($data->donation_id) || empty($data->status)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu tham số: donation_id hoặc status.']);
    exit;
}

if (!in_array($data->status, ['approved', 'rejected'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Status chỉ được là: approved hoặc rejected.']);
    exit;
}

try {
    $db = (new Database())->connect();

    // 3. Lấy thông tin quyên góp
    $stmt_get = $db->prepare(
        "SELECT id, user_id, book_title FROM donations WHERE id = ?"
    );
    $stmt_get->execute([(int)$data->donation_id]);
    $donation = $stmt_get->fetch();

    if (!$donation) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy bản ghi quyên góp.']);
        exit;
    }

    // 4. Cập nhật trạng thái
    $db->prepare("UPDATE donations SET status = ? WHERE id = ?")
       ->execute([$data->status, (int)$data->donation_id]);

    // 5. Tạo thông báo cho user
    $user_id     = (int)$donation['user_id'];
    $book_title  = $donation['book_title'];
    $donation_id = (int)$donation['id'];

    if ($data->status === 'approved') {
        createNotification($db, $user_id, 'donation_approved',
            'Quyên góp được chấp nhận ❤️',
            "Cảm ơn bạn! Thư viện B4E đã tiếp nhận thành công cuốn \"{$book_title}\" của bạn.",
            $donation_id
        );
    } else {
        createNotification($db, $user_id, 'donation_rejected',
            'Quyên góp không được chấp nhận',
            "Rất tiếc, yêu cầu quyên góp cuốn \"{$book_title}\" chưa phù hợp với tiêu chí thư viện lúc này.",
            $donation_id
        );
    }

    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'message' => "Đã cập nhật trạng thái quyên góp thành '{$data->status}'.",
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
}
?>
