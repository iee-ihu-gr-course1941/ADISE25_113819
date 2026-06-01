<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once 'helpers.php';
$user = require_login();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST required'], 405);
}

$body    = json_decode(file_get_contents('php://input'), true);
$game_id = (int)($body['game_id'] ?? 0);

if (!$game_id) json_out(['error' => 'game_id required'], 400);

$game = $pdo->prepare("SELECT * FROM games WHERE id = ? FOR UPDATE");
$pdo->beginTransaction();
$game->execute([$game_id]);
$game = $game->fetch();

if (!$game) {
    $pdo->rollBack();
    json_out(['error' => 'Game not found'], 404);
}
if ($game['status'] !== 'waiting') {
    $pdo->rollBack();
    json_out(['error' => 'Game is not waiting for players'], 400);
}

// Check not already in game
$check = $pdo->prepare("SELECT id FROM game_players WHERE game_id = ? AND user_id = ?");
$check->execute([$game_id, $user['id']]);
if ($check->fetch()) {
    $pdo->rollBack();
    json_out(['error' => 'Already in this game'], 400);
}

// Check game not full (max 2 players)
$count = $pdo->prepare("SELECT COUNT(*) as c FROM game_players WHERE game_id = ?");
$count->execute([$game_id]);
if ($count->fetch()['c'] >= 2) {
    $pdo->rollBack();
    json_out(['error' => 'Game is full'], 400);
}

$deck = json_decode($game['deck'], true);

// Deal 6 cards to joining player
$hand = array_splice($deck, 0, 6);

$pdo->prepare(
    "INSERT INTO game_players (game_id, user_id, position, hand, collected) VALUES (?, ?, 1, ?, '[]')"
)->execute([$game_id, $user['id'], json_encode($hand)]);

// Get player 0 to set as first turn
$p0 = $pdo->prepare("SELECT user_id FROM game_players WHERE game_id = ? AND position = 0");
$p0->execute([$game_id]);
$p0 = $p0->fetch();

$pdo->prepare("UPDATE games SET status = 'playing', current_player_id = ?, deck = ? WHERE id = ?")
    ->execute([$p0['user_id'], json_encode($deck), $game_id]);

$pdo->commit();

json_out(['status' => 'ok', 'game_id' => $game_id]);