<?php
require_once __DIR__ . '/database.php';

try {
    $db = (new Database())->connect();
    
    // Kiểm tra xem cột fcm_token đã tồn tại chưa
    $check = $db->query("SHOW COLUMNS FROM users LIKE 'fcm_token'")->fetch();
    
    if (!$check) {
        $sql = "ALTER TABLE users ADD COLUMN fcm_token VARCHAR(500) DEFAULT NULL 
                COMMENT 'Firebase Cloud Messaging device token — cập nhật sau mỗi lần đăng nhập';";
        $db->exec($sql);
        echo "SUCCESS: Column 'fcm_token' added to table 'users' successfully.\n";
    } else {
        echo "INFO: Column 'fcm_token' already exists in table 'users'.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
