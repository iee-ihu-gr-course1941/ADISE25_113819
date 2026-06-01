<?php

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function require_login() {
    session_start();
    if (empty($_SESSION['user_id'])) {
        json_out(['error' => 'Not logged in'], 401);
    }
    return ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username']];
}

function make_deck() {
    $suits  = ['S', 'H', 'D', 'C'];
    $values = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
    $deck   = [];
    foreach ($suits as $s) {
        foreach ($values as $v) {
            $deck[] = ['suit' => $s, 'value' => $v];
        }
    }
    shuffle($deck);
    return $deck;
}

function card_display($card) {
    if ($card === null) return 'empty';
    $vals = [1=>'A',11=>'J',12=>'Q',13=>'K'];
    $v = isset($vals[$card['value']]) ? $vals[$card['value']] : $card['value'];
    $suits = ['S'=>'♠','H'=>'♥','D'=>'♦','C'=>'♣'];
    $s = $suits[$card['suit']] ?? $card['suit'];
    return $v . $s;
}

function cards_match($a, $b) {
    return (int)$a['value'] === (int)$b['value'];
}

function is_jack($card) {
    return (int)$card['value'] === 11;
}

function deal_to_players($pdo, $game_id, $deck) {
    $players = $pdo->prepare("SELECT user_id FROM game_players WHERE game_id = ? ORDER BY position");
    $players->execute([$game_id]);
    $rows = $players->fetchAll();

    $updated_deck = $deck;
    foreach ($rows as $row) {
        $hand = array_splice($updated_deck, 0, 6);
        $pdo->prepare("UPDATE game_players SET hand = ? WHERE game_id = ? AND user_id = ?")
            ->execute([json_encode($hand), $game_id, $row['user_id']]);
    }
    return $updated_deck;
}

function calculate_scores($pdo, $game_id) {
    $stmt = $pdo->prepare(
        "SELECT gp.user_id, gp.collected, gp.xeri_count, gp.xeri_jack_count, u.username
         FROM game_players gp
         JOIN users u ON u.id = gp.user_id
         WHERE gp.game_id = ?"
    );
    $stmt->execute([$game_id]);
    $players = $stmt->fetchAll();

    $data = [];
    foreach ($players as $p) {
        $collected = json_decode($p['collected'], true);
        $count     = count($collected);
        $has_2S    = false;
        $has_10D   = false;
        $face10    = 0;
        foreach ($collected as $card) {
            if ($card['suit'] === 'S' && (int)$card['value'] === 2)  $has_2S  = true;
            if ($card['suit'] === 'D' && (int)$card['value'] === 10) $has_10D = true;
            if (in_array((int)$card['value'], [11, 12, 13]))          $face10++;
            if ((int)$card['value'] === 10 && $card['suit'] !== 'D') $face10++;
        }
        $data[] = [
            'user_id'    => $p['user_id'],
            'username'   => $p['username'],
            'card_count' => $count,
            'has_2S'     => $has_2S,
            'has_10D'    => $has_10D,
            'face10'     => $face10,
            'xeri_count' => (int)$p['xeri_count'],
            'xeri_jack'  => (int)$p['xeri_jack_count'],
        ];
    }

    $max_cards = max(array_column($data, 'card_count'));
    $winners   = array_filter($data, fn($p) => $p['card_count'] === $max_cards);

    foreach ($data as &$p) {
        $score = 0;
        if (count($winners) === 1 && $p['card_count'] === $max_cards) $score += 3;
        if ($p['has_2S'])  $score += 1;
        if ($p['has_10D']) $score += 1;
        $score += $p['face10'];
        $score += $p['xeri_count'] * 10;
        $score += $p['xeri_jack']  * 20;
        $p['score'] = $score;
    }
    unset($p);

    return $data;
}