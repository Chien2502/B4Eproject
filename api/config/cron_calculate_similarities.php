<?php
// api/config/cron_calculate_similarities.php
// Tính toán độ tương đồng giữa các người dùng (User-User Cosine Similarity)
// Chạy định kỳ qua Cron Job hoặc gọi thủ công để làm mới bộ nhớ đệm gợi ý.

require_once __DIR__ . '/database.php';

try {
    $db = (new Database())->connect();
    
    // 1. Lấy tất cả danh sách sách đã mượn của từng người dùng
    $query = "SELECT user_id, book_id FROM borrowings WHERE status NOT IN ('pending_approval', 'cancelled')";
    $stmt = $db->query($query);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $userBooks = []; // [user_id => [book_id => true]]
    $bookUsers = []; // [book_id => [user_id => true]]
    
    foreach ($records as $r) {
        $uId = (int)$r['user_id'];
        $bId = (int)$r['book_id'];
        
        $userBooks[$uId][$bId] = true;
        $bookUsers[$bId][$uId] = true;
    }
    
    // 2. Xác định các cặp người dùng có chung ít nhất 1 cuốn sách để tối ưu tính toán
    $pairs = []; // ["u1_u2" => true]
    foreach ($bookUsers as $bId => $users) {
        $userList = array_keys($users);
        $count = count($userList);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $u1 = $userList[$i];
                $u2 = $userList[$j];
                $key = $u1 < $u2 ? "{$u1}_{$u2}" : "{$u2}_{$u1}";
                $pairs[$key] = true;
            }
        }
    }
    
    // Xóa dữ liệu cũ trước khi nạp dữ liệu mới (nằm ngoài transaction vì DDL tự động commit)
    $db->exec("TRUNCATE TABLE user_similarities");
    
    $db->beginTransaction();
    
    $insertQuery = "INSERT INTO user_similarities (user_id_1, user_id_2, similarity) VALUES ";
    $values = [];
    $params = [];
    
    $metric = strtolower(getenv('CF_SIMILARITY_METRIC') ?: 'cosine');
    echo "Using similarity metric: " . strtoupper($metric) . "\n";

    foreach (array_keys($pairs) as $pairKey) {
        list($u1, $u2) = explode('_', $pairKey);
        $u1 = (int)$u1;
        $u2 = (int)$u2;
        
        $books1 = $userBooks[$u1];
        $books2 = $userBooks[$u2];
        
        // Giao của 2 tập hợp
        $intersectCount = count(array_intersect_key($books1, $books2));
        if ($intersectCount > 0) {
            $similarity = 0.0;
            switch ($metric) {
                case 'jaccard':
                    $unionCount = count($books1) + count($books2) - $intersectCount;
                    $similarity = $unionCount > 0 ? $intersectCount / $unionCount : 0.0;
                    break;
                case 'dice':
                    $similarity = (2.0 * $intersectCount) / (count($books1) + count($books2));
                    break;
                case 'overlap':
                    $minCount = min(count($books1), count($books2));
                    $similarity = $minCount > 0 ? $intersectCount / $minCount : 0.0;
                    break;
                case 'cosine':
                default:
                    $denom = sqrt(count($books1) * count($books2));
                    $similarity = $denom > 0 ? $intersectCount / $denom : 0.0;
                    break;
            }
            
            if ($similarity > 0.0001) {
                // Lưu đối xứng (u1, u2) và (u2, u1) để tối ưu việc tìm kiếm láng giềng
                $values[] = "(?, ?, ?)";
                $params[] = $u1;
                $params[] = $u2;
                $params[] = $similarity;
                
                $values[] = "(?, ?, ?)";
                $params[] = $u2;
                $params[] = $u1;
                $params[] = $similarity;
            }
        }
        
        // Thực hiện ghi theo lô (batch insert 1000 dòng một lần) để tránh giới hạn SQL
        if (count($values) >= 1000) {
            $stmtInsert = $db->prepare($insertQuery . implode(', ', $values));
            $stmtInsert->execute($params);
            $values = [];
            $params = [];
        }
    }
    
    // Insert nốt số dòng còn lại
    if (!empty($values)) {
        $stmtInsert = $db->prepare($insertQuery . implode(', ', $values));
        $stmtInsert->execute($params);
    }
    
    $db->commit();
    echo "Successfully calculated similarities for " . count($pairs) . " candidate user pairs.\n";
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error calculating similarities: " . $e->getMessage() . "\n";
}
?>
