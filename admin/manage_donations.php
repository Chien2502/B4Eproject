<?php
// admin/manage_donations.php
require_once 'includes/auth.php';
require_once 'includes/header.php';

// Lấy danh sách đang chờ (Pending)
// JOIN với bảng users để lấy tên người quyên góp
$query = "SELECT d.*, u.username, u.email 
          FROM donations d 
          JOIN users u ON d.user_id = u.id 
          WHERE d.status = 'pending' 
          ORDER BY d.created_at ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<link href="css/manage_donations.css" rel="stylesheet">
<div class="content">
    <h1>Duyệt Quyên Góp Sách</h1>
    <p>Danh sách các sách đang chờ xác nhận nhập kho.</p>

    <?php if (count($donations) > 0): ?>
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Người gửi</th>
                    <th>Thông tin Sách</th>
                    <th>Tình trạng</th>
                    <th>Hình thức</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($donations as $item): ?>
                <tr id="row-<?php echo $item['id']; ?>">
                    <td>
                        <b><?php echo htmlspecialchars($item['username']); ?></b><br>
                        <small><?php echo htmlspecialchars($item['email']); ?></small>
                    </td>
                    <td>
                        <span style="color:#007bff; font-weight:bold; font-size:1.1rem;">
                            <?php echo htmlspecialchars($item['book_title']); ?>
                        </span><br>
                        TG: <?php echo htmlspecialchars($item['book_author']); ?><br>
                        <small>NXB: <?php echo htmlspecialchars($item['book_publisher'] ?? 'N/A'); ?> (<?php echo $item['book_year'] ?? '-'; ?>)</small>
                    </td>
                    <td>
                        <span class="badge bg-yellow"><?php echo htmlspecialchars($item['book_condition']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($item['donation_type']); ?></td>
                    <td>
                        <button class="btn btn-green" onclick="processDonation(<?php echo $item['id']; ?>, 'approve')">
                            <i class="fas fa-check"></i> Tiếp nhận
                        </button>
                        <button class="btn btn-red" onclick="processDonation(<?php echo $item['id']; ?>, 'reject')">
                            <i class="fas fa-times"></i> Từ chối
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="card" style="text-align:center; padding:50px;">
            <i class="fas fa-check-circle" style="font-size:3rem; color:#28a745;"></i>
            <h3>Tuyệt vời! Không có yêu cầu nào đang chờ.</h3>
        </div>
    <?php endif; ?>
</div>

<script>
async function processDonation(id, action) {
    const confirmMsg = action === 'approve' 
        ? 'Bạn có chắc chắn muốn DUYỆT sách này? Nó sẽ được thêm vào kho sách ngay lập tức.' 
        : 'Bạn có chắc chắn muốn TỪ CHỐI yêu cầu này?';

    if (!confirm(confirmMsg)) return;

    // Xác định API endpoint
    const apiEndpoint = action === 'approve' 
        ? '/api/admin/approve_donation.php' 
        : '/api/admin/reject_donation.php';

    try {
        const response = await fetch(apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ donation_id: id })
        });

        const result = await response.json();

        if (response.ok) {
            alert(result.message);
            // Xóa dòng tương ứng khỏi bảng (Hiệu ứng UI tức thì)
            const row = document.getElementById('row-' + id);
            if (row) {
                row.style.transition = 'all 0.5s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 500);
            }
            // Nếu bảng trống thì reload để hiện thông báo "Không có yêu cầu"
            if (document.querySelectorAll('tbody tr').length <= 1) {
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            alert('Lỗi: ' + result.error);
        }
    } catch (error) {
        console.error(error);
        alert('Lỗi kết nối đến server.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>