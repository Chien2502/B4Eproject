-- ==========================================================
-- THÊM BẢNG NOTIFICATIONS (Thông báo In-App)
-- Chạy script này trong phpMyAdmin (tab SQL) trên database b4e_library.
-- KHÔNG chạy lại database.sql (sẽ xóa dữ liệu cũ).
-- ==========================================================

USE b4e_library;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT NOT NULL,

  -- Loại thông báo — Flutter dùng để chọn icon/màu:
  -- borrow_approved | borrow_rejected | return_reminder
  -- return_overdue  | donation_approved | donation_rejected | system
  `type`       VARCHAR(50) NOT NULL DEFAULT 'system',

  -- ID nguồn gốc (borrowing_id hoặc donation_id), NULL nếu là thông báo hệ thống
  `ref_id`     INT NULL,

  -- 0 = chưa đọc, 1 = đã đọc
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,

  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,

  INDEX `idx_user_unread` (`user_id`, `is_read`),
  INDEX `idx_created_at`  (`created_at`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
