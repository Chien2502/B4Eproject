<?php
/**
 * Migration: Add return_approved status and return_approved_at timestamp
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

// 1. Sửa ENUM status — thêm trạng thái 'return_approved' vào
$results[] = runSql($db, 'borrowings.status ENUM mở rộng (return_approved)',
    "ALTER TABLE borrowings 
     MODIFY COLUMN status ENUM(
       'pending_approval','approved','preparing','shipped',
       'borrowed','return_requested','return_approved','return_shipping',
       'returned','overdue','cancelled'
     ) NOT NULL DEFAULT 'pending_approval'");

// 2. Thêm cột return_approved_at
$results[] = runSql($db, 'borrowings.return_approved_at',
    "ALTER TABLE borrowings 
     ADD COLUMN return_approved_at DATETIME NULL AFTER shipped_at");

// Output
echo "=== B4E Library — Database Migration: Return Approved Status ===\n\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\n=== Hoàn tất! ===\n";
?>
