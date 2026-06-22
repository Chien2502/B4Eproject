<?php
require_once __DIR__ . '/database.php';

try {
    $db = (new Database())->connect();
    
    $sql = "CREATE TABLE IF NOT EXISTS user_similarities (
        user_id_1 INT NOT NULL,
        user_id_2 INT NOT NULL,
        similarity DECIMAL(5,4) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id_1, user_id_2),
        FOREIGN KEY (user_id_1) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id_2) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user1_similarity (user_id_1, similarity DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    echo "Migration successful: Table user_similarities created.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
