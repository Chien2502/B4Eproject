<?php
// file: api/auth/register.php

// 1. THIẾT LẬP HEADER
// (Báo cho client biết chúng ta sẽ trả về dữ liệu JSON)
header('Content-Type: application/json');
// (Cho phép nguồn gốc của bạn - 127.0.0.1:5500 - hoặc dùng * cho mọi nguồn)
header('Access-Control-Allow-Origin: *'); 

// ==========================================================
// *** PHẦN SỬA LỖI CORS PREFLIGHT ***
// ==========================================================
// Kiểm tra xem request có phải là một 'OPTIONS' request (hỏi xin phép) không
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    
    // Báo cho trình duyệt những phương thức nào được phép
    // QUAN TRỌNG: Thêm 'POST' vào đây!
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS'); 
    
    // Báo cho trình duyệt những header nào được phép khi gửi
    header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods, Authorization, X-Requested-With');
    
    // Trả về 200 OK và thoát. Không chạy phần logic CSDL bên dưới.
    http_response_code(200);
    exit;
}


// 1. (tiếp) Chỉ cho phép POST cho logic chính
// Bây giờ, nếu request KHÔNG PHẢI LÀ POST (và cũng không phải OPTIONS đã thoát ở trên), 
// thì từ chối.
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Allow: POST'); // Báo cho client biết phương thức đúng là POST
    http_response_code(405); // 405 Method Not Allowed
    echo json_encode(['error' => 'Method Not Allowed. Vui lòng sử dụng POST.']);
    exit;
}

// 2. GỌI CÁC FILE CẦN THIẾT
require_once '../config/database.php';

// 3. KHỞI TẠO KẾT NỐI
$database = new Database();
$db = $database->connect();
// 4. NHẬN DỮ LIỆU TỪ CLIENT
// Đọc dữ liệu JSON thô từ body của request
$data = json_decode(file_get_contents('php://input'));

// 5. KIỂM TRA DỮ LIỆU (VALIDATION)
// Đảm bảo các trường bắt buộc không bị trống
if (
    empty($data->username) ||
    empty($data->email) ||
    empty($data->password)
) {
    // Nếu thiếu dữ liệu, trả về lỗi 400 (Bad Request)
    http_response_code(400);
    echo json_encode(['error' => 'Vui lòng cung cấp đầy đủ username, email và password.']);
    exit; // Dừng thực thi
}

// Kiểm tra định dạng email
if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Định dạng email không hợp lệ.']);
    exit;
}

// 6. KIỂM TRA TÀI KHOẢN TỒN TẠI (QUAN TRỌNG)
try {
    // Câu lệnh 1: Kiểm tra username
    $query_check_user = 'SELECT id FROM users WHERE username = ?';
    $stmt_check_user = $db->prepare($query_check_user);
    $stmt_check_user->execute([$data->username]);
    if ($stmt_check_user->fetch()) {
        http_response_code(409); // 409 Conflict (Xung đột)
        echo json_encode(['error' => 'Username này đã tồn tại.']);
        exit;
    }

    // Câu lệnh 2: Kiểm tra email
    $query_check_email = 'SELECT id FROM users WHERE email = ?';
    $stmt_check_email = $db->prepare($query_check_email);
    $stmt_check_email->execute([$data->email]);
    if ($stmt_check_email->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email này đã được sử dụng.']);
        exit;
    }

    // 7. MÃ HÓA MẬT KHẨU
    // Sử dụng thuật toán băm mật khẩu mạnh nhất của PHP
    $password_hash = password_hash($data->password, PASSWORD_BCRYPT);

    // 8. LƯU VÀO CSDL
    // Tạo câu lệnh SQL với Prepared Statements (chống SQL Injection)
    $query_insert = 'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)';
    
    // Chuẩn bị câu lệnh
    $stmt_insert = $db->prepare($query_insert);

    // Thực thi câu lệnh, gán các giá trị vào dấu ?
    if ($stmt_insert->execute([$data->username, $data->email, $password_hash])) {
        // Nếu thành công, trả về 201 (Created)
        http_response_code(201);
        echo json_encode(['message' => 'Đăng ký tài khoản thành công.']);
    } else {
        // Nếu lỗi không xác định
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Đã có lỗi xảy ra khi đăng ký.']);
    }

} catch (PDOException $e) {
    // Bắt lỗi CSDL
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi CSDL: ' . $e->getMessage()]);
}

?>