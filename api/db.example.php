<?php
// Copy this file to db.php and fill in your credentials
$host    = 'localhost';
$dbname  = 'xeri_game';
$db_user = 'root';
$db_pass = '';

// For users.iee.ihu.gr, connect via socket instead of TCP:
// $pdo = new PDO("mysql:unix_socket=/home/YOURUSERNAME/mysql/run/mysql.sock;dbname=$dbname;charset=utf8", $db_user, $db_pass);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}