<?php
// file: api/borrowings/create.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../config/notification_helper.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

// 1. Xác thực Token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr   = explode(" ", $authHeader);
$token = $arr[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Vui lòng đăng nhập để mượn sách.']);
    exit;
}

try {
    $key     = "B4E_SECRET_KEY_123456";
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;

    // 2. Nhận dữ liệu
    $data = json_decode(file_get_contents('php://input'));
    if (empty($data->book_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu ID sách.']);
        exit;
    }

    $db = (new Database())->connect();

    // 3. Kiểm tra sách có available không + lấy tên sách để ghi thông báo
    $stmt = $db->prepare("SELECT title, status FROM books WHERE id = ?");
    $stmt->execute([$data->book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$book || $book['status'] !== 'available') {
        http_response_code(409);
        echo json_encode(['error' => 'Sách này hiện không có sẵn để mượn.']);
        exit;
    }

    // 4. Giao dịch
    $db->beginTransaction();

    try {
        // B4.1: Tạo phiếu mượn (hạn trả 14 ngày)
        $sql_borrow = "INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status) 
                       VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'borrowed')";
        $stmt_borrow = $db->prepare($sql_borrow);
        $stmt_borrow->execute([$user_id, $data->book_id]);
        $borrow_id = (int)$db->lastInsertId();

        // B4.2: Cập nhật trạng thái sách -> borrowed
        $db->prepare("UPDATE books SET status = 'borrowed' WHERE id = ?")
           ->execute([$data->book_id]);

        // B4.3: Tính ngày hết hạn để ghi vào thông báo
        $due_date = date('d/m/Y', strtotime('+14 days'));

        // B4.4: Gửi thông báo xác nhận cho user
        createNotification(
            $db,
            (int)$user_id,
            'borrow_approved',
            'Mượn sách thành công ✅',
            'Bạn đã mượn cuốn "' . $book['title'] . '" thành công. Hạn trả: ' . $due_date . '. Chúc bạn đọc vui!',
            $borrow_id
        );

        $db->commit();

        http_response_code(201);
        echo json_encode(['message' => 'Mượn sách thành công!']);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>