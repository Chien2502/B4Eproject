<?php
// file: api/auth/login.php

// 1. THIẾT LẬP HEADER (Giống register.php)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS'); 
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

// 2. GỌI CÁC FILE CẦN THIẾT
// Gọi file kết nối CSDL
require_once '../config/database.php';
// GỌI FILE AUTOLOAD CỦA COMPOSER (RẤT QUAN TRỌNG)
// Đường dẫn này là đi lùi 2 cấp (từ auth/ ra api/, rồi ra gốc B4Eproject/)
require_once '../../vendor/autoload.php'; 

// Import thư viện JWT
use \Firebase\JWT\JWT;

// 3. XỬ LÝ OPTIONS REQUEST (Giống register.php)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Chỉ cho phép POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Vui lòng sử dụng POST.']);
    exit;
}

// 4. KHỞI TẠO KẾT NỐI
$database = new Database();
$db = $database->connect();

// 5. NHẬN DỮ LIỆU TỪ CLIENT
$data = json_decode(file_get_contents('php://input'));

// 6. KIỂM TRA DỮ LIỆU (VALIDATION)
if (empty($data->email) || empty($data->password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Vui lòng cung cấp đầy đủ email và password.']);
    exit;
}

// 7. XỬ LÝ LOGIC ĐĂNG NHẬP
try {
    // Tìm người dùng bằng email
    $query_find_user = 'SELECT * FROM users WHERE email = ?';
    $stmt_find_user = $db->prepare($query_find_user);
    $stmt_find_user->execute([$data->email]);

    // Kiểm tra xem có tìm thấy user không
    if ($stmt_find_user->rowCount() == 0) {
        // Không tìm thấy user
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Email hoặc mật khẩu không đúng.']);
        exit;
    }

    // Lấy thông tin user
    $user = $stmt_find_user->fetch(PDO::FETCH_ASSOC);

    // 8. XÁC MINH MẬT KHẨU
    // Đây là hàm "thần kỳ": nó so sánh $data->password (người dùng nhập) 
    // với $user['password_hash'] (lưu trong CSDL)
    if (password_verify($data->password, $user['password_hash'])) {
        
        // Mật khẩu đúng! Tạo Token
        
        // 9. CHUẨN BỊ THÔNG TIN ĐỂ TẠO TOKEN
        // QUAN TRỌNG: Đây là "chìa khóa bí mật" của bạn.
        // Nó phải được giữ BÍ MẬT TUYỆT ĐỐI.
        // Tốt nhất là lưu ở file config riêng, không nên viết cứng ở đây.
        $secret_key = "B4E_SECRET_KEY_123456"; // <-- ĐÂY LÀ CHÌA KHÓA BÍ MẬT
        
        $issuer_claim = "http://localhost/B4Eproject"; // Domain của bạn
        $audience_claim = "http://localhost"; // Audience
        $issuedat_claim = time(); // Thời gian token được tạo
        $notbefore_claim = $issuedat_claim; // Token có hiệu lực ngay
        $expire_claim = $issuedat_claim + 3600; // Token hết hạn sau 1 giờ (3600 giây)

        // Dữ liệu "payload" bạn muốn lưu vào Token
        // Đây là phần quan trọng nhất: chúng ta lưu user_id và role
        $payload = array(
            "iss" => $issuer_claim,
            "aud" => $audience_claim,
            "iat" => $issuedat_claim,
            "nbf" => $notbefore_claim,
            "exp" => $expire_claim,
            "data" => array(
                "id" => $user['id'],
                "username" => $user['username'],
                "email" => $user['email'],
                "role" => $user['role']
            )
        );

        // 10. TẠO TOKEN
        $jwt = JWT::encode($payload, $secret_key, 'HS256');

        // 11. TRẢ TOKEN VỀ CHO NGƯỜI DÙNG
        http_response_code(200); // OK
        echo json_encode(
            array(
                "message" => "Đăng nhập thành công.",
                "token" => $jwt,
                "user" => array( // Gửi kèm thông tin user để frontend chào
                    "username" => $user['username'],
                    "email" => $user['email'],
                    "role" => $user['role']
                )
            )
        );

    } else {
        // Mật khẩu sai
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Email hoặc mật khẩu không đúng.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi CSDL: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Bắt lỗi chung, ví dụ lỗi từ thư viện JWT
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}

?>