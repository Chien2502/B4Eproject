<?php
// admin/includes/header.php
require_once __DIR__ . '/../../api/config/database.php'; // Đường dẫn tới file config
$database = new Database();
$conn = $database->connect();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Trang quản trị</title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="header">
            <span>Xin chào, <b><?php echo $_SESSION['username']; ?></b></span>
            <span style="font-size: 0.9rem; color: #888;">Quản trị viên</span>
        </div>