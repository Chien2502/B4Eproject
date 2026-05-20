<?php
require 'C:/xampp/htdocs/b4eproject/api/config/database.php';
$db = (new Database())->connect();
$stmt = $db->query('DESCRIBE books');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
?>
