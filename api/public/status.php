<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->connect();

    // 1. Đếm tổng đầu sách
    $books = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();

    // 2. Đếm tổng thành viên (trừ admin)
    $users = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

    // 3. Đếm người quyên góp (Đếm user_id duy nhất trong bảng donations)
    $donors = $db->query("SELECT COUNT(DISTINCT user_id) FROM donations")->fetchColumn();

    // 4. Đếm lượt mượn sách
    $borrows = $db->query("SELECT COUNT(*) FROM borrowings")->fetchColumn();

    echo json_encode([
        'books' => $books,
        'users' => $users,
        'donors' => $donors,
        'borrows' => $borrows
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>