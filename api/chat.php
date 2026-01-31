<?php
/**
 * API pour le chat et les messages
 */
require_once '../config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'send':
        sendMessage();
        break;
    case 'get_game_messages':
        getGameMessages();
        break;
    case 'get_private_messages':
        getPrivateMessages();
        break;
    case 'get_conversations':
        getConversations();
        break;
    case 'emoji_rain':
        sendEmojiRain();
        break;
    case 'mark_read':
        markAsRead();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Action non valide'], 400);
}

/**
 * Envoyer un message
 */
function sendMessage() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $content = sanitize($input['content'] ?? '');
    $receiverId = $input['receiver_id'] ?? null;
    $gameId = $input['game_id'] ?? null;
    
    if (empty($content)) {
        jsonResponse(['success' => false, 'error' => 'Message vide'], 400);
    }
    
    if (!$receiverId && !$gameId) {
        jsonResponse(['success' => false, 'error' => 'Destinataire ou partie requis'], 400);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, game_id, content) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $receiverId, $gameId, $content]);
    
    $messageId = $pdo->lastInsertId();
    
    // Récupérer le message avec les infos de l'expéditeur
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_username, u.profile_photo as sender_photo
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'message' => $message
    ]);
}

/**
 * Récupérer les messages d'une partie
 */
function getGameMessages() {
    $pdo = getDbConnection();
    $gameId = $_GET['game_id'] ?? null;
    $lastId = $_GET['last_id'] ?? 0;
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'error' => 'ID de partie requis'], 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_username, u.profile_photo as sender_photo
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.game_id = ? AND m.id > ?
        ORDER BY m.created_at ASC
        LIMIT 100
    ");
    $stmt->execute([$gameId, $lastId]);
    
    jsonResponse([
        'success' => true,
        'messages' => $stmt->fetchAll()
    ]);
}

/**
 * Récupérer les messages privés avec un utilisateur
 */
function getPrivateMessages() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    $otherId = $_GET['user_id'] ?? null;
    $lastId = $_GET['last_id'] ?? 0;
    
    if (!$otherId) {
        jsonResponse(['success' => false, 'error' => 'ID utilisateur requis'], 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as sender_username, u.profile_photo as sender_photo
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.game_id IS NULL 
        AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        AND m.id > ?
        ORDER BY m.created_at ASC
        LIMIT 100
    ");
    $stmt->execute([$userId, $otherId, $otherId, $userId, $lastId]);
    
    // Marquer comme lus
    $stmtUpdate = $pdo->prepare("
        UPDATE messages SET is_read = TRUE 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
    ");
    $stmtUpdate->execute([$otherId, $userId]);
    
    jsonResponse([
        'success' => true,
        'messages' => $stmt->fetchAll()
    ]);
}

/**
 * Récupérer la liste des conversations
 */
function getConversations() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    // Récupérer les derniers messages avec chaque utilisateur
    $stmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.username,
            u.profile_photo,
            u.is_online,
            m.content as last_message,
            m.created_at as last_message_time,
            (SELECT COUNT(*) FROM messages 
             WHERE sender_id = u.id AND receiver_id = ? AND is_read = FALSE) as unread_count
        FROM users u
        JOIN (
            SELECT 
                CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_id,
                MAX(id) as last_msg_id
            FROM messages
            WHERE (sender_id = ? OR receiver_id = ?) AND game_id IS NULL
            GROUP BY other_id
        ) latest ON u.id = latest.other_id
        JOIN messages m ON m.id = latest.last_msg_id
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    
    jsonResponse([
        'success' => true,
        'conversations' => $stmt->fetchAll()
    ]);
}

/**
 * Envoyer une pluie d'emojis
 */
function sendEmojiRain() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $emoji = $input['emoji'] ?? '😊';
    $receiverId = $input['receiver_id'] ?? null;
    $gameId = $input['game_id'] ?? null;
    
    if (!$receiverId && !$gameId) {
        jsonResponse(['success' => false, 'error' => 'Destinataire ou partie requis'], 400);
    }
    
    // Liste des emojis autorisés
    $allowedEmojis = ['😊', '😂', '🎉', '👏', '🔥', '❤️', '😢', '😠', '🤔', '😎', '🏆', '💪', '👍', '👎', '🙌'];
    
    if (!in_array($emoji, $allowedEmojis)) {
        $emoji = '😊';
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, game_id, content, is_emoji_rain, emoji_type) 
        VALUES (?, ?, ?, ?, TRUE, ?)
    ");
    $stmt->execute([$userId, $receiverId, $gameId, "Pluie d'emojis: " . $emoji, $emoji]);
    
    $messageId = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true,
        'message_id' => $messageId,
        'emoji' => $emoji,
        'message' => 'Pluie d\'emojis envoyée!'
    ]);
}

/**
 * Marquer les messages comme lus
 */
function markAsRead() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $senderId = $input['sender_id'] ?? null;
    
    if (!$senderId) {
        jsonResponse(['success' => false, 'error' => 'ID expéditeur requis'], 400);
    }
    
    $stmt = $pdo->prepare("
        UPDATE messages SET is_read = TRUE 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$senderId, $userId]);
    
    jsonResponse([
        'success' => true,
        'updated' => $stmt->rowCount()
    ]);
}
