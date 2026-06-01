<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'helpers.php';
$user = require_login();
require_once 'db.php';

$game_id = (int)($_GET['game_id'] ?? 0);
if (!$game_id) json_out(['error' => 'game_id required'], 400);

$game = $pdo->prepare("SELECT * FROM games WHERE id = ?");
$game->execute([$game_id]);
$game = $game->fetch();
if (!$game) json_out(['error' => 'Game not found'], 404);

$players_stmt = $pdo->prepare(
    "SELECT gp.*, u.username FROM game_players gp
     JOIN users u ON u.id = gp.user_id
     WHERE gp.game_id = ? ORDER BY gp.position"
);
$players_stmt->execute([$game_id]);
$players = $players_stmt->fetchAll();

$me  = null;
$opp = null;
foreach ($players as $p) {
    if ($p['user_id'] == $user['id']) $me  = $p;
    else                              $opp = $p;
}

if (!$me) json_out(['error' => 'You are not in this game'], 403);

$table_cards = json_decode($game['table_cards'], true);
$table_top   = count($table_cards) > 0 ? end($table_cards) : null;

$my_collected  = json_decode($me['collected'],  true);
$my_hand       = json_decode($me['hand'],       true);

function collected_info($cards) {
    $has_2S = false; $has_10D = false; $face10 = 0;
    foreach ($cards as $c) {
        if ($c['suit'] === 'S' && (int)$c['value'] === 2)  $has_2S  = true;
        if ($c['suit'] === 'D' && (int)$c['value'] === 10) $has_10D = true;
        if (in_array((int)$c['value'], [11,12,13]))           $face10++;
        if ((int)$c['value'] === 10 && $c['suit'] !== 'D')  $face10++;
    }
    return ['count' => count($cards), 'has_2spades' => $has_2S, 'has_10diamonds' => $has_10D, 'face10_count' => $face10];
}

$response = [
    'game_id'            => (int)$game['id'],
    'status'             => $game['status'],
    'round'              => (int)$game['round'],
    'current_player_id'  => (int)$game['current_player_id'],
    'is_my_turn'         => (int)$game['current_player_id'] === (int)$user['id'],
    'deck_count'         => count(json_decode($game['deck'], true)),
    'table_top'          => $table_top,
    'table_count'        => count($table_cards),
    'me' => [
        'id'              => (int)$me['user_id'],
        'username'        => $me['username'],
        'hand'            => $my_hand,
        'collected'       => collected_info($my_collected),
        'xeri_count'      => (int)$me['xeri_count'],
        'xeri_jack_count' => (int)$me['xeri_jack_count'],
    ],
];

if ($opp) {
    $opp_collected = json_decode($opp['collected'], true);
    $response['opponent'] = [
        'id'              => (int)$opp['user_id'],
        'username'        => $opp['username'],
        'hand_count'      => count(json_decode($opp['hand'], true)),
        'collected'       => collected_info($opp_collected),
        'xeri_count'      => (int)$opp['xeri_count'],
        'xeri_jack_count' => (int)$opp['xeri_jack_count'],
    ];
} else {
    $response['opponent'] = null;
}

if ($game['status'] === 'finished') {
    $scores = calculate_scores($pdo, $game_id);
    $response['scores'] = $scores;
}

json_out($response);