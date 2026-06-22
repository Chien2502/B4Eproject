<?php
// File: C:\xampp\htdocs\B4Eproject\api\users\confirm_action.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../config/notification_helper.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

// Auth
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = explode(" ", $authHeader)[1] ?? '';
if (!$token) { http_response_code(401); exit; }

try {
    $key = getenv('JWT_SECRET_KEY');
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;

    $data = json_decode(file_get_contents('php://input'));
    $borrow_id = $data->borrow_id ?? null;
    $action = $data->action ?? null; // 'confirm_receipt' or 'confirm_shipping'

    if (!$borrow_id || !$action) {
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu thông tin borrow_id hoặc action.']);
        exit;
    }

    $db = (new Database())->connect();

    // 1. Lấy thông tin phiếu mượn để check quyền sở hữu
    $stmt = $db->prepare(
        "SELECT br.id, br.user_id, br.book_id, br.status, br.due_date, b.title AS book_title 
         FROM borrowings br
         JOIN books b ON b.id = br.book_id
         WHERE br.id = ? AND br.user_id = ?"
    );
    $stmt->execute([$borrow_id, $user_id]);
    $borrow = $stmt->fetch();

    if (!$borrow) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy phiếu mượn hợp lệ của bạn.']);
        exit;
    }

    $current_status = $borrow['status'];
    $book_title = $borrow['book_title'];
    $due_date = $borrow['due_date'];

    if ($action === 'confirm_receipt') {
        if ($current_status !== 'shipped') {
            http_response_code(400);
            echo json_encode(['error' => 'Chỉ có thể xác nhận đã nhận sách khi đơn hàng đang ở trạng thái vận chuyển.']);
            exit;
        }

        $db->beginTransaction();
        $db->prepare("UPDATE borrowings SET status = 'borrowed', approved_at = IFNULL(approved_at, NOW()) WHERE id = ?")
           ->execute([$borrow_id]);
        $db->prepare("UPDATE books SET status = 'borrowed' WHERE id = ?")
           ->execute([$borrow['book_id']]);
           
        createNotification($db, $user_id, 'borrow_approved',
            'Xác nhận đã nhận sách 📚',
            "Bạn đã xác nhận nhận cuốn \"{$book_title}\". Hạn trả: {$due_date}. Chúc bạn đọc vui!",
            $borrow_id);
            
        $db->commit();

        echo json_encode(['status' => 'success', 'message' => 'Xác nhận đã nhận sách thành công!']);
        exit;
    } 
    
    if ($action === 'confirm_shipping') {
        if ($current_status !== 'return_approved') {
            http_response_code(400);
            echo json_encode(['error' => 'Chỉ có thể xác nhận đang ship trả khi yêu cầu trả đã được phê duyệt.']);
            exit;
        }

        $db->beginTransaction();
        $db->prepare("UPDATE borrowings SET status = 'return_shipping' WHERE id = ?")
           ->execute([$borrow_id]);
           
        createNotification($db, $user_id, 'system',
            'Sách đang trên đường về thư viện 🔄',
            "Bạn đã xác nhận đang ship sách \"{$book_title}\" về thư viện. Chúng tôi sẽ cập nhật khi nhận được sách.",
            $borrow_id);
            
        $db->commit();

        echo json_encode(['status' => 'success', 'message' => 'Xác nhận đang gửi shipper thành công!']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Hành động không hợp lệ.']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
