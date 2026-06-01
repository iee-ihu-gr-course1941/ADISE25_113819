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

try {
    $deck        = make_deck();
    $table_cards = array_splice($deck, 0, 4);

    $stmt = $pdo->prepare(
        "INSERT INTO games (status, deck, table_cards) VALUES ('waiting', ?, ?)"
    );
    $stmt->execute([json_encode($deck), json_encode($table_cards)]);
    $game_id = $pdo->lastInsertId();

    $hand = array_splice($deck, 0, 6);

    $pdo->prepare("UPDATE games SET deck = ? WHERE id = ?")
        ->execute([json_encode($deck), $game_id]);

    $pdo->prepare(
        "INSERT INTO game_players (game_id, user_id, position, hand, collected) VALUES (?, ?, 0, ?, '[]')"
    )->execute([$game_id, $user['id'], json_encode($hand)]);

    json_out(['status' => 'ok', 'game_id' => $game_id]);

} catch (PDOException $e) {
    json_out(['error' => 'DB error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    json_out(['error' => 'Server error: ' . $e->getMessage()], 500);
}