<?php
// file: api/books/related.php
// Trả về sách liên quan (cùng thể loại) hoặc fallback sách ngẫu nhiên
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

if (!isset($_GET['book_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu book_id.']);
    exit;
}

$bookId = (int)$_GET['book_id'];
$limit  = isset($_GET['limit']) ? min((int)$_GET['limit'], 20) : 6;

$database = new Database();
$db = $database->connect();

try {
    // 1. Lấy category_id của sách hiện tại
    $stmt = $db->prepare("SELECT category_id FROM books WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$bookId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy sách.']);
        exit;
    }

    $categoryId = $current['category_id'];
    $books = [];

    // 2. Nếu sách có thể loại → lấy sách cùng thể loại (loại trừ chính nó)
    if ($categoryId) {
        $query = "SELECT b.id, b.title, b.author, b.image_url, b.status, 
                         c.name as category_name
                  FROM books b
                  LEFT JOIN categories c ON b.category_id = c.id
                  WHERE b.category_id = ? AND b.id != ? AND b.is_deleted = 0
                  ORDER BY RAND()
                  LIMIT ?";
        $stmt = $db->prepare($query);
        $stmt->bindValue(1, $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(2, $bookId,     PDO::PARAM_INT);
        $stmt->bindValue(3, $limit,      PDO::PARAM_INT);
        $stmt->execute();
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Nếu không đủ sách cùng thể loại → bổ sung bằng sách ngẫu nhiên
    $remaining = $limit - count($books);
    if ($remaining > 0) {
        $excludeIds = array_merge([$bookId], array_column($books, 'id'));
        $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));

        $query = "SELECT b.id, b.title, b.author, b.image_url, b.status,
                         c.name as category_name
                  FROM books b
                  LEFT JOIN categories c ON b.category_id = c.id
                  WHERE b.id NOT IN ($placeholders) AND b.is_deleted = 0
                  ORDER BY RAND()
                  LIMIT ?";
        $stmt = $db->prepare($query);

        $i = 1;
        foreach ($excludeIds as $eid) {
            $stmt->bindValue($i++, $eid, PDO::PARAM_INT);
        }
        $stmt->bindValue($i, $remaining, PDO::PARAM_INT);
        $stmt->execute();
        $extra = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $books = array_merge($books, $extra);
    }

    // 4. Thêm cờ cho biết nguồn dữ liệu
    $type = ($categoryId && count($books) > 0) ? 'category' : 'random';

    echo json_encode([
        'type'  => $type,
        'data'  => $books,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi server: ' . $e->getMessage()]);
}
?>
