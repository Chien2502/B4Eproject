<?php
// file: api/books/read.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

// 1. Xây dựng câu truy vấn cơ bản
// Chúng ta JOIN với bảng categories để lấy tên thể loại thay vì chỉ lấy ID
$query = "SELECT b.*, c.name as category_name 
          FROM books b 
          LEFT JOIN categories c ON b.category_id = c.id 
          WHERE 1=1"; // Mẹo: WHERE 1=1 giúp dễ dàng nối thêm các điều kiện AND phía sau

$params = [];

// 2. Xử lý bộ lọc từ URL (Query Params)

// Lọc theo từ khóa (Search)
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $query .= " AND (b.title LIKE ? OR b.author LIKE ?)";
    array_push($params, $search, $search);
}

// Lọc theo thể loại (Category) - Nhận tên thể loại
if (isset($_GET['category']) && $_GET['category'] != 'Tất cả' && !empty($_GET['category'])) {
    $query .= " AND c.name = ?";
    array_push($params, $_GET['category']);
}

// Lọc theo trạng thái (Status)
if (isset($_GET['status']) && $_GET['status'] != 'all' && !empty($_GET['status'])) {
    $query .= " AND b.status = ?";
    array_push($params, $_GET['status']);
}

// 3. Xử lý Sắp xếp (Sort)
if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'title_asc': $query .= " ORDER BY b.title ASC"; break;
        case 'title_desc': $query .= " ORDER BY b.title DESC"; break;
        case 'popular': $query .= " ORDER BY RAND()"; break; // Giả lập phổ biến
        default: $query .= " ORDER BY b.id DESC"; // Mặc định mới nhất
    }
} else {
    $query .= " ORDER BY b.id DESC";
}

// 4. Thực thi và Trả về kết quả
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($books);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi truy vấn: ' . $e->getMessage()]);
}
?>