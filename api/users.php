<?php
/**
 * API pour la gestion des utilisateurs et invitations
 */
require_once '../config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'active':
        getActiveUsers();
        break;
    case 'invite':
        inviteUser();
        break;
    case 'invitations':
        getInvitations();
        break;
    case 'respond_invitation':
        respondToInvitation();
        break;
    case 'search':
        searchUsers();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Action non valide'], 400);
}

/**
 * Liste des utilisateurs actifs
 */
function getActiveUsers() {
    $pdo = getDbConnection();
    $userId = getCurrentUser();
    
    $stmt = $pdo->prepare("
        SELECT id, username, profile_photo, is_online, last_seen, wins, losses, draws
        FROM users
        WHERE is_online = TRUE AND id != ?
        ORDER BY last_seen DESC
        LIMIT 50
    ");
    $stmt->execute([$userId ?? 0]);
    
    jsonResponse([
        'success' => true,
        'users' => $stmt->fetchAll()
    ]);
}

/**
 * Inviter un utilisateur à jouer
 */
function inviteUser() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $receiverId = $input['receiver_id'] ?? null;
    
    if (!$receiverId) {
        jsonResponse(['success' => false, 'error' => 'ID utilisateur requis'], 400);
    }
    
    if ($receiverId == $userId) {
        jsonResponse(['success' => false, 'error' => 'Vous ne pouvez pas vous inviter vous-même'], 400);
    }
    
    // Vérifier que l'utilisateur existe
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$receiverId]);
    $receiver = $stmt->fetch();
    
    if (!$receiver) {
        jsonResponse(['success' => false, 'error' => 'Utilisateur non trouvé'], 404);
    }
    
    // Vérifier s'il y a déjà une invitation en attente
    $stmt = $pdo->prepare("
        SELECT id FROM game_invitations 
        WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
    ");
    $stmt->execute([$userId, $receiverId]);
    
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Vous avez déjà une invitation en attente avec cet utilisateur'], 400);
    }
    
    // Créer la partie immédiatement avec status 'waiting'
    // L'émetteur joue les blancs
    $stmt = $pdo->prepare("
        INSERT INTO games (white_player_id, black_player_id, board_state, status) 
        VALUES (?, ?, ?, 'waiting')
    ");
    $stmt->execute([$userId, $receiverId, getInitialBoardState()]);
    $gameId = $pdo->lastInsertId();
    
    // Créer l'invitation avec le game_id
    $stmt = $pdo->prepare("
        INSERT INTO game_invitations (sender_id, receiver_id, game_id) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$userId, $receiverId, $gameId]);
    
    jsonResponse([
        'success' => true,
        'invitation_id' => $pdo->lastInsertId(),
        'game_id' => $gameId,
        'message' => "Invitation envoyée à {$receiver['username']}. La partie vous attend!"
    ]);
}

/**
 * Récupérer mes invitations
 */
function getInvitations() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $type = $_GET['type'] ?? 'received';
    
    if ($type === 'received') {
        $stmt = $pdo->prepare("
            SELECT gi.*, u.username as sender_username, u.profile_photo as sender_photo,
                   u.wins as sender_wins, u.losses as sender_losses
            FROM game_invitations gi
            JOIN users u ON gi.sender_id = u.id
            WHERE gi.receiver_id = ? AND gi.status = 'pending'
            ORDER BY gi.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT gi.*, u.username as receiver_username, u.profile_photo as receiver_photo
            FROM game_invitations gi
            JOIN users u ON gi.receiver_id = u.id
            WHERE gi.sender_id = ? AND gi.status = 'pending'
            ORDER BY gi.created_at DESC
        ");
    }
    
    $stmt->execute([$userId]);
    
    jsonResponse([
        'success' => true,
        'invitations' => $stmt->fetchAll()
    ]);
}

/**
 * Répondre à une invitation
 */
function respondToInvitation() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $invitationId = $input['invitation_id'] ?? null;
    $accept = $input['accept'] ?? false;
    
    if (!$invitationId) {
        jsonResponse(['success' => false, 'error' => 'ID invitation requis'], 400);
    }
    
    // Récupérer l'invitation
    $stmt = $pdo->prepare("
        SELECT * FROM game_invitations 
        WHERE id = ? AND receiver_id = ? AND status = 'pending'
    ");
    $stmt->execute([$invitationId, $userId]);
    $invitation = $stmt->fetch();
    
    if (!$invitation) {
        jsonResponse(['success' => false, 'error' => 'Invitation non trouvée ou déjà traitée'], 404);
    }
    
    $newStatus = $accept ? 'accepted' : 'declined';
    $gameId = $invitation['game_id']; // La partie a déjà été créée lors de l'envoi de l'invitation
    
    if ($accept && $gameId) {
        // Activer la partie existante
        $stmt = $pdo->prepare("UPDATE games SET status = 'active' WHERE id = ? AND status = 'waiting'");
        $stmt->execute([$gameId]);
    } elseif (!$accept && $gameId) {
        // Supprimer ou annuler la partie si refusée
        $stmt = $pdo->prepare("UPDATE games SET status = 'abandoned' WHERE id = ? AND status = 'waiting'");
        $stmt->execute([$gameId]);
    }
    
    // Mettre à jour l'invitation
    $stmt = $pdo->prepare("
        UPDATE game_invitations 
        SET status = ?, responded_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $invitationId]);
    
    $response = [
        'success' => true,
        'accepted' => $accept,
        'message' => $accept ? 'Invitation acceptée, la partie commence!' : 'Invitation refusée'
    ];
    
    if ($gameId && $accept) {
        $response['game_id'] = $gameId;
    }
    
    jsonResponse($response);
}

/**
 * Rechercher des utilisateurs
 */
function searchUsers() {
    $pdo = getDbConnection();
    $userId = getCurrentUser();
    $query = sanitize($_GET['q'] ?? '');
    
    if (strlen($query) < 2) {
        jsonResponse(['success' => false, 'error' => 'Recherche trop courte (min 2 caractères)'], 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT id, username, profile_photo, is_online, wins, losses, draws
        FROM users
        WHERE username LIKE ? AND id != ?
        ORDER BY is_online DESC, username ASC
        LIMIT 20
    ");
    $stmt->execute(["%$query%", $userId ?? 0]);
    
    jsonResponse([
        'success' => true,
        'users' => $stmt->fetchAll()
    ]);
}
