<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

session_start();
require_once 'db.php';
require_once 'helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST required'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
$username = trim($body['username'] ?? '');

if ($username === '') {
    json_out(['error' => 'Username required'], 400);
}
if (!preg_match('/^[a-zA-Z0-9_]{2,30}$/', $username)) {
    json_out(['error' => 'Username must be 2-30 alphanumeric characters'], 400);
}

$stmt = $pdo->prepare("INSERT IGNORE INTO users (username) VALUES (?)");
$stmt->execute([$username]);

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

$_SESSION['user_id']  = $user['id'];
$_SESSION['username'] = $user['username'];

json_out(['status' => 'ok', 'user_id' => $user['id'], 'username' => $user['username']]);