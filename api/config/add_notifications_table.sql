-- ==========================================================
-- THÊM BẢNG NOTIFICATIONS (Thông báo In-App)
-- Chạy script này trên database b4e_library hiện có.
-- KHÔNG chạy lại database.sql (sẽ xóa dữ liệu cũ).
-- ==========================================================

USE b4e_library;

-- Bảng 6: Notifications (Thông báo)
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,

  -- Liên kết với người nhận thông báo (bắt buộc)
  `user_id`          INT NOT NULL,

  -- Tiêu đề và nội dung thông báo
  `title`            VARCHAR(255) NOT NULL,
  `message`          TEXT NOT NULL,

  -- Loại thông báo để Flutter chọn icon/màu phù hợp
  -- Các giá trị hợp lệ:
  --   borrow_approved  : Yêu cầu mượn được duyệt
  --   borrow_rejected  : Yêu cầu mượn bị từ chối
  --   return_reminder  : Nhắc trả sách sắp đến hạn
  --   return_overdue   : Sách đã quá hạn
  --   donation_approved: Quyên góp được duyệt
  --   donation_rejected: Quyên góp bị từ chối
  --   system           : Thông báo hệ thống chung
  `type`             VARCHAR(50) NOT NULL DEFAULT 'system',

  -- ID tham chiếu nguồn gốc thông báo (có thể NULL nếu không liên quan)
  -- VD: borrow_id=5 nghĩa là thông báo liên quan đến borrowings.id=5
  `ref_id`           INT NULL,

  -- Trạng thái đọc: 0 = chưa đọc, 1 = đã đọc
  `is_read`          TINYINT(1) NOT NULL DEFAULT 0,

  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  -- Khóa ngoại: Xóa user → xóa toàn bộ thông báo của user đó
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,

  -- Index để truy vấn nhanh theo user_id và is_read
  INDEX `idx_user_unread` (`user_id`, `is_read`),
  INDEX `idx_created_at`  (`created_at`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
