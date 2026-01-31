<?php
/**
 * Vérification de la session utilisateur
 */
require_once 'config.php';

$userId = getCurrentUser();

if (!$userId) {
    jsonResponse([
        'success' => true,
        'authenticated' => false,
        'error' => 'Non connecté'
    ]);
}

try {
    $pdo = getDbConnection();
    
    // Vérifier si l'utilisateur existe et la session est valide
    $stmt = $pdo->prepare("
        SELECT u.* 
        FROM users u 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Utilisateur n'existe plus
        session_destroy();
        jsonResponse([
            'success' => true,
            'authenticated' => false,
            'error' => 'Utilisateur non trouvé'
        ]);
    }
    
    // Vérifier si une session active existe dans la base
    $stmt = $pdo->prepare("SELECT id FROM active_sessions WHERE user_id = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$userId]);
    $activeSession = $stmt->fetch();
    
    if (!$activeSession) {
        // Créer une nouvelle session dans la base (récupérer la session PHP existante)
        $sessionToken = generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);
        
        // Supprimer les anciennes sessions
        $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Créer une nouvelle session
        $stmt = $pdo->prepare("INSERT INTO active_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $sessionToken, $expiresAt]);
    }
    
    // Mettre à jour last_seen
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW(), is_online = TRUE WHERE id = ?");
    $stmt->execute([$userId]);
    
    // Récupérer les invitations en attente
    $stmt = $pdo->prepare("
        SELECT gi.*, u.username as sender_username, u.profile_photo as sender_photo
        FROM game_invitations gi
        JOIN users u ON gi.sender_id = u.id
        WHERE gi.receiver_id = ? AND gi.status = 'pending'
        ORDER BY gi.created_at DESC
    ");
    $stmt->execute([$userId]);
    $pendingInvitations = $stmt->fetchAll();
    
    // Récupérer les parties en cours
    $stmt = $pdo->prepare("
        SELECT g.*, 
            w.username as white_username, w.profile_photo as white_photo,
            b.username as black_username, b.profile_photo as black_photo
        FROM games g
        LEFT JOIN users w ON g.white_player_id = w.id
        LEFT JOIN users b ON g.black_player_id = b.id
        WHERE (g.white_player_id = ? OR g.black_player_id = ?) 
        AND g.status IN ('active', 'paused', 'waiting')
        ORDER BY g.updated_at DESC
    ");
    $stmt->execute([$userId, $userId]);
    $activeGames = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'authenticated' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'profile_photo' => $user['profile_photo'],
            'bio' => $user['bio'],
            'wins' => $user['wins'],
            'losses' => $user['losses'],
            'draws' => $user['draws'],
            'abandons' => $user['abandons']
        ],
        'pending_invitations' => $pendingInvitations,
        'active_games' => $activeGames
    ]);
    
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()], 500);
}
