<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->connect();

if (!$db) {
    die("Connection failed\n");
}

$categories = [1, 2, 3, 4, 5, 6, 7, 8];
$titles = [
    "Hành trình", "Bí ẩn", "Khám phá", "Nghệ thuật", "Sức mạnh",
    "Tâm hồn", "Kiến thức", "Thế giới", "Cuộc sống", "Tương lai",
    "Bóng đêm", "Ánh sáng", "Ký ức", "Ước mơ", "Khát vọng",
    "Đồng hành", "Chinh phục", "Thay đổi", "Thành công", "Hạnh phúc"
];
$suffixes = [
    "vô tận", "của bạn", "mới", "kỳ diệu", "vĩ đại",
    "xanh", "vàng", "thời gian", "không gian", "tri thức",
    "lòng người", "số phận", "ngày mai", "hôm nay", "mãi mãi"
];
$authors = [
    "Nguyễn Văn A", "Trần Thị B", "Lê Văn C", "Phạm Thị D", "Hoàng Văn E",
    "Vũ Thị F", "Đặng Văn G", "Bùi Thị H", "Lý Văn I", "Đỗ Thị K"
];

echo "Starting seeding 500 books...\n";

$sql = "INSERT INTO books (title, author, publisher, year, category_id, description, image_url, status) 
        VALUES (:title, :author, :publisher, :year, :category_id, :description, :image_url, :status)";
$stmt = $db->prepare($sql);

for ($i = 1; $i <= 500; $i++) {
    $title = $titles[array_rand($titles)] . " " . $suffixes[array_rand($suffixes)] . " Vol " . $i;
    $author = $authors[array_rand($authors)];
    $publisher = "NXB Giáo Dục";
    $year = rand(2000, 2024);
    $category_id = $categories[array_rand($categories)];
    $description = "Mô tả cho cuốn sách " . $title . ". Đây là dữ liệu mẫu được tự động tạo ra để kiểm tra hiệu năng hệ thống.";
    $image_url = "img/Book/default.png"; // Default image as requested
    $status = 'available';

    $stmt->execute([
        ':title' => $title,
        ':author' => $author,
        ':publisher' => $publisher,
        ':year' => $year,
        ':category_id' => $category_id,
        ':description' => $description,
        ':image_url' => $image_url,
        ':status' => $status
    ]);

    if ($i % 50 == 0) {
        echo "Inserted $i books...\n";
    }
}

echo "Seeding completed successfully!\n";
?>
