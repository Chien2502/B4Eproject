<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS'); 
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');

// 2. GỌI CÁC FILE CẦN THIẾT
require_once '../config/database.php';
require_once '../../vendor/autoload.php'; 

// Import thư viện JWT
use \Firebase\JWT\JWT;

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

$database = new Database();
$db = $database->connect();

$data = json_decode(file_get_contents('php://input'));

if (empty($data->email) || empty($data->password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Vui lòng cung cấp đầy đủ email và password.']);
    exit;
}

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
    if (password_verify($data->password, $user['password_hash'])) {
        
        // Mật khẩu đúng! Tạo Token
        
        // 9. CHUẨN BỊ THÔNG TIN ĐỂ TẠO TOKEN
        $secret_key = "B4E_SECRET_KEY_123456"; // <-- ĐÂY LÀ CHÌA KHÓA BÍ MẬT
        
        $issuer_claim = "http://localhost/B4Eproject";
        $audience_claim = "http://localhost"; 
        $issuedat_claim = time(); 
        $notbefore_claim = $issuedat_claim; 
        $expire_claim = $issuedat_claim + 360000; // Token hết hạn sau 100 giờ

        // Dữ liệu "payload" lưu vào Token
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
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}

?>