<?php
// api/recommendations/index.php
// GET /api/recommendations/index.php
// Auth: tùy chọn JWT — Hybrid Recommendation Engine (Content-Based + Collaborative Filtering)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$headers     = function_exists('apache_request_headers') ? apache_request_headers() : [];
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

    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Token not found']);
        exit;
    }

    try {
        $decoded = JWT::decode($token, new Key(getenv('JWT_SECRET_KEY'), 'HS256'));
        $userId = (int)$decoded->data->id;
    } catch (Exception $jwtEx) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Invalid or expired token']);
        exit;
    }

    $hasHistory = false;
    $topCategoryNames = [];
    $topAuthors = [];
    $affinity = [];       // [category_id => weight]
    $authorAffinity = []; // [author_name => weight]
    $neighbors = [];      // Láng giềng gần nhất cho Collaborative Filtering
    $coCategories = [];   // Thể loại tương quan / liên đới (Co-read Categories)

    if ($userId !== null) {
        // ── BƯỚC 1A: Tính Category Affinity từ lịch sử mượn ───────────
        $stmtCatAffinity = $pdo->prepare("
            SELECT b.category_id, COUNT(*) AS cnt
            FROM borrowings br
            JOIN books b ON b.id = br.book_id
            WHERE br.user_id = :uid
              AND b.category_id IS NOT NULL
            GROUP BY b.category_id
        ");
        $stmtCatAffinity->execute([':uid' => $userId]);
        $catAffinityRows = $stmtCatAffinity->fetchAll(PDO::FETCH_ASSOC);

        $hasHistory = !empty($catAffinityRows);

        if ($hasHistory) {
            // Chuẩn hóa category affinity thành trọng số weight 0.0 → 1.0
            $totalBorrows = array_sum(array_column($catAffinityRows, 'cnt'));
            $topCatIds = [];
            foreach ($catAffinityRows as $row) {
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

            // ── BƯỚC 1B: Tính Author Affinity từ lịch sử mượn ───────────
            $stmtAuthorAffinity = $pdo->prepare("
                SELECT b.author, COUNT(*) AS cnt
                FROM borrowings br
                JOIN books b ON b.id = br.book_id
                WHERE br.user_id = :uid
                  AND b.author IS NOT NULL AND b.author != ''
                GROUP BY b.author
            ");
            $stmtAuthorAffinity->execute([':uid' => $userId]);
            $authorAffinityRows = $stmtAuthorAffinity->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($authorAffinityRows)) {
                $totalAuthorBorrows = array_sum(array_column($authorAffinityRows, 'cnt'));
                foreach ($authorAffinityRows as $row) {
                    $author = $row['author'];
                    $authorAffinity[$author] = (float)$row['cnt'] / $totalAuthorBorrows;
                }
                // Sắp xếp tác giả yêu thích giảm dần và lấy top 3
                arsort($authorAffinity);
                $topAuthors = array_slice(array_keys($authorAffinity), 0, 3);
            }

            // ── BƯỚC 1D: Tìm các thể loại liên đới (Co-read Categories) từ hành vi mượn chung của toàn thư viện ──
            if (!empty($topCatIds)) {
                $catPlaceholders = implode(',', array_fill(0, count($topCatIds), '?'));
                $stmtCoCats = $pdo->prepare("
                    SELECT b2.category_id, COUNT(DISTINCT br2.user_id) AS co_count
                    FROM borrowings br1
                    JOIN books b1 ON b1.id = br1.book_id
                    JOIN borrowings br2 ON br2.user_id = br1.user_id
                    JOIN books b2 ON b2.id = br2.book_id
                    WHERE b1.category_id IN ($catPlaceholders)
                      AND b2.category_id NOT IN ($catPlaceholders)
                      AND b2.category_id IS NOT NULL
                      AND br2.user_id != ?
                    GROUP BY b2.category_id
                    ORDER BY co_count DESC
                    LIMIT 3
                ");
                $bindParams = array_merge($topCatIds, $topCatIds, [$userId]);
                $stmtCoCats->execute($bindParams);
                $coCategories = $stmtCoCats->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        // ── BƯỚC 1C: Lấy danh sách láng giềng gần nhất cho CF ──
        $cfNeighborsLimit = max(1, min(100, (int)(getenv('CF_NEIGHBORS_LIMIT') ?: 30)));
        $stmtNeighbors = $pdo->prepare("
            SELECT user_id_2, similarity 
            FROM user_similarities 
            WHERE user_id_1 = :uid 
            ORDER BY similarity DESC 
            LIMIT :limit
        ");
        $stmtNeighbors->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmtNeighbors->bindValue(':limit', $cfNeighborsLimit, PDO::PARAM_INT);
        $stmtNeighbors->execute();
        $neighbors = $stmtNeighbors->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── BƯỚC 2A: Xây dựng biểu thức SQL Category Scoring ──
    $affinitySql = "0.0";
    if (!empty($affinity)) {
        $cases = [];
        foreach ($affinity as $catId => $weight) {
            $cases[] = "WHEN b.category_id = " . (int)$catId . " THEN " . number_format($weight, 4, '.', '');
        }
        $affinitySql = "CASE " . implode(' ', $cases) . " ELSE 0.0 END";
    }

    // ── BƯỚC 2B: Xây dựng biểu thức SQL Author Scoring ──
    $authorAffinitySql = "0.0";
    if (!empty($authorAffinity)) {
        $cases = [];
        foreach ($authorAffinity as $author => $weight) {
            $escapedAuthor = $pdo->quote($author);
            $cases[] = "WHEN b.author = $escapedAuthor THEN " . number_format($weight, 4, '.', '');
        }
        $authorAffinitySql = "CASE " . implode(' ', $cases) . " ELSE 0.0 END";
    }

    // ── BƯỚC 2C: Xây dựng biểu thức SQL Collaborative Filtering Scoring ──
    $cfScoreSql = "0.0";
    $neighborJoin = "";
    $groupBy = "";
    if (!empty($neighbors)) {
        $neighborIds = array_column($neighbors, 'user_id_2');
        $sumSim = array_sum(array_column($neighbors, 'similarity'));

        if ($sumSim > 0) {
            $cfCases = [];
            foreach ($neighbors as $n) {
                $nId = (int)$n['user_id_2'];
                $sim = (float)$n['similarity'];
                // Sử dụng MAX để tránh bị cộng dồn trùng lặp điểm nếu láng giềng mượn 1 cuốn sách nhiều lần
                $cfCases[] = "MAX(CASE WHEN br_neighbor.user_id = $nId THEN " . number_format($sim, 4, '.', '') . " ELSE 0.0 END)";
            }
            $cfScoreSql = "(" . implode(' + ', $cfCases) . ") / " . number_format($sumSim, 4, '.', '');

            // Left join với bảng borrowings của láng giềng
            $neighborJoin = "LEFT JOIN borrowings br_neighbor ON br_neighbor.book_id = b.id AND br_neighbor.user_id IN (" . implode(',', $neighborIds) . ")";
            $groupBy = "GROUP BY b.id";
        }
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

    // Câu lệnh SQL lai ghép chấm điểm (50% Content-based + 50% Collaborative Filtering)
    $query = "
        SELECT * FROM (
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
                -- Điểm CF chuẩn hóa
                ($cfScoreSql) AS cf_score,
                -- Điểm Content-based chuẩn hóa (60% Thể loại + 40% Tác giả)
                (
                    (0.60 * $affinitySql) + 
                    (0.40 * $authorAffinitySql)
                ) AS content_score,
                -- Điểm thưởng phụ
                IF(b.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY), 0.10, 0.0) AS recency_bonus,
                (b.borrow_count / :max_borrows) * 0.20 AS popularity_bonus
            FROM books b
            LEFT JOIN categories c ON c.id = b.category_id
            $neighborJoin
            WHERE b.is_deleted = 0
              AND b.status = 'available'
              $notBorrowedCond
            $groupBy
        ) AS temp_recs
        ORDER BY (
            0.50 * content_score + 
            0.50 * cf_score + 
            recency_bonus + 
            popularity_bonus
        ) DESC, borrow_count DESC, created_at DESC
        LIMIT :limit
    ";

    $stmtBooks = $pdo->prepare($query);

    // Bind values
    foreach ($params as $paramKey => $paramVal) {
        $paramType = is_int($paramVal) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmtBooks->bindValue($paramKey, $paramVal, $paramType);
    }

    $stmtBooks->execute();
    $recommendations = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn hóa kiểu dữ liệu cho khớp với kết quả JSON cũ và tính điểm score cuối cùng
    foreach ($recommendations as &$book) {
        $cf = (float)($book['cf_score'] ?? 0.0);
        $content = (float)($book['content_score'] ?? 0.0);
        $recency = (float)($book['recency_bonus'] ?? 0.0);
        $popularity = (float)($book['popularity_bonus'] ?? 0.0);

        $book['id']           = (int)$book['id'];
        $book['borrow_count'] = (int)$book['borrow_count'];
        $book['category_id']  = $book['category_id'] ? (int)$book['category_id'] : null;
        $book['score']        = round((0.50 * $content) + (0.50 * $cf) + $recency + $popularity, 4);

        // Ẩn bớt các cột trung gian trong kết quả JSON trả về
        unset($book['cf_score']);
        unset($book['content_score']);
        unset($book['recency_bonus']);
        unset($book['popularity_bonus']);
    }
    unset($book);

    // ── BƯỚC 3: Portfolio Allocation (Phân bổ danh mục gợi ý để tạo sự tình cờ - Serendipity) ──
    $finalRecommendations = $recommendations;
    if ($userId !== null && $hasHistory && !empty($coCategories)) {
        // Trọng số phân bổ: 75% sách quen thuộc (Nhóm 1), 25% sách tình cờ/khám phá (Nhóm 2)
        $group1Limit = (int)ceil($limit * 0.75);
        $targetCoLimit = $limit - $group1Limit;

        if ($targetCoLimit > 0) {
            $group1Recs = array_slice($recommendations, 0, $group1Limit);
            $group1Ids = array_column($group1Recs, 'id');

            $coCatPlaceholders = implode(',', array_fill(0, count($coCategories), '?'));

            // Lấy sách thuộc các thể loại liên đới (Nhóm 2), loại trừ sách đã gợi ý ở Nhóm 1 và sách user đã mượn
            $group2Query = "
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
                    (
                        IF(b.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY), 0.10, 0.0) + 
                        (b.borrow_count / ?) * 0.20
                    ) AS score
                FROM books b
                LEFT JOIN categories c ON c.id = b.category_id
                WHERE b.is_deleted = 0
                  AND b.status = 'available'
                  AND b.category_id IN ($coCatPlaceholders)
                  AND b.id NOT IN (SELECT book_id FROM borrowings WHERE user_id = ?)
            ";

            $group2Params = array_merge([$maxBorrowCount], $coCategories, [$userId]);

            if (!empty($group1Ids)) {
                $group1Placeholders = implode(',', array_fill(0, count($group1Ids), '?'));
                $group2Query .= " AND b.id NOT IN ($group1Placeholders)";
                $group2Params = array_merge($group2Params, $group1Ids);
            }

            $group2Query .= " ORDER BY score DESC, b.borrow_count DESC, b.created_at DESC LIMIT ?";
            $group2Params[] = $targetCoLimit;

            $stmtGroup2 = $pdo->prepare($group2Query);

            // Bind values
            $paramIndex = 1;
            foreach ($group2Params as $val) {
                $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmtGroup2->bindValue($paramIndex++, $val, $type);
            }

            $stmtGroup2->execute();
            $group2Recs = $stmtGroup2->fetchAll(PDO::FETCH_ASSOC);

            // Chuẩn hóa nhóm 2
            foreach ($group2Recs as &$book) {
                $book['id']           = (int)$book['id'];
                $book['borrow_count'] = (int)$book['borrow_count'];
                $book['category_id']  = $book['category_id'] ? (int)$book['category_id'] : null;
                $book['score']        = round((float)($book['score'] ?? 0.0), 4);
            }
            unset($book);

            // Kết hợp 2 nhóm
            $finalRecommendations = array_merge($group1Recs, $group2Recs);

            // Nếu nhóm 2 không đủ sách, bù đắp từ các sách còn lại của nhóm 1
            if (count($finalRecommendations) < $limit && count($recommendations) > count($group1Recs)) {
                $remainingRecs = array_slice($recommendations, $group1Limit);
                foreach ($remainingRecs as $remBook) {
                    if (count($finalRecommendations) >= $limit) {
                        break;
                    }
                    $alreadyAdded = false;
                    foreach ($finalRecommendations as $fRec) {
                        if ($fRec['id'] === $remBook['id']) {
                            $alreadyAdded = true;
                            break;
                        }
                    }
                    if (!$alreadyAdded) {
                        $finalRecommendations[] = $remBook;
                    }
                }
            }
        }
    }
    $recommendations = $finalRecommendations;

    echo json_encode([
        'has_history'      => $hasHistory,
        'top_categories'   => $topCategoryNames,
        'recommendations'  => $recommendations,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
