-- ============================================================
-- RECOMMENDATION SYSTEM — Database Migration
-- Chạy file này trong phpMyAdmin (USE b4e_library trước)
-- ============================================================

-- [1] Thêm cột borrow_count vào bảng books
ALTER TABLE books
  ADD COLUMN IF NOT EXISTS borrow_count INT NOT NULL DEFAULT 0;

-- [2] Sync lại giá trị từ dữ liệu borrowings hiện có
UPDATE books b
SET b.borrow_count = (
  SELECT COUNT(*) FROM borrowings br WHERE br.book_id = b.id
);

-- [3] Trigger tự động tăng borrow_count khi có lượt mượn mới
DROP TRIGGER IF EXISTS trg_increment_borrow;
CREATE TRIGGER trg_increment_borrow
AFTER INSERT ON borrowings
FOR EACH ROW
  UPDATE books SET borrow_count = borrow_count + 1 WHERE id = NEW.book_id;

-- Kiểm tra kết quả
SELECT id, title, borrow_count
FROM books
ORDER BY borrow_count DESC
LIMIT 10;
