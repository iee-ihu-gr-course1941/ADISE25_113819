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
$action  = $body['action'] ?? '';  // 'throw' or 'pickup'
$card    = $body['card']   ?? null; // card from hand: {suit, value}

if (!$game_id || !$action || !$card) {
    json_out(['error' => 'game_id, action, and card are required'], 400);
}

$pdo->beginTransaction();

// Load game with lock
$game = $pdo->prepare("SELECT * FROM games WHERE id = ? FOR UPDATE");
$game->execute([$game_id]);
$game = $game->fetch();

if (!$game) { $pdo->rollBack(); json_out(['error' => 'Game not found'], 404); }
if ($game['status'] !== 'playing') { $pdo->rollBack(); json_out(['error' => 'Game not in progress'], 400); }
if ((int)$game['current_player_id'] !== (int)$user['id']) { $pdo->rollBack(); json_out(['error' => 'Not your turn'], 403); }

// Load my player row
$me = $pdo->prepare("SELECT * FROM game_players WHERE game_id = ? AND user_id = ? FOR UPDATE");
$me->execute([$game_id, $user['id']]);
$me = $me->fetch();
if (!$me) { $pdo->rollBack(); json_out(['error' => 'You are not in this game'], 403); }

// Load opponent
$opp = $pdo->prepare("SELECT * FROM game_players WHERE game_id = ? AND user_id != ? FOR UPDATE");
$opp->execute([$game_id, $user['id']]);
$opp = $opp->fetch();

$my_hand     = json_decode($me['hand'],     true);
$my_coll     = json_decode($me['collected'], true);
$table_cards = json_decode($game['table_cards'], true);
$deck        = json_decode($game['deck'],   true);

// Find the card in hand
$card_idx = null;
foreach ($my_hand as $i => $c) {
    if ($c['suit'] === $card['suit'] && $c['value'] === $card['value']) {
        $card_idx = $i;
        break;
    }
}
if ($card_idx === null) { $pdo->rollBack(); json_out(['error' => 'Card not in your hand'], 400); }

$played_card = $my_hand[$card_idx];
$result_msg  = '';
$xeri        = false;
$xeri_jack   = false;

if ($action === 'throw') {
    // Remove card from hand, add to top of table pile
    array_splice($my_hand, $card_idx, 1);
    $table_cards[] = $played_card;
    $result_msg = 'Card thrown to table';

} elseif ($action === 'pickup') {
    if (count($table_cards) === 0) { $pdo->rollBack(); json_out(['error' => 'No cards on table'], 400); }

    $table_top = end($table_cards);

    $can_pickup = is_jack($played_card) || cards_match($played_card, $table_top);
    if (!$can_pickup) { $pdo->rollBack(); json_out(['error' => 'Card does not match table top and is not a Jack'], 400); }

    // Detect Ξερή: exactly 1 card on table
    if (count($table_cards) === 1) {
        $xeri = true;
        if (is_jack($played_card)) $xeri_jack = true;
    }

    // Collect all table cards + played card
    array_splice($my_hand, $card_idx, 1);
    $all_collected = array_merge($table_cards, [$played_card]);
    $my_coll       = array_merge($my_coll, $all_collected);
    $table_cards   = [];

    $xeri_inc      = 0;
    $xeri_jack_inc = 0;
    if ($xeri_jack) { $xeri_jack_inc = 1; $result_msg = 'ΞΕΡΗ ΜΕ ΒΑΛΕ! +20 πόντοι'; }
    elseif ($xeri)  { $xeri_inc      = 1; $result_msg = 'ΞΕΡΗ! +10 πόντοι'; }
    else            { $result_msg = 'Picked up table cards'; }

    $pdo->prepare("UPDATE game_players SET collected = ?, xeri_count = xeri_count + ?, xeri_jack_count = xeri_jack_count + ? WHERE game_id = ? AND user_id = ?")
        ->execute([json_encode($my_coll), $xeri_inc, $xeri_jack_inc, $game_id, $user['id']]);

    // Update last collector
    $pdo->prepare("UPDATE games SET last_collector_id = ? WHERE id = ?")
        ->execute([$user['id'], $game_id]);

} else {
    $pdo->rollBack();
    json_out(['error' => 'action must be throw or pickup'], 400);
}

// Save hand
$pdo->prepare("UPDATE game_players SET hand = ? WHERE game_id = ? AND user_id = ?")
    ->execute([json_encode($my_hand), $game_id, $user['id']]);

// Determine next player
$opp_hand = $opp ? json_decode($opp['hand'], true) : [];
$next_player_id = $opp ? (int)$opp['user_id'] : (int)$user['id'];

// If both hands empty, deal new round or end game
$status = 'playing';
if (count($my_hand) === 0 && count($opp_hand) === 0) {
    if (count($deck) >= 12) {
        // Deal 6 to each
        $p0_hand = array_splice($deck, 0, 6);
        $p1_hand = array_splice($deck, 0, 6);

        $positions = $pdo->prepare("SELECT user_id, position FROM game_players WHERE game_id = ? ORDER BY position");
        $positions->execute([$game_id]);
        $pos_rows = $positions->fetchAll();
        foreach ($pos_rows as $pr) {
            $new_hand = $pr['position'] === 0 ? $p0_hand : $p1_hand;
            $pdo->prepare("UPDATE game_players SET hand = ? WHERE game_id = ? AND user_id = ?")
                ->execute([json_encode($new_hand), $game_id, $pr['user_id']]);
        }

        $pdo->prepare("UPDATE games SET round = round + 1 WHERE id = ?")
            ->execute([$game_id]);
        $result_msg .= ' | New round dealt';
    } else {
        // Deck exhausted: give remaining table cards to last collector
        if (count($table_cards) > 0) {
            $last_id = (int)$game['last_collector_id'];
            if (!$last_id) $last_id = (int)$user['id'];
            $last_coll_stmt = $pdo->prepare("SELECT collected FROM game_players WHERE game_id = ? AND user_id = ?");
            $last_coll_stmt->execute([$game_id, $last_id]);
            $last_row = $last_coll_stmt->fetch();
            $last_coll = json_decode($last_row['collected'], true);
            $last_coll = array_merge($last_coll, $table_cards);
            $pdo->prepare("UPDATE game_players SET collected = ? WHERE game_id = ? AND user_id = ?")
                ->execute([json_encode($last_coll), $game_id, $last_id]);
            $table_cards = [];
        }
        $status = 'finished';
    }
}

// Update game row
$pdo->prepare("UPDATE games SET deck = ?, table_cards = ?, current_player_id = ?, status = ? WHERE id = ?")
    ->execute([json_encode($deck), json_encode($table_cards), $next_player_id, $status, $game_id]);

$pdo->commit();

$response = ['status' => 'ok', 'message' => $result_msg, 'xeri' => $xeri, 'xeri_jack' => $xeri_jack, 'game_status' => $status];
if ($status === 'finished') {
    $response['scores'] = calculate_scores($pdo, $game_id);
}
json_out($response);