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
    $image_path = $book['image_url']; // Mặc định giữ ảnh cũ
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../src/img/Book/"; // Lưu vào thư mục src/img/Book
        // Tạo tên file mới để tránh trùng (timestamp_filename)
        $filename = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $filename;
        
        // Kiểm tra và di chuyển file
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_path = "img/Book/" . $filename; // Đường dẫn lưu vào DB
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
        <h1><?php echo $is_edit ? 'Chỉnh sửa Sách' : 'Thêm Sách Mới'; ?></h1>

        <?php echo $message; ?>

        <div class="card">
            <form method="POST" enctype="multipart/form-data" class="form-grid">

                <div class="form-group full-width">
                    <label>Tên sách (*)</label>
                    <input type="text" name="title" class="form-control" required value="...">
                </div>

                <div class="form-group">
                    <label>Tác giả (*)</label>
                    <input type="text" name="author" class="form-control" required value="...">
                </div>

                <div class="form-group">
                    <label>Thể loại</label>
                    <div class="input-group">
                        <select id="categorySelect" name="category_id" class="form-control">
                            <option value="">-- Chọn thể loại --</option>
                        </select>
                        <button type="button" class="btn btn-blue" onclick="openCatModal()">+</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Nhà xuất bản</label>
                    <input type="text" name="publisher" class="form-control" value="...">
                </div>

                <div class="form-group">
                    <label>Năm xuất bản</label>
                    <input type="number" name="year" class="form-control" value="...">
                </div>

                <div class="form-group full-width">
                    <label>Mô tả</label>
                    <textarea name="description" rows="5" class="form-control">...</textarea>
                </div>

                <div class="form-group full-width">
                    <label>Ảnh bìa</label>
                    <div class="file-upload-wrapper">
                        <input type="file" name="image" accept="image/*">
                        <p style="font-size: 0.8rem; color: #888; margin-top: 5px;">Để trống nếu không muốn thay đổi
                            ảnh.</p>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="manage_books.php" class="btn btn-red">Hủy</a>
                    <button type="submit" class="btn btn-green">Thêm mới</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div id="catModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:white; padding:20px; border-radius:5px; width:300px; box-shadow:0 0 10px rgba(0,0,0,0.3);">
        <h3>Thêm Thể loại Mới</h3>
        <input type="text" id="newCatName" placeholder="Nhập tên thể loại..."
            style="width:100%; padding:8px; margin:10px 0; border:1px solid #ccc;">
        <div style="text-align:right;">
            <button type="button" class="btn btn-red" onclick="closeCatModal()">Hủy</button>
            <button type="button" class="btn btn-green" onclick="saveCategory()">Lưu</button>
        </div>
    </div>
</div>

<script>
function openCatModal() {
    document.getElementById('catModal').style.display = 'flex';
    document.getElementById('newCatName').focus();
}

function closeCatModal() {
    document.getElementById('catModal').style.display = 'none';
    document.getElementById('newCatName').value = ''; // Reset input
}

async function saveCategory() {
    const name = document.getElementById('newCatName').value.trim();
    if (!name) {
        alert('Vui lòng nhập tên thể loại!');
        return;
    }

    try {
        const response = await fetch('/api/categories/create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                name: name
            })
        });

        const result = await response.json();

        if (response.ok) {
            alert(result.message);

            const select = document.getElementById('categorySelect');

            const option = document.createElement('option');
            option.value = result.id;
            option.text = result.name;
            option.selected = true;

            select.appendChild(option);

            closeCatModal();
        } else {
            alert('Lỗi: ' + result.error);
        }
    } catch (error) {
        console.error(error);
        alert('Lỗi kết nối server.');
    }
}
</script>
<?php require_once 'includes/footer.php'; ?>