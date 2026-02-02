<?php
/**
 * API pour la gestion des parties d'échecs
 */
require_once '../config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        createGame();
        break;
    case 'get':
        getGame();
        break;
    case 'move':
        makeMove();
        break;
    case 'abandon':
        abandonGame();
        break;
    case 'toggle_public':
        togglePublic();
        break;
    case 'public_games':
        getPublicGames();
        break;
    case 'request_pause':
        requestPause();
        break;
    case 'respond_pause':
        respondToPause();
        break;
    case 'resume':
        resumeGame();
        break;
    case 'my_games':
        getMyGames();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Action non valide'], 400);
}

/**
 * Créer une nouvelle partie
 */
function createGame() {
    $pdo = getDbConnection();
    $userId = getCurrentUser();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $isVsComputer = $input['vs_computer'] ?? false;
    $opponentId = $input['opponent_id'] ?? null;
    $isPublic = $input['is_public'] ?? false;
    
    // Mode contre ordinateur - pas besoin d'être connecté
    if ($isVsComputer) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO games (white_player_id, is_vs_computer, board_state, status, is_public) 
                VALUES (?, TRUE, ?, 'active', ?)
            ");
            // $userId peut être NULL pour les joueurs non connectés
            $stmt->execute([$userId, getInitialBoardState(), $isPublic ? 1 : 0]);
            $gameId = $pdo->lastInsertId();
            
            jsonResponse([
                'success' => true,
                'game_id' => $gameId,
                'message' => 'Partie contre l\'ordinateur créée'
            ]);
        } catch (PDOException $e) {
            jsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la création de la partie: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Mode en ligne - connexion requise
    if (!$userId) {
        jsonResponse(['success' => false, 'error' => 'Connexion requise pour jouer en ligne'], 401);
    }
    
    // Créer une partie en attente ou avec un adversaire
    $status = $opponentId ? 'active' : 'waiting';
    
    $stmt = $pdo->prepare("
        INSERT INTO games (white_player_id, black_player_id, board_state, status, is_public) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $opponentId, getInitialBoardState(), $status, $isPublic]);
    $gameId = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true,
        'game_id' => $gameId,
        'status' => $status,
        'message' => $opponentId ? 'Partie lancée' : 'En attente d\'un adversaire'
    ]);
}

/**
 * Obtenir l'état d'une partie
 */
function getGame() {
    $pdo = getDbConnection();
    $gameId = $_GET['game_id'] ?? null;
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'error' => 'ID de partie requis'], 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT g.*, 
            w.username as white_username, w.profile_photo as white_photo,
            b.username as black_username, b.profile_photo as black_photo,
            pr.status as pause_status, pr.requester_id as pause_requester,
            pr.requested_resume_at
        FROM games g
        LEFT JOIN users w ON g.white_player_id = w.id
        LEFT JOIN users b ON g.black_player_id = b.id
        LEFT JOIN pause_requests pr ON g.id = pr.game_id AND pr.status = 'pending'
        WHERE g.id = ?
    ");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    
    if (!$game) {
        jsonResponse(['success' => false, 'error' => 'Partie non trouvée'], 404);
    }
    
    // Récupérer les derniers coups
    $stmt = $pdo->prepare("
        SELECT * FROM moves WHERE game_id = ? ORDER BY move_number DESC LIMIT 10
    ");
    $stmt->execute([$gameId]);
    $moves = $stmt->fetchAll();
    
    // Nombre de spectateurs si public
    $spectatorCount = 0;
    if ($game['is_public']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM spectators WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $spectatorCount = $stmt->fetchColumn();
    }
    
    jsonResponse([
        'success' => true,
        'game' => $game,
        'moves' => array_reverse($moves),
        'spectator_count' => $spectatorCount
    ]);
}

/**
 * Effectuer un coup
 */
