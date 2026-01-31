-- =====================================================
-- BASE DE DONNÉES POUR LE SITE D'ÉCHECS EN LIGNE
-- =====================================================

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS echec_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE echec_db;

-- =====================================================
-- TABLE: users - Utilisateurs du site
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255) DEFAULT 'default.png',
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    wins INT DEFAULT 0,
    losses INT DEFAULT 0,
    draws INT DEFAULT 0,
    abandons INT DEFAULT 0,
    is_online BOOLEAN DEFAULT FALSE,
    INDEX idx_username (username),
    INDEX idx_is_online (is_online)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: active_sessions - Sessions actives
-- =====================================================
CREATE TABLE IF NOT EXISTS active_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: games - Parties d'échecs
-- =====================================================
CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    white_player_id INT NULL,
    black_player_id INT NULL,
    is_vs_computer BOOLEAN DEFAULT FALSE,
    board_state TEXT NOT NULL,
    current_turn ENUM('white', 'black') DEFAULT 'white',
    status ENUM('waiting', 'active', 'paused', 'completed', 'abandoned') DEFAULT 'waiting',
    winner_id INT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    pause_requested_by INT NULL,
    pause_accepted BOOLEAN DEFAULT FALSE,
    paused_at TIMESTAMP NULL,
    resume_scheduled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (white_player_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (black_player_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (pause_requested_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_is_public (is_public),
    INDEX idx_players (white_player_id, black_player_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: moves - Historique des coups
-- =====================================================
CREATE TABLE IF NOT EXISTS moves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    player_id INT NULL,
    move_notation VARCHAR(20) NOT NULL,
    from_position VARCHAR(5) NOT NULL,
    to_position VARCHAR(5) NOT NULL,
    piece_type VARCHAR(20) NOT NULL,
    captured_piece VARCHAR(20) NULL,
    is_check BOOLEAN DEFAULT FALSE,
    is_checkmate BOOLEAN DEFAULT FALSE,
    move_number INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_game_id (game_id),
    INDEX idx_move_number (game_id, move_number)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: messages - Messages de chat (partie et privé)
-- =====================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NULL,
    game_id INT NULL,
    content TEXT NOT NULL,
    is_emoji_rain BOOLEAN DEFAULT FALSE,
    emoji_type VARCHAR(50) NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    INDEX idx_sender_receiver (sender_id, receiver_id),
    INDEX idx_game_id (game_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: game_invitations - Invitations à jouer
-- =====================================================
CREATE TABLE IF NOT EXISTS game_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending',
    game_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL,
    INDEX idx_receiver_status (receiver_id, status),
    INDEX idx_sender_id (sender_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: publications - Publications automatiques
-- =====================================================
CREATE TABLE IF NOT EXISTS publications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_id INT NULL,
    content TEXT NOT NULL,
    publication_type ENUM('victory', 'defeat', 'draw', 'custom') DEFAULT 'custom',
    opponent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE SET NULL,
    FOREIGN KEY (opponent_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: pause_requests - Demandes de pause
-- =====================================================
CREATE TABLE IF NOT EXISTS pause_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    requester_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    requested_resume_at TIMESTAMP NULL,
    message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_game_status (game_id, status)
) ENGINE=InnoDB;

-- =====================================================
-- TABLE: spectators - Spectateurs des parties publiques
-- =====================================================
CREATE TABLE IF NOT EXISTS spectators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_spectator (game_id, user_id),
    INDEX idx_game_id (game_id)
) ENGINE=InnoDB;

-- =====================================================
-- Insertion d'un utilisateur par défaut pour les tests
-- =====================================================
-- INSERT INTO users (username, email, password) VALUES 
-- ('testuser', 'test@example.com', '$2y$10$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
