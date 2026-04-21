<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit(); }

require_once '../config/database.php';
require_once '../config/middleware.php';
require_once '../config/notification_helper.php';

$admin_data = checkAdminAuth();

$data = json_decode(file_get_contents('php://input'));

if (empty($data->donation_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu ID.']);
    exit;
}

try {
    $db = (new Database())->connect();

    // Lấy thông tin donation trước khi cập nhật
    $stmt_get = $db->prepare("SELECT id, user_id, book_title FROM donations WHERE id = ? AND status = 'pending'");
    $stmt_get->execute([$data->donation_id]);
    $donation = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if (!$donation) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy yêu cầu hoặc đã được xử lý.']);
        exit;
    }

    // Cập nhật trạng thái -> rejected
    $stmt = $db->prepare("UPDATE donations SET status = 'rejected' WHERE id = ?");
    if ($stmt->execute([$data->donation_id])) {

        // Gửi thông báo cho người quyên góp
        createNotification(
            $db,
            (int)$donation['user_id'],
            'donation_rejected',
            'Quyên góp không được chấp nhận',
            'Rất tiếc, yêu cầu quyên góp cuốn "' . $donation['book_title'] . '" chưa phù hợp với tiêu chí của thư viện lúc này. Cảm ơn bạn đã quan tâm!',
            (int)$donation['id']
        );

        echo json_encode(['message' => 'Đã từ chối yêu cầu quyên góp.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi CSDL.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>