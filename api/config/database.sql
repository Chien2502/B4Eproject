-- ==========================================================
-- 1. KHỞI TẠO DATABASE
-- ==========================================================
DROP DATABASE IF EXISTS b4e_library;
CREATE DATABASE b4e_library CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE b4e_library;

-- ==========================================================
-- 2. TẠO CẤU TRÚC BẢNG (SCHEMA)
-- ==========================================================

-- Bảng 1: Categories (Thể loại)
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng 2: Users (Người dùng - Đã cập nhật phone, address)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `address` TEXT NULL,
  `role` ENUM('user', 'admin', 'super-admin') NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng 3: Books (Sách)
CREATE TABLE `books` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `author` VARCHAR(255) NOT NULL,
  `publisher` VARCHAR(255) NULL,
  `year` INT NULL,
  `category_id` INT NULL,
  `description` TEXT NULL,
  `image_url` VARCHAR(255) NULL,
  `status` ENUM('available', 'borrowed') NOT NULL DEFAULT 'available',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng 4: Borrowings (Mượn trả - Đã thêm trạng thái 'returning')
CREATE TABLE `borrowings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `book_id` INT NOT NULL,
  `borrow_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `return_date` DATE NULL,
  `status` ENUM('borrowed', 'returning', 'returned', 'overdue') NOT NULL DEFAULT 'borrowed',
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng 5: Donations (Quyên góp)
CREATE TABLE `donations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `donation_type` VARCHAR(50) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `book_title` VARCHAR(255) NOT NULL,
  `book_author` VARCHAR(255) NOT NULL,
  `book_publisher` VARCHAR(255) NULL,
  `book_year` INT NULL,
  `book_condition` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- 3. NHẬP DỮ LIỆU MẪU (SEED DATA)
-- ==========================================================

-- 3.1. Thêm Thể loại
INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Tiểu thuyết'),
(2, 'Kỹ năng sống'),
(3, 'Kinh tế'),
(4, 'Tâm lý học'),
(5, 'Khoa học'),
(6, 'Sách giáo khoa'),
(7, 'Văn học kinh điển'),
(8, 'Truyện thiếu nhi');

-- 3.2. Thêm Người dùng mẫu
-- Mật khẩu cho cả 2 tài khoản là: 123456
-- Hash: $2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm (Bcrypt cost 10)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `phone`, `address`) VALUES
('admin_b4e', 'admin@gmail.com.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'super-admin', '0909123456', 'Phòng Admin, Thư viện B4E'),
('user_test', 'test@b4e.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'user', '0987654321', 'Hà Nội, Việt Nam');

-- 3.3. Thêm Sách (20 cuốn)
INSERT INTO `books` (`title`, `author`, `category_id`, `publisher`, `year`, `status`, `image_url`, `description`) VALUES
-- Sách từ Database cũ
('Nhà Giả Kim', 'Paulo Coelho', 1, 'NXB Văn Học', 2013, 'available', 'img/Book/Nha_gia_kim.png', 'Tiểu thuyết kể về hành trình của chàng chăn cừu Santiago đi tìm kho báu.'),
('Hai Số Phận', 'Jeffrey Archer', 1, 'NXB Văn Học', 2018, 'available', 'img/Book/hai_số_phận.webp', 'Câu chuyện song hành đầy kịch tính về cuộc đời của hai người đàn ông sinh cùng ngày.'),
('Người Đua Diều', 'Khaled Hosseini', 1, 'NXB Nhã Nam', 2015, 'available', 'img/Book/nguoi-dua-dieu.png', 'Một câu chuyện cảm động về tình bạn, sự phản bội và chuộc lỗi tại Afghanistan.'),
('Trăm Năm Cô Đơn', 'Gabriel García Márquez', 7, 'NXB Văn Học', 2010, 'available', 'img/Book/Trăm_năm_cô_đơn.jpeg', 'Kiệt tác văn học hiện thực huyền ảo kể về dòng họ Buendía.'),
('Số Đỏ', 'Vũ Trọng Phụng', 7, 'NXB Văn Học', 2000, 'available', 'img/Book/số_đỏ.webp', 'Tác phẩm châm biếm kinh điển của văn học Việt Nam.'),
('Đắc Nhân Tâm', 'Dale Carnegie', 2, 'NXB Tổng Hợp TP.HCM', 2016, 'available', 'img/Book/dac_nhân_tâm.jpg', 'Nghệ thuật giao tiếp và đối nhân xử thế kinh điển.'),
('Thế Giới Phẳng', 'Thomas L. Friedman', 3, 'NXB Trẻ', 2014, 'borrowed', 'img/Book/Thế_giới_phẳng.jpg', 'Cái nhìn sâu sắc về toàn cầu hóa trong thế kỷ 21.'),
('Điểm Đến Của Cuộc Đời', 'Đặng Hoàng Giang', 4, 'NXB Hội Nhà Văn', 2020, 'borrowed', 'img/Book/điểm_đến_của_cuộc_đời.jpg', 'Hành trình đồng hành cùng những người ở cận kề cái chết.'),
('Súng, Vi Trùng và Thép', 'Jared Diamond', 5, 'NXB Tri Thức', 2019, 'available', 'img/Book/súng_vi_trùng_và_thép.webp', 'Lược sử nhân loại qua các yếu tố địa lý và sinh học.'),
('Vũ Trụ Trong Vỏ Hạt Dẻ', 'Stephen Hawking', 5, 'NXB Trẻ', 2012, 'borrowed', 'img/Book/vũ_trụ_trong_vỏ_hạt_dẻ.jpg', 'Khám phá kỳ thú của vật lý lý thuyết.'),
('Lược Sử Thời Gian', 'Stephen Hawking', 5, 'NXB Trẻ', 2011, 'available', 'img/Book/Lược_sử_thời_gian.jpg', 'Cuốn sách khoa học phổ thông bán chạy nhất mọi thời đại.'),
('Gen Vị Kỷ', 'Richard Dawkins', 5, 'NXB Tri Thức', 2015, 'available', 'img/Book/gen_vị_kỷ.jpg', 'Quan điểm gen là đơn vị trung tâm của sự tiến hóa.'),
('Toán 12 (Giải tích & Hình học)', 'Bộ Giáo dục', 6, 'NXB Giáo Dục', 2023, 'available', 'img/Book/Toán_12.jpg', 'Sách giáo khoa Toán lớp 12 chương trình chuẩn.'),

-- Sách Mới Thêm (Để database phong phú hơn)
('Harry Potter và Hòn đá Phù thủy', 'J.K. Rowling', 1, 'NXB Trẻ', 2020, 'available', 'img/Book/harry_potter_1.jpg', 'Khởi đầu cuộc hành trình của cậu bé phù thủy Harry Potter.'),
('Dế Mèn Phiêu Lưu Ký', 'Tô Hoài', 8, 'NXB Kim Đồng', 2019, 'available', 'img/Book/de_men.jpg', 'Câu chuyện phiêu lưu kinh điển dành cho thiếu nhi Việt Nam.'),
('Đọc Vị Bất Kỳ Ai', 'David J. Lieberman', 4, 'NXB Lao Động', 2018, 'available', 'img/Book/doc_vi.jpg', 'Phương pháp tâm lý để hiểu rõ suy nghĩ của người khác.'),
('Cha Giàu Cha Nghèo', 'Robert Kiyosaki', 3, 'NXB Trẻ', 2021, 'available', 'img/Book/cha_giau_cha_ngheo.jpg', 'Tư duy tài chính khác biệt giữa người giàu và người nghèo.'),
('Tắt Đèn', 'Ngô Tất Tố', 7, 'NXB Văn Học', 2015, 'available', 'img/Book/tat_den.jpg', 'Bức tranh hiện thực về cuộc sống khốn cùng của nông dân Việt Nam xưa.'),
('Tuổi Trẻ Đáng Giá Bao Nhiêu', 'Rosie Nguyễn', 2, 'NXB Hội Nhà Văn', 2017, 'borrowed', 'img/Book/tuoi_tre.jpg', 'Cuốn sách truyền cảm hứng cho giới trẻ Việt Nam.'),
('Sapiens: Lược Sử Loài Người', 'Yuval Noah Harari', 5, 'NXB Tri Thức', 2018, 'available', 'img/Book/sapiens.jpg', 'Câu chuyện về sự hình thành và phát triển của loài người.');