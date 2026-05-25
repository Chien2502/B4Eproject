<?php
/**
 * API: Admin - Danh sách chat threads
 * GET /chat/threads.php
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

require_once '../config/database.php';
require_once '../config/middleware.php';

try {
    $admin = checkAdminAuth();
    $db = (new Database())->connect();

    $sql = "SELECT ct.id as thread_id, ct.user_id, u.username, u.avatar,
                   ct.last_message, ct.last_message_at, ct.last_sender,
                   ct.unread_by_admin, ct.is_active
            FROM chat_threads ct
            JOIN users u ON ct.user_id = u.id
            WHERE ct.is_active = 1
            ORDER BY ct.last_message_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tính tổng unread
    $total_unread = 0;
    foreach ($threads as &$t) {
        $t['unread_by_admin'] = (int)$t['unread_by_admin'];
        $total_unread += $t['unread_by_admin'];
    }

    echo json_encode([
        'data'         => $threads,
        'total_unread' => $total_unread,
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi: ' . $e->getMessage()]);
}
?>
