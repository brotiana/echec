<?php
/**
 * API pour les profils utilisateurs
 */
require_once '../config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getProfile();
        break;
    case 'update':
        updateProfile();
        break;
    case 'upload_photo':
        uploadPhoto();
        break;
    case 'games':
        getUserGames();
        break;
    case 'stats':
        getStats();
        break;
    case 'publications':
        getPublications();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Action non valide'], 400);
}

/**
 * Récupérer un profil
 */
function getProfile() {
    $pdo = getDbConnection();
    $userId = $_GET['user_id'] ?? getCurrentUser();
    
    if (!$userId) {
        jsonResponse(['success' => false, 'error' => 'ID utilisateur requis'], 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT id, username, profile_photo, bio, created_at, 
               wins, losses, draws, abandons, is_online, last_seen
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['success' => false, 'error' => 'Utilisateur non trouvé'], 404);
    }
    
    // Calculer le taux de victoire
    $totalGames = $user['wins'] + $user['losses'] + $user['draws'];
    $winRate = $totalGames > 0 ? round(($user['wins'] / $totalGames) * 100, 1) : 0;
    $user['win_rate'] = $winRate;
    $user['total_games'] = $totalGames;
    
    // Récupérer les 5 dernières parties
    $stmt = $pdo->prepare("
        SELECT g.*, 
            w.username as white_username,
            b.username as black_username,
            CASE 
                WHEN g.winner_id = ? THEN 'win'
                WHEN g.winner_id IS NULL AND g.status = 'completed' THEN 'draw'
                WHEN g.status = 'completed' THEN 'loss'
                ELSE g.status
            END as result
        FROM games g
        LEFT JOIN users w ON g.white_player_id = w.id
        LEFT JOIN users b ON g.black_player_id = b.id
        WHERE g.white_player_id = ? OR g.black_player_id = ?
        ORDER BY g.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $recentGames = $stmt->fetchAll();
    
    // Récupérer les dernières publications
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as opponent_username
        FROM publications p
        LEFT JOIN users u ON p.opponent_id = u.id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $publications = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'user' => $user,
        'recent_games' => $recentGames,
        'publications' => $publications
    ]);
}

/**
 * Mettre à jour son profil
 */
function updateProfile() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $bio = sanitize($input['bio'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
    $stmt->execute([$bio, $userId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Profil mis à jour'
    ]);
}

/**
 * Changer la photo de profil
 */
function uploadPhoto() {
    $pdo = getDbConnection();
    $userId = requireAuth();
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'error' => 'Aucune photo envoyée'], 400);
    }
    
    $file = $_FILES['photo'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        jsonResponse(['success' => false, 'error' => 'Type de fichier non autorisé'], 400);
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonResponse(['success' => false, 'error' => 'La photo ne doit pas dépasser 5 Mo'], 400);
    }
    
    // Récupérer l'ancienne photo pour la supprimer
    $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldPhoto = $stmt->fetchColumn();
    
    // Générer un nouveau nom de fichier
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = 'user_' . $userId . '_' . time() . '.' . $extension;
    $destination = PROFILE_PHOTOS_DIR . $newFilename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        jsonResponse(['success' => false, 'error' => 'Erreur lors de l\'upload'], 500);
    }
    
    // Supprimer l'ancienne photo (sauf si c'est default.png)
    if ($oldPhoto && $oldPhoto !== 'default.png') {
        $oldPath = PROFILE_PHOTOS_DIR . $oldPhoto;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }
    
    // Mettre à jour la base
    $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
    $stmt->execute([$newFilename, $userId]);
    
    jsonResponse([
        'success' => true,
        'profile_photo' => $newFilename,
        'message' => 'Photo de profil mise à jour'
    ]);
}

/**
 * Historique des parties d'un utilisateur
 */
