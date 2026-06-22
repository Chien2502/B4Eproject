<?php
/**
 * API: Tạo yêu cầu mượn sách
 * 
 * POST /borrowings/create.php
 * Body: {
 *   "book_id": 1,
 *   "borrow_days": 14,
 *   "delivery_type": "pickup" | "delivery",
 *   "delivery_address": "...",   // chỉ khi delivery
 *   "delivery_lat": 21.03,       // chỉ khi delivery
 *   "delivery_lng": 105.84,      // chỉ khi delivery
 *   "payment_method": "cod" | "vietqr"  // chỉ khi delivery
 * }
 * 
 * Trạng thái ban đầu: 'pending_approval' (Admin phải duyệt trước)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../config/notification_helper.php';
require_once '../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

// 1. Xác thực Token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = explode(' ', $authHeader)[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Vui lòng đăng nhập để mượn sách.']);
    exit;
}

try {
    $key     = getenv('JWT_SECRET_KEY');
    $decoded = JWT::decode($token, new Key($key, 'HS256'));
    $user_id = $decoded->data->id;

    // 2. Nhận dữ liệu
    $data = json_decode(file_get_contents('php://input'));

    if (empty($data->book_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Thiếu ID sách.']);
        exit;
    }

    $delivery_type = $data->delivery_type ?? 'pickup';
    if (!in_array($delivery_type, ['pickup', 'delivery'])) {
        http_response_code(400);
        echo json_encode(['error' => 'delivery_type phải là pickup hoặc delivery.']);
        exit;
    }

    // Validate dữ liệu delivery
    $delivery_address   = null;
    $delivery_lat       = null;
    $delivery_lng       = null;
    $shipping_fee       = 0;
    $payment_method     = null;
    $payment_status     = 'not_required';
    $distance_km        = null;

    if ($delivery_type === 'delivery') {
        if (empty($data->delivery_address) || empty($data->delivery_lat) || empty($data->delivery_lng)) {
            http_response_code(400);
            echo json_encode(['error' => 'Mượn ship cần có delivery_address, delivery_lat, delivery_lng.']);
            exit;
        }
        if (empty($data->payment_method) || !in_array($data->payment_method, ['cod', 'vietqr'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Mượn ship cần chọn payment_method: cod hoặc vietqr.']);
            exit;
        }

        $delivery_address = $data->delivery_address;
        $delivery_lat     = (float)$data->delivery_lat;
        $delivery_lng     = (float)$data->delivery_lng;
        $payment_method   = $data->payment_method;
        $payment_status   = ($payment_method === 'vietqr') ? 'pending' : 'not_required';
    }

    $db = (new Database())->connect();

    // 3. Kiểm tra sách available
    $stmt = $db->prepare("SELECT title, status FROM books WHERE id = ?");
    $stmt->execute([$data->book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$book || $book['status'] !== 'available') {
        http_response_code(409);
        echo json_encode(['error' => 'Sách này hiện không có sẵn để mượn.']);
        exit;
    }

    // 4. Tính phí ship (nếu delivery)
    if ($delivery_type === 'delivery') {
        // Lấy tọa độ thư viện
        $cfgStmt = $db->query("SELECT config_key, config_value FROM library_config WHERE config_key IN ('library_lat','library_lng','max_delivery_km')");
        $cfg = [];
        while ($row = $cfgStmt->fetch(PDO::FETCH_ASSOC)) {
            $cfg[$row['config_key']] = $row['config_value'];
        }

        $libLat      = (float)($cfg['library_lat'] ?? 21.028511);
        $libLng      = (float)($cfg['library_lng'] ?? 105.804817);
        $maxKm       = (float)($cfg['max_delivery_km'] ?? 35);

        // Haversine
        $R    = 6371;
        $dLat = deg2rad($delivery_lat - $libLat);
        $dLng = deg2rad($delivery_lng - $libLng);
        $a    = sin($dLat/2)**2 + cos(deg2rad($libLat)) * cos(deg2rad($delivery_lat)) * sin($dLng/2)**2;
        $distance_km = round($R * 2 * asin(sqrt($a)) * 1.4, 2); // ×1.4 hệ số đường bộ

        if ($distance_km > $maxKm) {
            http_response_code(422);
            echo json_encode(['error' => "Địa chỉ của bạn ({$distance_km} km) vượt quá phạm vi giao hàng ({$maxKm} km)."]);
            exit;
        }

        // Tra bảng phí
        $feeStmt = $db->prepare("SELECT fee FROM shipping_fee_config WHERE min_km <= ? AND max_km > ? AND is_active = 1 ORDER BY min_km ASC LIMIT 1");
        $feeStmt->execute([$distance_km, $distance_km]);
        $feeRow = $feeStmt->fetch(PDO::FETCH_ASSOC);
        $shipping_fee = $feeRow ? (int)$feeRow['fee'] : 60000;
    }

    // 5. Giao dịch
    $db->beginTransaction();

    try {
        $borrow_days = isset($data->borrow_days) ? (int)$data->borrow_days : 14;
        if ($borrow_days < 1 || $borrow_days > 15) $borrow_days = 14;

        // 5.1: Tạo phiếu mượn — trạng thái 'pending_approval'
        $sql = "INSERT INTO borrowings 
                  (user_id, book_id, delivery_type, delivery_address, delivery_distance_km,
                   shipping_fee, payment_method, payment_status, borrow_date, due_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'pending_approval')";
        $db->prepare($sql)->execute([
            $user_id, $data->book_id, $delivery_type, $delivery_address, $distance_km,
            $shipping_fee, $payment_method, $payment_status, $borrow_days
        ]);
        $borrow_id = (int)$db->lastInsertId();

        // 5.2: Đổi status sách sang 'busy' nếu thanh toán qua VietQR (giao dịch chuyển tiền đang chờ duyệt)
        if ($delivery_type === 'delivery' && $payment_method === 'vietqr') {
            $db->prepare("UPDATE books SET status = 'busy' WHERE id = ?")
               ->execute([$data->book_id]);
        }


        // 5.3: Gửi thông báo xác nhận yêu cầu đã gửi cho user
        $deliveryNote = $delivery_type === 'delivery'
            ? " (Ship tận nơi - phí: " . number_format($shipping_fee, 0, ',', '.') . "đ)"
            : " (Nhận trực tiếp tại thư viện)";

        createNotification(
            $db,
            (int)$user_id,
            'borrow_approved',
            'Yêu cầu mượn sách đã gửi ⏳',
            'Yêu cầu mượn cuốn "' . $book['title'] . '"' . $deliveryNote . ' đang chờ Admin duyệt.',
            $borrow_id
        );

        $db->commit();

        // 5.4: Gửi FCM Realtime cho admin biết có yêu cầu mới
        try {
            $factory   = (new Factory())->withServiceAccount(__DIR__.'/../firebase_credentials.json');
            $messaging = $factory->createMessaging();
            $message   = CloudMessage::withTarget('topic', 'admin_alerts')
                ->withData([
                    'action'     => 'new_borrow_request',
                    'borrow_id'  => (string)$borrow_id,
                    'book_title' => $book['title'],
                ]);
            $messaging->send($message);
        } catch (\Exception $fcmEx) {
            error_log("FCM Admin Alert Error: " . $fcmEx->getMessage());
        }

        $response = [
            'message'      => 'Yêu cầu mượn sách đã được gửi! Admin sẽ duyệt sớm.',
            'borrow_id'    => $borrow_id,
            'delivery_type' => $delivery_type,
        ];

        if ($delivery_type === 'delivery') {
            $response['shipping_fee']  = $shipping_fee;
            $response['distance_km']   = $distance_km;
            $response['payment_method'] = $payment_method;
            if ($payment_method === 'vietqr') {
                $response['next_step'] = 'Vui lòng thanh toán phí ship qua VietQR để Admin tiến hành chuẩn bị sách.';
            } else {
                $response['next_step'] = 'Thanh toán COD khi nhận sách. Admin sẽ liên hệ xác nhận.';
            }
        }

        http_response_code(201);
        echo json_encode($response);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>