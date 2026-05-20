-- Thêm cột fcm_token vào bảng users để lưu FCM device token
-- Chạy file này 1 lần duy nhất trên MySQL để migrate DB

ALTER TABLE users ADD COLUMN IF NOT EXISTS fcm_token VARCHAR(500) DEFAULT NULL
    COMMENT 'Firebase Cloud Messaging device token — cập nhật sau mỗi lần đăng nhập';
