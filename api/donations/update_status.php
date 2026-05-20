<?php
// file: api/donations/update_status.php
// POST: Admin cập nhật trạng thái quyên góp
// Body JSON: { "donation_id": 3, "status": "approved" }
// Status hợp lệ: approved | in_transit | received | processed | rejected

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

// 1. Chỉ admin
checkAdminAuth();

// 2. Validate input
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$donation_id = $data['donation_id'] ?? $_POST['donation_id'] ?? null;
$status = $data['status'] ?? $_POST['status'] ?? null;

if (empty($donation_id) || empty($status)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu donation_id hoặc status.']);
    exit;
}

$allowed = ['approved', 'in_transit', 'received', 'processed', 'rejected'];
if (!in_array($status, $allowed)) {
    http_response_code(400);
    echo json_encode(['error' => 'Status không hợp lệ. Các giá trị được phép: ' . implode(', ', $allowed)]);
    exit;
}

try {
    $db = (new Database())->connect();

    // 3. Lấy thông tin quyên góp
    $stmt = $db->prepare(
        "SELECT * FROM donations WHERE id = ?"
    );
    $stmt->execute([(int)$donation_id]);
    $donation = $stmt->fetch();

    if (!$donation) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy bản ghi quyên góp.']);
        exit;
    }

    $user_id     = (int)$donation['user_id'];
    $book_title  = $donation['book_title'];
    $donation_id = (int)$donation['id'];
    $new_status  = $status;

    // 4. Cập nhật trạng thái + timestamp
    switch ($new_status) {
        case 'approved':
            $db->prepare("UPDATE donations SET status = ?, approved_at = NOW() WHERE id = ?")
               ->execute([$new_status, $donation_id]);
            break;
        case 'received':
            $db->prepare("UPDATE donations SET status = ?, received_at = NOW() WHERE id = ?")
               ->execute([$new_status, $donation_id]);
            break;
        case 'processed':
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE donations SET status = ?, processed_at = NOW() WHERE id = ?")
                   ->execute([$new_status, $donation_id]);

                // Auto find category_id. We can try to look up a category matching donation_type
                $cat_stmt = $db->prepare("SELECT id FROM categories WHERE name LIKE ? LIMIT 1");
                $cat_stmt->execute(['%' . ($donation['donation_type'] ?? '') . '%']);
                $cat = $cat_stmt->fetch();
                $category_id = $cat ? (int)$cat['id'] : null;

                $book_publisher = $donation['book_publisher'];
                $book_year = $donation['book_year'];
                $image_url = $donation['image_url'];
                $book_condition = $donation['book_condition'];
                $description = "Sách quyên góp từ bạn đọc. Tình trạng: " . ($book_condition ?: 'Mới');

                $insert_book = $db->prepare("INSERT INTO books (title, author, publisher, year, category_id, description, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'available')");
                $insert_book->execute([
                    $book_title,
                    $donation['book_author'],
                    $book_publisher,
                    $book_year,
                    $category_id,
                    $description,
                    $image_url
                ]);

                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
        default:
            $db->prepare("UPDATE donations SET status = ? WHERE id = ?")
               ->execute([$new_status, $donation_id]);
    }

    // 5. Thông báo cho user
    switch ($new_status) {
        case 'approved':
            $msg = $donation['pickup_type'] === 'user_ship'
                ? "Cảm ơn bạn! Yêu cầu quyên góp \"{$book_title}\" đã được chấp nhận. Vui lòng gửi sách về địa chỉ thư viện."
                : "Cảm ơn bạn! Yêu cầu quyên góp \"{$book_title}\" đã được chấp nhận. Vui lòng mang sách đến thư viện.";
            createNotification($db, $user_id, 'donation_approved',
                'Quyên góp được chấp nhận ❤️', $msg, $donation_id);
            break;

        case 'in_transit':
            createNotification($db, $user_id, 'donation_approved',
                'Sách đang trên đường đến thư viện 🚚',
                "Thư viện đã nhận được thông tin vận chuyển cuốn \"{$book_title}\". Chúng tôi sẽ xác nhận khi nhận được sách.",
                $donation_id);
            break;

        case 'received':
            createNotification($db, $user_id, 'donation_approved',
                'Thư viện đã nhận được sách 📦',
                "Thư viện đã nhận cuốn \"{$book_title}\" thành công. Sách đang được kiểm tra và xử lý.",
                $donation_id);
            break;

        case 'processed':
            createNotification($db, $user_id, 'donation_approved',
                'Quyên góp hoàn tất 🎉',
                "Cuốn \"{$book_title}\" đã được nhập vào kho thư viện và sẵn sàng phục vụ bạn đọc. Cảm ơn đóng góp quý giá của bạn!",
                $donation_id);
            break;

        case 'rejected':
            createNotification($db, $user_id, 'donation_rejected',
                'Quyên góp không được chấp nhận',
                "Rất tiếc, yêu cầu quyên góp cuốn \"{$book_title}\" chưa phù hợp với tiêu chí thư viện lúc này.",
                $donation_id);
            break;
    }

    http_response_code(200);
    echo json_encode([
        'status'  => 'success',
        'message' => "Đã cập nhật trạng thái quyên góp thành '{$new_status}'.",
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
}
?>
