<?php
// api/recommendations/index.php
// GET /api/recommendations/index.php
// Auth: yêu cầu JWT — trả về sách gợi ý theo CBF
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$headers     = apache_request_headers();
$authHeader  = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$arr         = explode(' ', $authHeader);
$token       = $arr[1] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Token not found']);
    exit;
}

$limit  = max(1, min(30, (int)($_GET['limit'] ?? 8)));

try {
    $decoded = JWT::decode($token, new Key('B4E_SECRET_KEY_123456', 'HS256'));
    $userId = (int)$decoded->data->id;

    $pdo = (new Database())->connect();

    // ── BƯỚC 1: Tính Category Affinity từ lịch sử mượn ───────────
    $stmtAffinity = $pdo->prepare("
        SELECT b.category_id, COUNT(*) AS cnt
        FROM borrowings br
        JOIN books b ON b.id = br.book_id
        WHERE br.user_id = :uid
          AND b.category_id IS NOT NULL
        GROUP BY b.category_id
    ");
    $stmtAffinity->execute([':uid' => $userId]);
    $affinityRows = $stmtAffinity->fetchAll(PDO::FETCH_ASSOC);

    $hasHistory = !empty($affinityRows);

    // Chuẩn hóa affinity thành weight 0.0 → 1.0
    $totalBorrows = array_sum(array_column($affinityRows, 'cnt'));
    $affinity     = []; // [category_id => weight]
    $topCatIds    = [];
    foreach ($affinityRows as $row) {
        $catId           = (int)$row['category_id'];
        $affinity[$catId] = (float)$row['cnt'] / $totalBorrows;
        $topCatIds[]     = $catId;
    }
    // Sort giảm dần để lấy top categories
    arsort($affinity);

    // ── BƯỚC 2: Lấy sách chưa từng mượn & còn available ─────────
    $stmtBooks = $pdo->prepare("
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
          AND b.status = 'available'
          AND b.id NOT IN (
              SELECT book_id FROM borrowings WHERE user_id = :uid
          )
    ");
    $stmtBooks->execute([':uid' => $userId]);
    $candidates = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);

    // ── BƯỚC 3: Scoring ───────────────────────────────────────────
    $maxBorrowCount = 1;
    foreach ($candidates as $book) {
        $maxBorrowCount = max($maxBorrowCount, (int)$book['borrow_count']);
    }

    $now = time();
    foreach ($candidates as &$book) {
        $catId        = $book['category_id'] ? (int)$book['category_id'] : 0;
        $affinityW    = $affinity[$catId] ?? 0.0;

        // Bonus sách mới (< 30 ngày)
        $createdTs    = strtotime($book['created_at']);
        $newBonus     = (($now - $createdTs) < 30 * 86400) ? 0.10 : 0.0;

        // Popularity bonus (0 → 0.2)
        $popularBonus = ((int)$book['borrow_count'] / $maxBorrowCount) * 0.20;

        $book['score']        = round($affinityW + $newBonus + $popularBonus, 4);
        $book['id']           = (int)$book['id'];
        $book['borrow_count'] = (int)$book['borrow_count'];
        $book['category_id']  = $catId ?: null;
    }
    unset($book);

    // Sort theo score giảm dần
    usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
    $recommendations = array_slice($candidates, 0, $limit);

    // ── Lấy tên top categories để hiển thị trên UI ───────────────
    $topCategoryNames = [];
    if (!empty($topCatIds)) {
        $placeholders = implode(',', array_fill(0, count($topCatIds), '?'));
        $stmtCats     = $pdo->prepare(
            "SELECT name FROM categories WHERE id IN ($placeholders) ORDER BY FIELD(id, " . implode(',', $topCatIds) . ") LIMIT 3"
        );
        $stmtCats->execute(array_keys($affinity)); // keys đã sort theo weight
        $topCategoryNames = $stmtCats->fetchAll(PDO::FETCH_COLUMN);
    }

    echo json_encode([
        'has_history'      => $hasHistory,
        'top_categories'   => $topCategoryNames,
        'recommendations'  => $recommendations,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
