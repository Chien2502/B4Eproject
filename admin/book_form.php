<?php
require_once 'includes/auth.php';
require_once 'includes/header.php';

// Khởi tạo biến
$id = $_GET['id'] ?? null;
$book = [
    'title' => '', 'author' => '', 'category_id' => '', 
    'publisher' => '', 'year' => '', 'description' => '', 'image_url' => ''
];
$is_edit = false;
$message = '';

// 1. Nếu là Edit -> Lấy dữ liệu cũ
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$book) { echo "Sách không tồn tại!"; exit; }
    $is_edit = true;
}

// 2. Lấy danh sách Thể loại cho Select box
$categories = $conn->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);

// 3. XỬ LÝ FORM SUBMIT (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $category_id = $_POST['category_id'];
    $publisher = $_POST['publisher'];
    $year = $_POST['year'];
    $description = $_POST['description'];
    
    // Xử lý Upload Ảnh
    $image_path = "//src/"+$book['image_url']; // Mặc định giữ ảnh cũ
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "/src/img/Book/"; 
        // Tạo tên file mới để tránh trùng (timestamp_filename)
        $filename = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $filename;
        
        // Kiểm tra và di chuyển file
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_path = "/src/img/Book/" . $filename; // Đường dẫn lưu vào DB
        } else {
            $message = "<div style='color:red'>Lỗi upload ảnh!</div>";
        }
    }

    try {
        if ($is_edit) {
            // UPDATE
            $sql = "UPDATE books SET title=?, author=?, category_id=?, publisher=?, year=?, description=?, image_url=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$title, $author, $category_id, $publisher, $year, $description, $image_path, $id]);
            $message = "<div style='color:green; margin-bottom:15px;'>Cập nhật thành công! <a href='manage_books.php'>Quay lại danh sách</a></div>";
        } else {
            // INSERT (CREATE)
            $sql = "INSERT INTO books (title, author, category_id, publisher, year, description, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'available')";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$title, $author, $category_id, $publisher, $year, $description, $image_path]);
            $message = "<div style='color:green; margin-bottom:15px;'>Thêm sách mới thành công! <a href='manage_books.php'>Quay lại danh sách</a></div>";
            // Reset form nếu thêm mới thành công
            if(!$is_edit) $book = []; 
        }
        
        // Cập nhật lại biến $book để hiển thị trên form (nếu là edit)
        if($is_edit) {
            $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
            $stmt->execute([$id]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
        }

    } catch (Exception $e) {
        $message = "<div style='color:red'>Lỗi CSDL: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="content">
    <div style="max-width: 800px; margin: 0 auto;">
        <h1><?php echo $is_edit ? '✏️ Chỉnh sửa Sách' : '➕ Thêm Sách Mới'; ?></h1>
        
        <?php echo $message; ?>

        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Tên sách (*)</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($book['title'] ?? ''); ?>" required style="width:100%; padding:8px;">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label>Tác giả (*)</label>
                        <input type="text" name="author" value="<?php echo htmlspecialchars($book['author'] ?? ''); ?>" required style="width:100%; padding:8px;">
                    </div>
                    <div class="form-group">
                        <label>Thể loại</label>
                        <select name="category_id" style="width:100%; padding:8px;">
                            <option value="">-- Chọn thể loại --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo ($book['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-top:15px;">
                    <div class="form-group">
                        <label>Nhà xuất bản</label>
                        <input type="text" name="publisher" value="<?php echo htmlspecialchars($book['publisher'] ?? ''); ?>" style="width:100%; padding:8px;">
                    </div>
                    <div class="form-group">
                        <label>Năm xuất bản</label>
                        <input type="number" name="year" value="<?php echo htmlspecialchars($book['year'] ?? ''); ?>" style="width:100%; padding:8px;">
                    </div>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Mô tả</label>
                    <textarea name="description" rows="5" style="width:100%; padding:8px;"><?php echo htmlspecialchars($book['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label>Ảnh bìa</label><br>
                    <?php if (!empty($book['image_url'])): ?>
                        <img src="/B4Eproject/<?php echo htmlspecialchars($book['image_url']); ?>" style="width:100px; margin-bottom:10px;"><br>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*">
                    <p style="font-size:0.8rem; color:#666;">Để trống nếu không muốn thay đổi ảnh.</p>
                </div>

                <div style="margin-top:20px; text-align:right;">
                    <a href="manage_books.php" class="btn btn-red" style="margin-right:10px;">Hủy</a>
                    <button type="submit" class="btn btn-green">
                        <?php echo $is_edit ? 'Cập nhật' : 'Thêm mới'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>