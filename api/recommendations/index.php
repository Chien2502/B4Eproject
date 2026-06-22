<?php
// api/recommendations/index.php
// GET /api/recommendations/index.php
// Auth: tùy chọn JWT — trả về sách gợi ý theo CBF (hỗ trợ chế độ Public cho khách vãng lai)
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

$limit  = max(1, min(30, (int)($_GET['limit'] ?? 8)));

try {
    $pdo = (new Database())->connect();

    // Lấy số lượt mượn lớn nhất của 1 cuốn sách trong kho để chuẩn hóa Popularity
    $stmtMax = $pdo->query("SELECT MAX(borrow_count) AS max_borrows FROM books WHERE is_deleted = 0");
    $maxRow = $stmtMax->fetch(PDO::FETCH_ASSOC);
    $maxBorrowCount = $maxRow ? (int)$maxRow['max_borrows'] : 1;
    if ($maxBorrowCount <= 0) {
        $maxBorrowCount = 1;
    }

    $userId = null;
    $hasHistory = false;
    $topCategoryNames = [];
    $affinity = []; // [category_id => weight]

    // Nếu có token gửi lên, tiến hành giải mã lấy thông tin người dùng
    if ($token) {
        try {
            $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET_KEY'), 'HS256'));
            $userId = (int)$decoded->data->id;
        } catch (Exception $jwtEx) {
            // Token không hợp lệ hoặc hết hạn -> Xem như khách vãng lai
            $userId = null;
        }
    }

    if ($userId !== null) {
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

        if ($hasHistory) {
            // Chuẩn hóa affinity thành trọng số weight 0.0 → 1.0
            $totalBorrows = array_sum(array_column($affinityRows, 'cnt'));
            $topCatIds = [];
            foreach ($affinityRows as $row) {
                $catId = (int)$row['category_id'];
                $affinity[$catId] = (float)$row['cnt'] / $totalBorrows;
                $topCatIds[] = $catId;
            }
            // Sắp xếp các thể loại yêu thích giảm dần
            arsort($affinity);

            // Lấy tên tối đa 3 thể loại yêu thích nhất hiển thị trên UI
            if (!empty($topCatIds)) {
                $placeholders = implode(',', array_fill(0, count($topCatIds), '?'));
                $stmtCats     = $pdo->prepare(
                    "SELECT name FROM categories WHERE id IN ($placeholders) ORDER BY FIELD(id, " . implode(',', $topCatIds) . ") LIMIT 3"
                );
                $stmtCats->execute(array_keys($affinity));
                $topCategoryNames = $stmtCats->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }

    // ── BƯỚC 2: Xây dựng biểu thức SQL Scoring trực tiếp ──
    $affinitySql = "0.0";
    if (!empty($affinity)) {
        $cases = [];
        foreach ($affinity as $catId => $weight) {
            $cases[] = "WHEN b.category_id = " . (int)$catId . " THEN " . number_format($weight, 4, '.', '');
        }
        $affinitySql = "CASE " . implode(' ', $cases) . " ELSE 0.0 END";
    }

    // Bộ lọc loại trừ những sách user hiện tại đã từng mượn
    $notBorrowedCond = "";
    $params = [
        ':max_borrows' => $maxBorrowCount,
        ':limit'       => $limit
    ];

    if ($userId !== null) {
        $notBorrowedCond = "AND b.id NOT IN (SELECT book_id FROM borrowings WHERE user_id = :uid)";
        $params[':uid'] = $userId;
    }

    // Câu lệnh SQL truy vấn và chấm điểm tối ưu
    $query = "
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
            c.id AS category_id,
            c.name AS category_name,
            -- Tính điểm tổng hợp ngay trong MySQL
            (
                $affinitySql + 
                IF(b.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY), 0.10, 0.0) + 
                (b.borrow_count / :max_borrows) * 0.20
            ) AS score
        FROM books b
        LEFT JOIN categories c ON c.id = b.category_id
        WHERE b.is_deleted = 0
          AND b.status = 'available'
          $notBorrowedCond
        ORDER BY score DESC, b.borrow_count DESC, b.created_at DESC
        LIMIT :limit
    ";

    $stmtBooks = $pdo->prepare($query);

    // Bind values (chú ý LIMIT cần bind PARAM_INT)
    foreach ($params as $paramKey => $paramVal) {
        $paramType = is_int($paramVal) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmtBooks->bindValue($paramKey, $paramVal, $paramType);
    }

    $stmtBooks->execute();
    $recommendations = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn hóa kiểu dữ liệu cho khớp với kết quả JSON cũ
    foreach ($recommendations as &$book) {
        $book['id']           = (int)$book['id'];
        $book['borrow_count'] = (int)$book['borrow_count'];
        $book['category_id']  = $book['category_id'] ? (int)$book['category_id'] : null;
        $book['score']        = round((float)$book['score'], 4);
    }
    unset($book);

    echo json_encode([
        'has_history'      => $hasHistory,
        'top_categories'   => $topCategoryNames,
        'recommendations'  => $recommendations,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
