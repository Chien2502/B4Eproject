<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

// 1. Nhận tham số phân trang
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12; // Mặc định 12 cuốn
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// 2. Xây dựng điều kiện lọc (WHERE clause)
$where_sql = " WHERE 1=1 AND b.is_deleted = 0";
$params = [];

// Lọc theo từ khóa
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where_sql .= " AND (b.title LIKE ? OR b.author LIKE ?)";
    array_push($params, $search, $search);
}

// Lọc theo thể loại
if (isset($_GET['category']) && $_GET['category'] != 'Tất cả' && !empty($_GET['category'])) {
    $where_sql .= " AND c.name = ?";
    array_push($params, $_GET['category']);
}

// Lọc theo trạng thái
if (isset($_GET['status']) && $_GET['status'] != 'all' && !empty($_GET['status'])) {
    $where_sql .= " AND b.status = ?";
    array_push($params, $_GET['status']);
}

// 3. Xử lý Sắp xếp
$order_sql = " ORDER BY b.id DESC"; 
if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'title_asc': $order_sql = " ORDER BY b.title ASC"; break;
        case 'title_desc': $order_sql = " ORDER BY b.title DESC"; break;
        case 'popular': $order_sql = " ORDER BY RAND()"; break;
    }
}
//Pagination
try {
    $count_query = "SELECT COUNT(*) as total 
                    FROM books b 
                    LEFT JOIN categories c ON b.category_id = c.id" . $where_sql;
    
    $stmt_count = $db->prepare($count_query);
    $stmt_count->execute($params);
    $total_books = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_books / $limit);

    $data_query = "SELECT b.*, c.name as category_name 
                   FROM books b 
                   LEFT JOIN categories c ON b.category_id = c.id" 
                   . $where_sql 
                   . $order_sql 
                   . " LIMIT $limit OFFSET $offset";
                   
    $stmt = $db->prepare($data_query);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'data' => $books,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total_books,
            'limit' => $limit
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi truy vấn: ' . $e->getMessage()]);
}
?>