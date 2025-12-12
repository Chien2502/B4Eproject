<?php
require_once 'includes/auth.php';
require_once 'includes/header.php';

// Lấy danh sách users
$query = "SELECT * FROM users ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content">
    <h1>Quản lý Người dùng</h1>
    <p>Danh sách tất cả tài khoản trong hệ thống.</p>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên đăng nhập / Email</th>
                    <th>Vai trò (Role)</th>
                    <th>Thông tin liên hệ</th>
                    <th>Ngày tham gia</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr id="row-<?php echo $u['id']; ?>">
                    <td>#<?php echo $u['id']; ?></td>
                    <td>
                        <b><?php echo htmlspecialchars($u['username']); ?></b><br>
                        <small><?php echo htmlspecialchars($u['email']); ?></small>
                    </td>
                    <td>
                        <?php 
                        if ($u['role'] === 'admin') {
                            echo '<span class="badge" style="background:#6f42c1; color:white;">Admin</span>';
                        } if ($u['role'] === 'super-admin') {
                            echo '<span class="badge" style="background:#6f42c1; color:white;">Super-Admin</span>';
                        }else {
                            echo '<span class="badge" style="background:#17a2b8; color:white;">User</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($u['phone'] ?? '---'); ?><br>
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($u['address'] ?? '---'); ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                    <td>
                        <a href="user_form.php?id=<?php echo $u['id']; ?>" class="btn btn-blue" title="Sửa quyền/Thông tin">
                            <i class="fas fa-edit"></i>
                        </a>
                        &nbsp;&nbsp;
                        <?php if($u['id'] != $_SESSION['admin_id']): ?>
                        <button class="btn btn-red" onclick="deleteUser(<?php echo $u['id']; ?>)" title="Xóa tài khoản">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function deleteUser(id) {
    if (!confirm('CẢNH BÁO: Xóa người dùng sẽ xóa luôn lịch sử mượn và quyên góp của họ. Bạn có chắc chắn không?')) return;

    try {
        const response = await fetch('/api/admin/delete_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            alert(result.message);
            document.getElementById('row-' + id).remove();
        } else {
            alert('Lỗi: ' + result.error);
        }
    } catch (error) {
        alert('Lỗi kết nối.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>