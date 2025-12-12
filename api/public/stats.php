<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->connect();

    $books = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();

    $users = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

    $donors = $db->query("SELECT COUNT(DISTINCT user_id) FROM donations")->fetchColumn();

    $borrows = $db->query("SELECT COUNT(*) FROM borrowings")->fetchColumn();

    echo json_encode([
        'books' => $books,
        'users' => $users,
        'donors' => $donors,
        'borrows' => $borrows
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>