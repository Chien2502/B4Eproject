<?php
// admin/index.php
require_once 'includes/auth.php';   // 1. Kiểm tra đăng nhập
require_once 'includes/header.php'; // 2. Kết nối DB và Giao diện

// Lấy thống kê
$count_books = $conn->query("SELECT COUNT(*) FROM books")->fetchColumn();
$count_users = $conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
// Đếm quyên góp đang chờ
$count_pending_donations = $conn->query("SELECT COUNT(*) FROM donations WHERE status='pending'")->fetchColumn();
// Đếm sách đang chờ trả (returning)
$count_returning = $conn->query("SELECT COUNT(*) FROM borrowings WHERE status='returning'")->fetchColumn();
?>

<div class="content">
    <h1>Tổng quan hệ thống</h1>
    
    <div class="card-grid">
        <div class="card">
            <h3><?php echo $count_books; ?></h3>
            <p>Tổng đầu sách</p>
        </div>
        <div class="card">
            <h3><?php echo $count_users; ?></h3>
            <p>Thành viên</p>
        </div>
        <div class="card" style="border-left: 5px solid #ffc107;">
            <h3 style="color: #ffc107;"><?php echo $count_pending_donations; ?></h3>
            <p>Yêu cầu Quyên góp mới</p>
            <?php if($count_pending_donations > 0): ?>
                <a href="manage_donations.php" class="btn btn-blue">Xử lý ngay</a>
            <?php endif; ?>
        </div>
        <div class="card" style="border-left: 5px solid #28a745;">
            <h3 style="color: #28a745;"><?php echo $count_returning; ?></h3>
            <p>Sách đang trả về</p>
            <?php if($count_returning > 0): ?>
                <a href="manage_borrowings.php" class="btn btn-blue">Xác nhận</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>