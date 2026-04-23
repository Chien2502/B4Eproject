<?php
// file: api/donations/update_status.php
// POST: Admin duyệt hoặc từ chối quyên góp
// Body JSON: { "donation_id": 3, "status": "approved" }
// Status hợp lệ: approved, rejected
// → Tự động tạo thông báo cho user
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../config/notification_helper.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// 1. Xác thực JWT + kiểm tra quyền Admin
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr   = explode(' ', $authHeader);
$token = $arr[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Vui lòng đăng nhập.']);
    exit;
}

try {
    $key     = 'B4E_SECRET_KEY_123456';
    $decoded = JWT::decode($token, new Key($key, 'HS256'));

    $role = $decoded->data->role ?? 'user';
    if (!in_array($role, ['admin', 'super-admin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Bạn không có quyền thực hiện thao tác này.']);
        exit;
    }

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

    $db = (new Database())->connect();

    // 2. Lấy thông tin quyên góp và tên user
    $stmt_get = $db->prepare(
        "SELECT d.id, d.user_id, d.book_title, d.status AS old_status
         FROM donations d
         WHERE d.id = ?"
    );
    $stmt_get->execute([(int)$data->donation_id]);
    $donation = $stmt_get->fetch();

    if (!$donation) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy bản ghi quyên góp.']);
        exit;
    }

    // 3. Cập nhật trạng thái
    $stmt_update = $db->prepare(
        "UPDATE donations SET status = ? WHERE id = ?"
    );
    $stmt_update->execute([$data->status, (int)$data->donation_id]);

    // 4. Tạo thông báo
    $user_id    = (int)$donation['user_id'];
    $book_title = $donation['book_title'];
    $donation_id = (int)$donation['id'];

    if ($data->status === 'approved') {
        createNotification($db, $user_id, 'donation_approved',
            'Quyên góp được chấp nhận ❤️',
            "Cảm ơn bạn rất nhiều! Thư viện B4E đã tiếp nhận cuốn \"{$book_title}\" của bạn thành công.",
            $donation_id
        );
    } else {
        createNotification($db, $user_id, 'donation_rejected',
            'Quyên góp không được chấp nhận',
            "Rất tiếc, yêu cầu quyên góp cuốn \"{$book_title}\" chưa phù hợp với tiêu chí của thư viện lúc này.",
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
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>
