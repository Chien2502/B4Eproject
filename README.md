# 📚 B4E - Book for Everyone (Library Management System)

Dự án website quản lý thư viện cộng đồng, cho phép người dùng mượn sách, quyên góp sách và tương tác với thư viện. Hệ thống bao gồm giao diện dành cho người dùng (User) và trang quản trị (Admin Dashboard).

---

## 🛠 Yêu cầu hệ thống (Prerequisites)

Để chạy được dự án này trên môi trường Windows mới, bạn cần cài đặt các công cụ sau:

1.  **XAMPP** (Hoặc WampServer): Để chạy cơ sở dữ liệu MySQL.
    * *Download:* [apachefriends.org](https://www.apachefriends.org/)
2.  **Visual Studio Code**: Trình soạn thảo code.
3.  **PHP Server Extension (VS Code)**: Để chạy server PHP ảo ngay trên VS Code.
    * *Extension ID:* `brapifra.phpserver`

---

## 🚀 Hướng dẫn Cài đặt & Triển khai

Làm theo các bước sau để thiết lập dự án trên máy mới:

### Bước 1: Khởi động Database Server
1.  Mở **XAMPP Control Panel**.
2.  Bấm **Start** ở module **MySQL** (Chúng ta chỉ cần MySQL, không cần Start Apache nếu dùng VS Code PHP Server).
3.  Đảm bảo MySQL đang chạy ở cổng mặc định `3306`.

### Bước 2: Cấu hình Dự án trong VS Code
1.  Mở thư mục dự án `B4Eproject` bằng **Visual Studio Code**.
2.  Cài đặt Extension **PHP Server** (nếu chưa cài).
3.  Chuột phải vào file `index.html` (hoặc bất kỳ file nào), chọn **PHP Server: Serve project**.
    * Lúc này trình duyệt sẽ bật lên (thường là `http://localhost:3000`).

### Bước 3: Cài đặt Cơ sở dữ liệu Tự động (Quan trọng)
Dự án có tích hợp script tự động kiểm tra và khởi tạo Database. Bạn không cần import file SQL thủ công.

1.  Trên trình duyệt, truy cập đường dẫn sau để chạy trình cài đặt:
    ```
    http://localhost:3000/api/config/install.php
    ```
    *(Lưu ý: Thay `3000` bằng cổng thực tế mà PHP Server của bạn đang chạy nếu khác)*.

2.  Hệ thống sẽ thực hiện:
    * Kết nối đến MySQL.
    * Tạo database `b4e_library` (nếu chưa có).
    * Tạo toàn bộ bảng (Users, Books, Donations, Borrowings...).
    * Thêm dữ liệu mẫu (Admin mặc định, sách mẫu).

3.  Nếu màn hình hiện thông báo **"Cài đặt hoàn tất!"**, bạn đã sẵn sàng sử dụng.

---

## 👤 Tài khoản Mặc định (Default Credentials)

Sau khi chạy file cài đặt, bạn có thể đăng nhập bằng các tài khoản sau:

**1. Tài khoản Quản trị viên (Admin)**
* **Email:** `admin@b4e.com`
* **Mật khẩu:** `123456`
* *Quyền hạn:* Truy cập Dashboard, quản lý sách, duyệt quyên góp, quản lý user.

**2. Tài khoản Người dùng (User)**
* **Email:** `test@b4e.com`
* **Mật khẩu:** `123456`
* *Quyền hạn:* Mượn sách, gửi yêu cầu quyên góp, xem lịch sử cá nhân.

---

## 📂 Cấu trúc Thư mục

* `admin/`: Giao diện quản trị (Dashboard).
* `api/`: Các API xử lý logic (PHP).
    * `config/`: Chứa file kết nối DB và file `install.php`.
* `css/`: Mã nguồn giao diện (Styles).
* `js/`: Mã nguồn xử lý sự kiện (Scripts).
* `img/`: Hình ảnh bìa sách và giao diện.
* `layout/`: Các thành phần HTML dùng chung (Modal, v.v.).

---

## 📝 Lưu ý khi phát triển
* File cấu hình kết nối database nằm tại: `api/config/database.php`.
* Nếu muốn reset lại toàn bộ dữ liệu: Hãy xóa database `b4e_library` trong phpMyAdmin, sau đó chạy lại link `install.php`.

---
&copy; 2025 B4E Project.