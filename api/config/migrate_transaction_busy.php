<?php
/**
 * Migration: Add 'busy' status to books.status and created_at to borrowings
 * 
 * Run this file once to upgrade the database schema.
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/database.php';

$db = (new Database())->connect();
$results = [];

function runSql(PDO $db, string $label, string $sql): string {
    try {
        $db->exec($sql);
        return "✅ $label";
    } catch (PDOException $e) {
        if (in_array($e->getCode(), ['42S21', '42000', 23000])) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate column') || str_contains($msg, 'already exists')) {
                return "⏭️  $label (đã tồn tại, bỏ qua)";
            }
        }
        return "❌ $label — " . $e->getMessage();
    }
}

// 1. Sửa ENUM status của books — thêm trạng thái 'busy' vào
$results[] = runSql($db, 'books.status ENUM mở rộng (busy)',
    "ALTER TABLE books 
     MODIFY COLUMN status ENUM('available', 'borrowed', 'busy') NOT NULL DEFAULT 'available'");

// 2. Thêm cột created_at vào borrowings
$results[] = runSql($db, 'borrowings.created_at',
     "ALTER TABLE borrowings 
      ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

// Output
echo "=== B4E Library — Database Migration: Transaction Expiry & Busy Status ===\n\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\n=== Hoàn tất! ===\n";
?>
