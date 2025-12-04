<?php
echo "<h1>Đang tiến hành cài đặt hệ thống...</h1>";

$host = 'localhost';
$username = 'root';
$password = '';
$db_name = 'b4e_library';

try {
    // Kết nối đến MySQL Server
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql_create_db = "CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $pdo->exec($sql_create_db);
    echo "Running: Kiểm tra Database... <span style='color:green'>OK</span><br>";

    $pdo->exec("USE $db_name");

    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<h3 style='color:blue'>Hệ thống đã được cài đặt trước đó. Bỏ qua bước tạo bảng.</h3>";
        echo "<a href='../../index.html'>Về trang chủ</a>";
        exit;
    }

    $sql_file_path = __DIR__ . '/database.sql';
    
    if (!file_exists($sql_file_path)) {
        throw new Exception("Không tìm thấy file SQL tại: $sql_file_path");
    }

    $sql_content = file_get_contents($sql_file_path);

    $queries = explode(';', $sql_content);

    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }

    echo "Running: Tạo Bảng và Dữ liệu mẫu... <span style='color:green'>THÀNH CÔNG!</span><br>";
    echo "<hr>";
    echo "<h3>Cài đặt hoàn tất!</h3>";
    echo "<p>Tài khoản Admin: <b>admin@b4e.com</b> / <b>123456</b></p>";
    echo "<p>Tài khoản User: <b>test@b4e.com</b> / <b>123456</b></p>";
    echo "<a href='../../index.html'>Đi đến trang chủ</a>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>Lỗi CSDL: " . $e->getMessage() . "</h3>";
} catch (Exception $e) {
    echo "<h3 style='color:red'>Lỗi: " . $e->getMessage() . "</h3>";
}
?>