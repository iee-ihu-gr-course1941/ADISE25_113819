CREATE DATABASE IF NOT EXISTS xeri_game CHARACTER SET utf8 COLLATE utf8_general_ci;
USE xeri_game;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status ENUM('waiting','playing','finished') DEFAULT 'waiting',
    current_player_id INT DEFAULT NULL,
    deck JSON NOT NULL,
    table_cards JSON NOT NULL,
    last_collector_id INT DEFAULT NULL,
    round INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (current_player_id) REFERENCES users(id),
    FOREIGN KEY (last_collector_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS game_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    user_id INT NOT NULL,
    position INT NOT NULL,
    hand JSON NOT NULL,
    collected JSON NOT NULL,
    xeri_count INT DEFAULT 0,
    xeri_jack_count INT DEFAULT 0,
    FOREIGN KEY (game_id) REFERENCES games(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_game_player (game_id, user_id)
);
