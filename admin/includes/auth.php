<?php
session_start();
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['admin', 'super-admin'])) {
    // Chuyển hướng về trang login chính
    echo("Bạn không có quyền truy cập vào trang này, vui lòng đăng nhập!");
    header('Location: /src/login.html'); 
    exit;
}
?>