<?php
// file: api/config/database.php

class Database {
    // Thông tin CSDL
    private $host = 'localhost';        
    private $db_name = 'b4e_library'; 
    private $username = 'root';        
    private $password = '';       
    private $conn;

    public function connect() {
        $this->conn = null;

        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4';

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password);

            // Cài đặt các thuộc tính PDO để xử lý lỗi tốt hơn
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Cài đặt chế độ fetch mặc định là mảng kết hợp (associative array)
            // Điều này giúp chúng ta lấy dữ liệu dưới dạng $row['ten_cot']
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        } catch(PDOException $e) {
            echo 'Lỗi kết nối: ' . $e->getMessage();
        }

        return $this->conn;
    }
}
?>