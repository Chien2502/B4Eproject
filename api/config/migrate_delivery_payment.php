<?php
/**
 * Migration: Delivery + Payment Feature
 * 
 * Chạy file này 1 lần duy nhất để nâng cấp database.
 * URL: http://localhost/B4Eproject/api/config/migrate_delivery_payment.php
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
        // Bỏ qua lỗi "column already exists" (1060) hoặc "duplicate key" (1061, 1060)
        if (in_array($e->getCode(), ['42S21', '42000', 23000])) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate column') || str_contains($msg, 'already exists')) {
                return "⏭️  $label (đã tồn tại, bỏ qua)";
            }
        }
        return "❌ $label — " . $e->getMessage();
    }
}

// ==============================================================
// STEP 1: ALTER TABLE borrowings
// ==============================================================

// 1a. Thêm cột delivery_type
$results[] = runSql($db, 'borrowings.delivery_type',
    "ALTER TABLE borrowings 
     ADD COLUMN delivery_type ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup' 
     AFTER book_id");

// 1b. Sửa ENUM status — thêm các trạng thái mới
$results[] = runSql($db, 'borrowings.status ENUM mở rộng',
    "ALTER TABLE borrowings 
     MODIFY COLUMN status ENUM(
       'pending_approval','approved','preparing','shipped',
       'borrowed','return_requested','return_shipping',
       'returned','overdue','cancelled'
     ) NOT NULL DEFAULT 'pending_approval'");

// 1c. Thêm delivery_address
$results[] = runSql($db, 'borrowings.delivery_address',
    "ALTER TABLE borrowings 
     ADD COLUMN delivery_address TEXT NULL AFTER delivery_type");

// 1d. Thêm delivery_distance_km
$results[] = runSql($db, 'borrowings.delivery_distance_km',
    "ALTER TABLE borrowings 
     ADD COLUMN delivery_distance_km DECIMAL(8,2) NULL AFTER delivery_address");

// 1e. Thêm shipping_fee
$results[] = runSql($db, 'borrowings.shipping_fee',
    "ALTER TABLE borrowings 
     ADD COLUMN shipping_fee INT NOT NULL DEFAULT 0 AFTER delivery_distance_km");

// 1f. Thêm payment_method
$results[] = runSql($db, 'borrowings.payment_method',
    "ALTER TABLE borrowings 
     ADD COLUMN payment_method ENUM('cod','vietqr') NULL AFTER shipping_fee");

// 1g. Thêm payment_status
$results[] = runSql($db, 'borrowings.payment_status',
    "ALTER TABLE borrowings 
     ADD COLUMN payment_status ENUM('pending','paid','not_required') NOT NULL DEFAULT 'not_required' 
     AFTER payment_method");

// 1h. Thêm payment_ref
$results[] = runSql($db, 'borrowings.payment_ref',
    "ALTER TABLE borrowings 
     ADD COLUMN payment_ref VARCHAR(100) NULL AFTER payment_status");

// 1i. Thêm payment_confirmed_at
$results[] = runSql($db, 'borrowings.payment_confirmed_at',
    "ALTER TABLE borrowings 
     ADD COLUMN payment_confirmed_at DATETIME NULL AFTER payment_ref");

// 1j. Thêm approved_at
$results[] = runSql($db, 'borrowings.approved_at',
    "ALTER TABLE borrowings 
     ADD COLUMN approved_at DATETIME NULL");

// 1k. Thêm shipped_at
$results[] = runSql($db, 'borrowings.shipped_at',
    "ALTER TABLE borrowings 
     ADD COLUMN shipped_at DATETIME NULL");

// 1l. Thêm cancelled_at
$results[] = runSql($db, 'borrowings.cancelled_at',
    "ALTER TABLE borrowings 
     ADD COLUMN cancelled_at DATETIME NULL");

// Migrate dữ liệu cũ: 'borrowed' cũ vẫn là 'borrowed' (không đổi)
// Không cần migrate vì default mới là 'pending_approval' chỉ áp dụng cho record mới

// ==============================================================
// STEP 2: ALTER TABLE donations
// ==============================================================

// 2a. Thêm pickup_type
$results[] = runSql($db, 'donations.pickup_type',
    "ALTER TABLE donations 
     ADD COLUMN pickup_type ENUM('self_deliver','user_ship') NOT NULL DEFAULT 'self_deliver' 
     AFTER donation_type");

// 2b. Sửa ENUM status
$results[] = runSql($db, 'donations.status ENUM mở rộng',
    "ALTER TABLE donations 
     MODIFY COLUMN status ENUM(
       'pending','approved','in_transit','received','processed','rejected'
     ) NOT NULL DEFAULT 'pending'");

// 2c. Thêm pickup_address
$results[] = runSql($db, 'donations.pickup_address',
    "ALTER TABLE donations 
     ADD COLUMN pickup_address TEXT NULL");

// 2d. Timestamps
$results[] = runSql($db, 'donations.approved_at',
    "ALTER TABLE donations ADD COLUMN approved_at DATETIME NULL");
$results[] = runSql($db, 'donations.received_at',
    "ALTER TABLE donations ADD COLUMN received_at DATETIME NULL");
$results[] = runSql($db, 'donations.processed_at',
    "ALTER TABLE donations ADD COLUMN processed_at DATETIME NULL");

// 2e. Thêm image_url nếu chưa có
$results[] = runSql($db, 'donations.image_url',
    "ALTER TABLE donations ADD COLUMN image_url VARCHAR(255) NULL AFTER book_condition");

// ==============================================================
// STEP 3: Tạo bảng shipping_fee_config
// ==============================================================
$results[] = runSql($db, 'CREATE TABLE shipping_fee_config',
    "CREATE TABLE IF NOT EXISTS shipping_fee_config (
       id         INT AUTO_INCREMENT PRIMARY KEY,
       min_km     DECIMAL(8,2) NOT NULL,
       max_km     DECIMAL(8,2) NOT NULL,
       fee        INT NOT NULL COMMENT 'Phí vận chuyển (VNĐ)',
       is_active  TINYINT(1) NOT NULL DEFAULT 1,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Seed data — INSERT IGNORE để tránh duplicate
$results[] = runSql($db, 'Seed shipping_fee_config',
    "INSERT IGNORE INTO shipping_fee_config (id, min_km, max_km, fee) VALUES
     (1, 0,  5,  15000),
     (2, 5,  10, 25000),
     (3, 10, 20, 40000),
     (4, 20, 35, 60000)");

// ==============================================================
// STEP 4: Tạo bảng library_config
// ==============================================================
$results[] = runSql($db, 'CREATE TABLE library_config',
    "CREATE TABLE IF NOT EXISTS library_config (
       config_key   VARCHAR(100) PRIMARY KEY,
       config_value TEXT NOT NULL,
       updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Seed library config (dùng INSERT IGNORE để không ghi đè nếu đã có)
$results[] = runSql($db, 'Seed library_config',
    "INSERT IGNORE INTO library_config (config_key, config_value) VALUES
     ('library_name',    'Thư viện B4E'),
     ('library_address', 'Hà Nội, Việt Nam'),
     ('library_lat',     '21.028511'),
     ('library_lng',     '105.804817'),
     ('max_delivery_km', '35'),
     ('bank_name',       'Vietcombank'),
     ('bank_account',    '1234567890'),
     ('bank_owner',      'THU VIEN B4E'),
     ('borrow_timeout_vietqr_hours', '24'),
     ('borrow_timeout_cod_hours',    '48')");

// ==============================================================
// OUTPUT
// ==============================================================
echo "=== B4E Library — Database Migration: Delivery & Payment ===\n\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\n=== Hoàn tất! ===\n";
echo "Vui lòng cập nhật thông tin ngân hàng trong bảng library_config.\n";
?>
