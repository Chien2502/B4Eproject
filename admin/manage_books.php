<?php
require_once 'includes/auth.php';
require_once 'includes/header.php';

// Lấy danh sách sách (Kèm tên thể loại)
$query = "SELECT b.*, c.name as category_name 
          FROM books b 
          LEFT JOIN categories c ON b.category_id = c.id 
          ORDER BY b.id DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link href="css/manage_books.css" rel="stylesheet">
<div class="content">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1>Quản lý Kho Sách</h1>
        <a href="book_form.php" class="btn btn-blue"><i class="fas fa-plus"></i> Thêm sách mới</a>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ảnh</th>
                    <th>Tên sách / Tác giả</th>
                    <th>Thể loại</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $book): ?>
                <tr id="row-<?php echo $book['id']; ?>">
                    <td>#<?php echo $book['id']; ?></td>
                    <td>
                        <img src="/B4Eproject/<?php echo htmlspecialchars($book['image_url'] ?? 'img/default.png'); ?>" 
                             style="width:40px; height:60px; object-fit:cover; border-radius:3px;">
                    </td>
                    <td>
                        <b><?php echo htmlspecialchars($book['title']); ?></b><br>
                        <small><?php echo htmlspecialchars($book['author']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($book['category_name'] ?? 'Chưa phân loại'); ?></td>
                    <td>
                        <?php if($book['status'] == 'available'): ?>
                            <span class="badge bg-green">Có sẵn</span>
                        <?php else: ?>
                            <span class="badge bg-yellow">Đang mượn</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="book_form.php?id=<?php echo $book['id']; ?>" class="btn btn-blue" title="Sửa">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button class="btn btn-red" onclick="deleteBook(<?php echo $book['id']; ?>)" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function deleteBook(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa cuốn sách này? Hành động này không thể hoàn tác.')) return;

    try {
        const response = await fetch('/api/books/delete.php', {
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