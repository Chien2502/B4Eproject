<?php
require_once 'includes/auth.php';
require_once 'includes/header.php';

$id = $_GET['id'] ?? null;
if (!$id) { echo "Không tìm thấy ID người dùng!"; exit; }

$message = '';

// XỬ LÝ POST (Cập nhật)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $role = $_POST['role'];

    try {
        $sql = "UPDATE users SET username=?, phone=?, address=?, role=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $phone, $address, $role, $id]);
        $message = "<div style='color:green; margin-bottom:15px;'>Cập nhật thành công! <a href='manage_users.php'>Quay lại danh sách</a></div>";
    } catch (Exception $e) {
        $message = "<div style='color:red'>Lỗi: " . $e->getMessage() . "</div>";
    }
}

// LẤY DỮ LIỆU HIỂN THỊ
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) { echo "Người dùng không tồn tại!"; exit; }
?>

<div class="content">
    <div style="max-width: 600px; margin: 0 auto;">
        <h1>✏️ Chỉnh sửa Người dùng</h1>
        <?php echo $message; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>Email (Không thể sửa)</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="width:100%; padding:8px; background:#eee;">
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Tên đăng nhập</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required style="width:100%; padding:8px;">
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Phân quyền (Role)</label>
                    <select name="role" style="width:100%; padding:8px; border:2px solid #007bff; border-radius:4px;">
                        <option value="user" <?php echo ($user['role'] == 'user') ? 'selected' : ''; ?>>User (Thành viên)</option>
                        <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Admin (Quản trị viên)</option>
                        <option value="super-admin" <?php echo ($user['role'] == 'super-admin') ? 'selected' : ''; ?>>Super Admin</option>
                    </select>
                    <p style="font-size:0.8rem; color:#666;">⚠️ Cẩn trọng khi cấp quyền Admin.</p>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Số điện thoại</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" style="width:100%; padding:8px;">
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Địa chỉ</label>
                    <textarea name="address" rows="3" style="width:100%; padding:8px;"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>

                <div style="margin-top:20px; text-align:right;">
                    <a href="manage_users.php" class="btn btn-red" style="margin-right:10px;">Hủy</a>
                    <button type="submit" class="btn btn-green">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>