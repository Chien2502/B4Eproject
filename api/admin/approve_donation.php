<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';
require_once '../config/middleware.php';
require_once '../config/notification_helper.php';

$admin_data = checkAdminAuth();

$data = json_decode(file_get_contents('php://input'));

if (empty($data->donation_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu ID quyên góp.']);
    exit;
}

$database = new Database();
$db = $database->connect();

try {
    $db->beginTransaction();

    // BƯỚC 1: Lấy thông tin donation (kèm user_id để gửi thông báo)
    $stmt_get = $db->prepare("SELECT * FROM donations WHERE id = ? AND status = 'pending'");
    $stmt_get->execute([$data->donation_id]);

    if ($stmt_get->rowCount() == 0) {
        throw new Exception("Yêu cầu quyên góp không tồn tại hoặc đã được xử lý trước đó.");
    }

    $donation = $stmt_get->fetch(PDO::FETCH_ASSOC);

    // BƯỚC 2: Thêm sách mới vào bảng Books
    $description = "Sách được quyên góp từ cộng đồng. Tình trạng: " . ($donation['book_condition'] ?? 'Tốt');
    $stmt_insert = $db->prepare(
        "INSERT INTO books (title, author, publisher, year, description, status, created_at)
         VALUES (?, ?, ?, ?, ?, 'available', NOW())"
    );
    $insert_success = $stmt_insert->execute([
        $donation['book_title'],
        $donation['book_author'],
        $donation['book_publisher'],
        $donation['book_year'],
        $description,
    ]);

    if (!$insert_success) {
        throw new Exception("Lỗi khi thêm sách vào kho.");
    }

    // BƯỚC 3: Cập nhật trạng thái donation -> approved
    $stmt_update = $db->prepare("UPDATE donations SET status = 'approved' WHERE id = ?");
    if (!$stmt_update->execute([$data->donation_id])) {
        throw new Exception("Lỗi khi cập nhật trạng thái quyên góp.");
    }

    // BƯỚC 4: Tạo thông báo cho người quyên góp
    createNotification(
        $db,
        (int)$donation['user_id'],
        'donation_approved',
        'Quyên góp được chấp nhận ❤️',
        'Cảm ơn bạn! Thư viện B4E đã tiếp nhận thành công cuốn "' . $donation['book_title'] . '". Sách của bạn đã được thêm vào kho và phục vụ cộng đồng!',
        (int)$donation['id']
    );

    $db->commit();

    http_response_code(200);
    echo json_encode(['message' => 'Thành công! Sách đã được thêm vào kho và hiển thị cho độc giả.']);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi xử lý: ' . $e->getMessage()]);
}
?>