<?php
/**
 * Connexion des utilisateurs
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Méthode non autorisée'], 405);
}

// Récupérer les données (supporte JSON et form-data)
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $username = sanitize($input['username'] ?? '');
    $password = $input['password'] ?? '';
} else {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
}

// Validations
if (empty($username) || empty($password)) {
    jsonResponse(['success' => false, 'error' => "Nom d'utilisateur et mot de passe requis"], 400);
}

try {
    $pdo = getDbConnection();
    
    // Rechercher l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['success' => false, 'error' => "Nom d'utilisateur ou mot de passe incorrect"], 401);
    }
    
    // Créer une session PHP
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    
    // Supprimer les anciennes sessions expirées
    $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE user_id = ? OR expires_at < NOW()");
    $stmt->execute([$user['id']]);
    
    // Créer un nouveau token de session
    $sessionToken = generateToken();
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);
    
    $stmt = $pdo->prepare("
        INSERT INTO active_sessions (user_id, session_token, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $sessionToken, $expiresAt]);
    
    // Mettre à jour le statut en ligne
    $stmt = $pdo->prepare("UPDATE users SET is_online = TRUE, last_seen = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Connexion réussie',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'profile_photo' => $user['profile_photo'],
            'bio' => $user['bio'],
            'wins' => $user['wins'],
            'losses' => $user['losses'],
            'draws' => $user['draws']
        ],
        'session_token' => $sessionToken
    ]);
    
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'error' => 'Erreur lors de la connexion'], 500);
}