function makeMove() {
    try {
        $pdo = getDbConnection();
        $userId = getCurrentUser();
        
        $input = json_decode(file_get_contents('php://input'), true);
        $gameId = $input['game_id'] ?? null;
        $from = $input['from'] ?? null;
        $to = $input['to'] ?? null;
        $pieceType = $input['piece_type'] ?? null;
        $newBoardState = $input['board_state'] ?? null;
        $capturedPiece = $input['captured_piece'] ?? null;
        // Convertir en entiers pour MySQL (les booléens ou chaînes vides causent des erreurs)
        $isCheck = !empty($input['is_check']) ? 1 : 0;
        $isCheckmate = !empty($input['is_checkmate']) ? 1 : 0;
        $isDraw = !empty($input['is_draw']) ? 1 : 0;
        $promotionPiece = $input['promotion_piece'] ?? null;
        
        if (!$gameId || !$from || !$to || !$newBoardState) {
            jsonResponse(['success' => false, 'error' => 'Données manquantes'], 400);
        }
        
        // Récupérer la partie
        $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();
        
        if (!$game) {
            jsonResponse(['success' => false, 'error' => 'Partie non trouvée'], 404);
        }
        
        if ($game['status'] !== 'active') {
            jsonResponse(['success' => false, 'error' => 'Cette partie n\'est pas active'], 400);
        }
        
        // Vérifier que c'est le tour du joueur (sauf vs ordinateur)
        if (!$game['is_vs_computer']) {
            $isWhitePlayer = $game['white_player_id'] == $userId;
            $isBlackPlayer = $game['black_player_id'] == $userId;
            
            if (!$isWhitePlayer && !$isBlackPlayer) {
                jsonResponse(['success' => false, 'error' => 'Vous n\'êtes pas dans cette partie'], 403);
            }
            
            if (($game['current_turn'] === 'white' && !$isWhitePlayer) ||
                ($game['current_turn'] === 'black' && !$isBlackPlayer)) {
                jsonResponse(['success' => false, 'error' => 'Ce n\'est pas votre tour'], 400);
            }
        }
        
        // Compter le numéro du coup
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM moves WHERE game_id = ?");
        $stmt->execute([$gameId]);
        $moveNumber = $stmt->fetchColumn() + 1;
        
        // Extraire le type de pièce (ex: "white_pawn" -> "pawn", ou "pawn" -> "pawn")
        $pieceTypeName = $pieceType;
        if ($pieceType && strpos($pieceType, '_') !== false) {
            $parts = explode('_', $pieceType);
            $pieceTypeName = end($parts); // Prend la dernière partie (le type)
        }
        
        // Créer la notation du coup (P=pawn, R=rook, N=knight, B=bishop, Q=queen, K=king)
        $pieceNotations = [
            'pawn' => 'P', 'rook' => 'R', 'knight' => 'N', 
            'bishop' => 'B', 'queen' => 'Q', 'king' => 'K'
        ];
        $pieceChar = $pieceNotations[$pieceTypeName] ?? strtoupper(substr($pieceTypeName ?? 'P', 0, 1));
        
        $moveNotation = $pieceChar . $from . '-' . $to;
        if ($capturedPiece) $moveNotation .= 'x';
        if ($isCheck) $moveNotation .= '+';
        if ($isCheckmate) $moveNotation .= '#';
        
        // Enregistrer le coup
        $stmt = $pdo->prepare("
            INSERT INTO moves (game_id, player_id, move_notation, from_position, to_position, 
                              piece_type, captured_piece, is_check, is_checkmate, move_number) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $gameId, $userId, $moveNotation, $from, $to,
            $pieceTypeName, $capturedPiece, $isCheck, $isCheckmate, $moveNumber
        ]);
    
        // Déterminer le nouveau tour
        $nextTurn = $game['current_turn'] === 'white' ? 'black' : 'white';
        
        // Mettre à jour la partie
        $newStatus = $game['status'];
        $winnerId = null;
        
        if ($isCheckmate) {
            $newStatus = 'completed';
            $winnerId = $userId;
            
            // Mettre à jour les statistiques
            updateGameStats($pdo, $game, $userId, 'checkmate');
        } elseif ($isDraw) {
            $newStatus = 'completed';
            updateGameStats($pdo, $game, null, 'draw');
        }
        
        $stmt = $pdo->prepare("
            UPDATE games 
            SET board_state = ?, current_turn = ?, status = ?, winner_id = ?, 
                completed_at = IF(? = 'completed', NOW(), NULL)
            WHERE id = ?
        ");
        $stmt->execute([$newBoardState, $nextTurn, $newStatus, $winnerId, $newStatus, $gameId]);
        
        jsonResponse([
            'success' => true,
            'move_number' => $moveNumber,
            'next_turn' => $nextTurn,
            'status' => $newStatus,
            'is_checkmate' => $isCheckmate,
            'is_draw' => $isDraw
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()], 500);
    }
}

/**
 * Mettre à jour les statistiques après une partie
 */
