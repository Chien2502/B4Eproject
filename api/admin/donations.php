<?php
// 1. Cấu hình CORS và JSON Header (BẮT BUỘC ĐẶT TRÊN CÙNG)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Xử lý Preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


try {
    require_once "../config/database.php";
    require_once "../config/middleware.php";

    $admin_data = checkAdminAuth();

    // 3. Kết nối CSDL
    $database = new Database();
    $conn = $database->connect();

    // 4. Truy vấn dữ liệu — trả về tất cả để admin quản lý toàn bộ workflow
    // Optional filter: ?status=pending|approved|in_transit|received|processed|rejected
    $allowed_statuses = ['pending', 'approved', 'in_transit', 'received', 'processed', 'rejected'];
    $status_filter = $_GET['status'] ?? 'all';

    if ($status_filter !== 'all' && in_array($status_filter, $allowed_statuses)) {
        $query = "SELECT d.*, u.username, u.email 
                  FROM donations d 
                  LEFT JOIN users u ON d.user_id = u.id 
                  WHERE d.status = :status
                  ORDER BY d.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute([':status' => $status_filter]);
    } else {
        $query = "SELECT d.*, u.username, u.email 
                  FROM donations d 
                  LEFT JOIN users u ON d.user_id = u.id 
                  ORDER BY 
                    CASE d.status 
                      WHEN 'pending'    THEN 0
                      WHEN 'in_transit' THEN 1
                      WHEN 'received'   THEN 2
                      ELSE 3
                    END ASC,
                    d.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
    }
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Trả về kết quả JSON
    echo json_encode($donations);

} catch (Exception $e) {
    // Nếu có lỗi, trả về JSON lỗi (HTTP 500)
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>