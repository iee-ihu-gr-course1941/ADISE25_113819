<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'helpers.php';
$user = require_login();
require_once 'db.php';

$stmt = $pdo->prepare(
    "SELECT g.id, g.status, g.created_at,
            u.username AS creator,
            EXISTS(SELECT 1 FROM game_players p2 WHERE p2.game_id = g.id AND p2.user_id = ?) AS is_mine
     FROM games g
     JOIN game_players gp ON gp.game_id = g.id AND gp.position = 0
     JOIN users u ON u.id = gp.user_id
     WHERE g.status IN ('waiting','playing')
     ORDER BY g.created_at DESC
     LIMIT 20"
);
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();
foreach ($rows as &$r) $r['is_mine'] = (bool)$r['is_mine'];
unset($r);
json_out($rows);