function updateGameStats($pdo, $game, $winnerId, $result) {
    $whiteId = $game['white_player_id'];
    $blackId = $game['black_player_id'];
    
    if ($result === 'draw') {
        // Match nul
        if ($whiteId) {
            $pdo->prepare("UPDATE users SET draws = draws + 1 WHERE id = ?")->execute([$whiteId]);
        }
        if ($blackId) {
            $pdo->prepare("UPDATE users SET draws = draws + 1 WHERE id = ?")->execute([$blackId]);
        }
        
        // Publications
        createGamePublication($pdo, $game['id'], $whiteId, $blackId, 'draw');
        createGamePublication($pdo, $game['id'], $blackId, $whiteId, 'draw');
    } elseif ($result === 'checkmate' || $result === 'abandon') {
        $loserId = ($winnerId == $whiteId) ? $blackId : $whiteId;
        
        if ($winnerId) {
            $pdo->prepare("UPDATE users SET wins = wins + 1 WHERE id = ?")->execute([$winnerId]);
            createGamePublication($pdo, $game['id'], $winnerId, $loserId, 'victory');
        }
        if ($loserId) {
            $field = ($result === 'abandon') ? 'abandons' : 'losses';
            $pdo->prepare("UPDATE users SET $field = $field + 1 WHERE id = ?")->execute([$loserId]);
            createGamePublication($pdo, $game['id'], $loserId, $winnerId, 'defeat');
        }
    }
}

/**
 * Créer une publication automatique après la partie
 */
function createGamePublication($pdo, $gameId, $userId, $opponentId, $type) {
    if (!$userId) return;
    
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$opponentId]);
    $opponent = $stmt->fetch();
    $opponentName = $opponent ? $opponent['username'] : 'un adversaire';
    
    $messages = [
        'victory' => "🏆 Victoire ! J'ai gagné contre $opponentName aux échecs !",
        'defeat' => "Défaite contre $opponentName aux échecs. La prochaine fois...",
        'draw' => "🤝 Match nul avec $opponentName aux échecs !"
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO publications (user_id, game_id, content, publication_type, opponent_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $gameId, $messages[$type], $type, $opponentId]);
}

/**
 * Abandonner une partie
 */
function abandonGame() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $gameId = $input['game_id'] ?? null;
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'error' => 'ID de partie requis'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    
    if (!$game) {
        jsonResponse(['success' => false, 'error' => 'Partie non trouvée'], 404);
    }
    
    // Vérifier que le joueur est dans la partie
    if ($game['white_player_id'] != $userId && $game['black_player_id'] != $userId) {
        jsonResponse(['success' => false, 'error' => 'Vous n\'êtes pas dans cette partie'], 403);
    }
    
    // Déterminer le gagnant (l'adversaire)
    $winnerId = ($game['white_player_id'] == $userId) ? $game['black_player_id'] : $game['white_player_id'];
    
    // Mettre à jour la partie
    $stmt = $pdo->prepare("
        UPDATE games 
        SET status = 'abandoned', winner_id = ?, completed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$winnerId, $gameId]);
    
    // Mettre à jour les statistiques
    updateGameStats($pdo, $game, $winnerId, 'abandon');
    
    jsonResponse([
        'success' => true,
        'message' => 'Vous avez abandonné la partie',
        'winner_id' => $winnerId
    ]);
}

/**
 * Rendre une partie publique/privée
 */
function togglePublic() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $gameId = $input['game_id'] ?? null;
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'error' => 'ID de partie requis'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    
    if (!$game) {
        jsonResponse(['success' => false, 'error' => 'Partie non trouvée'], 404);
    }
    
    if ($game['white_player_id'] != $userId && $game['black_player_id'] != $userId) {
        jsonResponse(['success' => false, 'error' => 'Vous n\'êtes pas dans cette partie'], 403);
    }
    
    $newStatus = !$game['is_public'];
    $stmt = $pdo->prepare("UPDATE games SET is_public = ? WHERE id = ?");
    $stmt->execute([$newStatus, $gameId]);
    
    jsonResponse([
        'success' => true,
        'is_public' => $newStatus,
        'message' => $newStatus ? 'La partie est maintenant publique' : 'La partie est maintenant privée'
    ]);
}

/**
 * Liste des parties publiques
 */
