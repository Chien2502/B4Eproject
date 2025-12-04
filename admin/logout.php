<?php
session_start();
session_destroy(); // Hủy session PHP
header('Location: /src/index.html'); 
?>