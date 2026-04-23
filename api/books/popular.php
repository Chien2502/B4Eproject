<?php
// api/books/popular.php
// GET /api/books/popular.php?limit=10
// Public — không cần JWT
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));

try {
    $pdo = (new Database())->connect();

    $stmt = $pdo->prepare("
        SELECT
            b.id,
            b.title,
            b.author,
            b.publisher,
            b.year,
            b.description,
            b.image_url,
            b.status,
            b.borrow_count,
            b.created_at,
            c.id   AS category_id,
            c.name AS category_name
        FROM books b
        LEFT JOIN categories c ON c.id = b.category_id
        WHERE b.is_deleted = 0
        ORDER BY b.borrow_count DESC, b.created_at DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn hóa kiểu dữ liệu
    foreach ($books as &$book) {
        $book['id']           = (int)$book['id'];
        $book['borrow_count'] = (int)$book['borrow_count'];
        $book['category_id']  = $book['category_id'] ? (int)$book['category_id'] : null;
    }

    echo json_encode($books);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
