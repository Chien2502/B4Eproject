<?php
require_once 'includes/auth.php';
require_once 'includes/header.php';

// Query phức tạp: JOIN 3 bảng (borrowings, users, books)
// Sắp xếp: Ưu tiên trạng thái 'returning' lên đầu, sau đó đến 'borrowed', rồi mới đến ngày tháng.
$query = "SELECT br.*, u.username, u.email, u.phone, b.title as book_title, b.image_url 
          FROM borrowings br
          JOIN users u ON br.user_id = u.id
          JOIN books b ON br.book_id = b.id
          ORDER BY 
            CASE 
                WHEN br.status = 'returning' THEN 1 
                WHEN br.status = 'borrowed' THEN 2 
                WHEN br.status = 'overdue' THEN 3
                ELSE 4 
            END,
            br.due_date DESC";

$stmt = $conn->prepare($query);
$stmt->execute();
$borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content">
    <h1>Quản lý Mượn & Trả Sách</h1>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Người mượn
                        <br>
                        ID/Tên/SĐT
                    </th>
                    <th>Sách</th>
                    <th>Ngày mượn / Hạn trả</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($borrowings as $item): ?>
                <tr>
                    <td>
                        <b># <?php echo htmlspecialchars( $item['user_id']);?> </b><br>
                        <b><?php echo htmlspecialchars( $item['username']); ?></b><br>
                        <small><?php echo htmlspecialchars($item['phone'] ?? 'Chưa có SĐT'); ?></small>

                    </td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img src="../src/<?php echo htmlspecialchars($item['image_url']); ?>"
                                style="width:30px; height:45px; object-fit:cover; border-radius:3px;">
                            <span><?php echo htmlspecialchars($item['book_title']); ?></span>
                        </div>
                    </td>
                    <td>
                        Mượn: <?php echo date('d/m/Y', strtotime($item['borrow_date'])); ?><br>

                        <span style="color: #d9534f;">
                            Hạn: <?php echo date('d/m/Y', strtotime($item['due_date'])); ?><br>
                        </span>
                        Ngày trả: <?php echo date('d/m/Y', strtotime($item['return_date'])); ?>
                    </td>
                    <td>
                        <?php
    $badgeStyle = 'display: inline-block; padding: 5px 10px; border-radius: 15px; font-size: 0.85rem; font-weight: bold; color: white; margin-bottom: 4px;';

    switch($item['status']) {
        case 'returning':
            echo '<span class="badge" style="' . $badgeStyle . ' background-color: #3498db;">User báo đã trả</span>';
            break;
        case 'borrowed':
            echo '<span class="badge" style="' . $badgeStyle . ' background-color: #f39c12;">Đang mượn</span>';
            break;
        case 'returned':
            echo '<span class="badge" style="' . $badgeStyle . ' background-color: #27ae60;">Đã trả sách</span>';
            
            if (!empty($item['return_date'])) {
                echo '<br>'; 
                if (strtotime($item['return_date']) <= strtotime($item['due_date'])) {
                    echo '<span class="badge" style="' . $badgeStyle . ' background-color: #2ecc71; font-size: 0.75rem; margin-top: 2px;"> Đúng hạn </span>';
                } else {
                    echo '<span class="badge" style="' . $badgeStyle . ' background-color: #e74c3c; font-size: 0.75rem; margin-top: 2px;">  Trả muộn  </span>';
                }
            }
            break;
        case 'overdue':
            echo '<span class="badge" style="' . $badgeStyle . ' background-color: #c0392b;"> Quá hạn </span>';
            break;
    }
    ?>
                    </td>
                    <td>
                        <?php if ($item['status'] == 'returning' || $item['status'] == 'borrowed' || $item['status'] == 'overdue'): ?>
                        <button class="btn btn-green" onclick="confirmReturn(<?php echo $item['id']; ?>)">
                            <i class="fas fa-check-circle"></i> Xác nhận đã nhận sách
                        </button>
                        <?php else: ?>
                        <span style="color:#aaa;"><i class="fas fa-check"></i> Đã xử lí</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function confirmReturn(borrowId) {
    if (!confirm('Xác nhận bạn đã nhận được sách và sách còn nguyên vẹn? Hành động này sẽ cập nhật kho sách.'))
        return;

    try {
        const response = await fetch('../api/admin/confirm_return.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                borrow_id: borrowId
            })
        });

        const result = await response.json();

        if (response.ok) {
            alert(result.message);
            location.reload();
        } else {
            alert('Lỗi: ' + result.error);
        }
    } catch (error) {
        alert('Lỗi kết nối.');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>