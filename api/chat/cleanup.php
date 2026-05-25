<?php
/**
 * Cron Job: Dọn dẹp tin nhắn chat > 30 ngày.
 * Chạy hàng ngày lúc 3:00 AM.
 * 
 * Windows Task Scheduler:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\B4Eproject\api\chat\cleanup.php
 * 
 * Linux crontab:
 *   0 3 * * * /usr/bin/php /path/to/api/chat/cleanup.php
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = (new Database())->connect();

    // 1. Xóa tin nhắn > 30 ngày
    $stmt = $db->prepare(
        "DELETE FROM chat_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $stmt->execute();
    $deletedMessages = $stmt->rowCount();

    // 2. Xóa thread không còn tin nhắn nào
    $stmt = $db->prepare(
        "DELETE FROM chat_threads 
         WHERE id NOT IN (SELECT DISTINCT thread_id FROM chat_messages)"
    );
    $stmt->execute();
    $deletedThreads = $stmt->rowCount();

    $msg = date('Y-m-d H:i:s') . " | Chat cleanup: deleted $deletedMessages messages, $deletedThreads empty threads.";
    echo $msg . "\n";
    error_log("[Chat Cleanup] $msg");

} catch (Exception $e) {
    $errMsg = date('Y-m-d H:i:s') . " | Chat cleanup ERROR: " . $e->getMessage();
    echo $errMsg . "\n";
    error_log("[Chat Cleanup] $errMsg");
}
?>