function getPublicGames() {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("
        SELECT g.*, 
            w.username as white_username, w.profile_photo as white_photo,
            b.username as black_username, b.profile_photo as black_photo,
            (SELECT COUNT(*) FROM spectators WHERE game_id = g.id) as spectator_count
        FROM games g
        LEFT JOIN users w ON g.white_player_id = w.id
        LEFT JOIN users b ON g.black_player_id = b.id
        WHERE g.is_public = TRUE AND g.status = 'active'
        ORDER BY g.updated_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    
    jsonResponse([
        'success' => true,
        'games' => $stmt->fetchAll()
    ]);
}

/**
 * Demander une pause
 */
function requestPause() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $gameId = $input['game_id'] ?? null;
    $resumeAt = $input['resume_at'] ?? null;
    $message = sanitize($input['message'] ?? '');
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'error' => 'ID de partie requis'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ? AND status = 'active'");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    
    if (!$game) {
        jsonResponse(['success' => false, 'error' => 'Partie non trouvée ou non active'], 404);
    }
    
    if ($game['white_player_id'] != $userId && $game['black_player_id'] != $userId) {
        jsonResponse(['success' => false, 'error' => 'Vous n\'êtes pas dans cette partie'], 403);
    }
    
    // Vérifier s'il y a déjà une demande de pause en attente
    $stmt = $pdo->prepare("SELECT id FROM pause_requests WHERE game_id = ? AND status = 'pending'");
    $stmt->execute([$gameId]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Une demande de pause est déjà en attente'], 400);
    }
    
    // Créer la demande de pause
    $stmt = $pdo->prepare("
        INSERT INTO pause_requests (game_id, requester_id, requested_resume_at, message) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$gameId, $userId, $resumeAt, $message]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Demande de pause envoyée à votre adversaire'
    ]);
}

/**
 * Répondre à une demande de pause
 */
function respondToPause() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $gameId = $input['game_id'] ?? null;
    $accept = $input['accept'] ?? false;
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'error' => 'ID de partie requis'], 400);
    }
    
    // Récupérer la demande de pause en attente
    $stmt = $pdo->prepare("
        SELECT pr.*, g.white_player_id, g.black_player_id 
        FROM pause_requests pr
        JOIN games g ON pr.game_id = g.id
        WHERE pr.game_id = ? AND pr.status = 'pending'
    ");
    $stmt->execute([$gameId]);
    $pauseRequest = $stmt->fetch();
    
    if (!$pauseRequest) {
        jsonResponse(['success' => false, 'error' => 'Aucune demande de pause en attente'], 404);
    }
    
    // Vérifier que c'est l'adversaire qui répond
    if ($pauseRequest['requester_id'] == $userId) {
        jsonResponse(['success' => false, 'error' => 'Vous ne pouvez pas répondre à votre propre demande'], 400);
    }
    
    if ($pauseRequest['white_player_id'] != $userId && $pauseRequest['black_player_id'] != $userId) {
        jsonResponse(['success' => false, 'error' => 'Vous n\'êtes pas dans cette partie'], 403);
    }
    
    $newStatus = $accept ? 'accepted' : 'declined';
    
    // Mettre à jour la demande
    $stmt = $pdo->prepare("UPDATE pause_requests SET status = ?, responded_at = NOW() WHERE id = ?");
    $stmt->execute([$newStatus, $pauseRequest['id']]);
    
    if ($accept) {
        // Mettre la partie en pause
        $stmt = $pdo->prepare("
            UPDATE games 
            SET status = 'paused', paused_at = NOW(), 
                pause_requested_by = ?, pause_accepted = TRUE,
                resume_scheduled_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$pauseRequest['requester_id'], $pauseRequest['requested_resume_at'], $gameId]);
    }
    
    jsonResponse([
        'success' => true,
        'accepted' => $accept,
        'message' => $accept ? 'Partie mise en pause' : 'Demande de pause refusée'
    ]);
}

/**
 * Reprendre une partie en pause
 */
function resumeGame() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $gameId = $input['game_id'] ?? null;
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'error' => 'ID de partie requis'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ? AND status = 'paused'");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    
    if (!$game) {
        jsonResponse(['success' => false, 'error' => 'Partie non trouvée ou non en pause'], 404);
    }
    
    if ($game['white_player_id'] != $userId && $game['black_player_id'] != $userId) {
        jsonResponse(['success' => false, 'error' => 'Vous n\'êtes pas dans cette partie'], 403);
    }
    
    $stmt = $pdo->prepare("
        UPDATE games 
        SET status = 'active', paused_at = NULL, pause_requested_by = NULL, 
            pause_accepted = FALSE, resume_scheduled_at = NULL
        WHERE id = ?
    ");
    $stmt->execute([$gameId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Partie reprise'
    ]);
}

/**
 * Mes parties
 */
function getMyGames() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $status = $_GET['status'] ?? 'all';
    
    $query = "
        SELECT g.*, 
            w.username as white_username, w.profile_photo as white_photo,
            b.username as black_username, b.profile_photo as black_photo
        FROM games g
        LEFT JOIN users w ON g.white_player_id = w.id
        LEFT JOIN users b ON g.black_player_id = b.id
        WHERE (g.white_player_id = ? OR g.black_player_id = ?)
    ";
    
    $params = [$userId, $userId];
    
    if ($status !== 'all') {
        $query .= " AND g.status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY g.updated_at DESC LIMIT 50";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    jsonResponse([
        'success' => true,
        'games' => $stmt->fetchAll()
    ]);
}