function getUserGames() {
    $pdo = getDbConnection();
    $userId = $_GET['user_id'] ?? getCurrentUser();
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    if (!$userId) {
        jsonResponse(['success' => false, 'error' => 'ID utilisateur requis'], 400);
    }
    
    // Compter le total
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM games 
        WHERE white_player_id = ? OR black_player_id = ?
    ");
    $stmt->execute([$userId, $userId]);
    $total = $stmt->fetchColumn();
    
    // Récupérer les parties
    $stmt = $pdo->prepare("
        SELECT g.*, 
            w.username as white_username, w.profile_photo as white_photo,
            b.username as black_username, b.profile_photo as black_photo,
            CASE 
                WHEN g.winner_id = ? THEN 'win'
                WHEN g.winner_id IS NULL AND g.status = 'completed' THEN 'draw'
                WHEN g.status = 'completed' THEN 'loss'
                ELSE g.status
            END as result
        FROM games g
        LEFT JOIN users w ON g.white_player_id = w.id
        LEFT JOIN users b ON g.black_player_id = b.id
        WHERE g.white_player_id = ? OR g.black_player_id = ?
        ORDER BY g.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $userId, $userId, $limit, $offset]);
    
    jsonResponse([
        'success' => true,
        'games' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
}

/**
 * Statistiques d'un utilisateur
 */
function getStats() {
    $pdo = getDbConnection();
    $userId = $_GET['user_id'] ?? getCurrentUser();
    
    if (!$userId) {
        jsonResponse(['success' => false, 'error' => 'ID utilisateur requis'], 400);
    }
    
    // Stats de base
    $stmt = $pdo->prepare("
        SELECT wins, losses, draws, abandons FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    if (!$stats) {
        jsonResponse(['success' => false, 'error' => 'Utilisateur non trouvé'], 404);
    }
    
    $totalGames = $stats['wins'] + $stats['losses'] + $stats['draws'];
    
    // Parties par couleur
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN white_player_id = ? THEN 1 ELSE 0 END) as games_as_white,
            SUM(CASE WHEN black_player_id = ? THEN 1 ELSE 0 END) as games_as_black,
            SUM(CASE WHEN white_player_id = ? AND winner_id = ? THEN 1 ELSE 0 END) as wins_as_white,
            SUM(CASE WHEN black_player_id = ? AND winner_id = ? THEN 1 ELSE 0 END) as wins_as_black
        FROM games
        WHERE (white_player_id = ? OR black_player_id = ?) AND status = 'completed'
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    $colorStats = $stmt->fetch();
    
    // Série actuelle (victoires/défaites consécutives)
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN winner_id = ? THEN 'win'
                WHEN winner_id IS NULL THEN 'draw'
                ELSE 'loss'
            END as result
        FROM games 
        WHERE (white_player_id = ? OR black_player_id = ?) AND status = 'completed'
        ORDER BY completed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $recentResults = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $currentStreak = 0;
    $streakType = null;
    foreach ($recentResults as $result) {
        if ($streakType === null) {
            $streakType = $result;
            $currentStreak = 1;
        } elseif ($result === $streakType) {
            $currentStreak++;
        } else {
            break;
        }
    }
    
    jsonResponse([
        'success' => true,
        'stats' => [
            'wins' => $stats['wins'],
            'losses' => $stats['losses'],
            'draws' => $stats['draws'],
            'abandons' => $stats['abandons'],
            'total_games' => $totalGames,
            'win_rate' => $totalGames > 0 ? round(($stats['wins'] / $totalGames) * 100, 1) : 0,
            'games_as_white' => $colorStats['games_as_white'] ?? 0,
            'games_as_black' => $colorStats['games_as_black'] ?? 0,
            'wins_as_white' => $colorStats['wins_as_white'] ?? 0,
            'wins_as_black' => $colorStats['wins_as_black'] ?? 0,
            'current_streak' => $currentStreak,
            'streak_type' => $streakType
        ]
    ]);
}

/**
 * Publications d'un utilisateur
 */
function getPublications() {
    $pdo = getDbConnection();
    $userId = $_GET['user_id'] ?? null;
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    if (!$userId) {
        jsonResponse(['success' => false, 'error' => 'ID utilisateur requis'], 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username as opponent_username, u.profile_photo as opponent_photo
        FROM publications p
        LEFT JOIN users u ON p.opponent_id = u.id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    
    jsonResponse([
        'success' => true,
        'publications' => $stmt->fetchAll()
    ]);
}
