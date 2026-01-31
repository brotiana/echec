<?php
/**
 * Inscription des utilisateurs
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Méthode non autorisée'], 405);
}

// Récupérer les données
$username = sanitize($_POST['username'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$bio = sanitize($_POST['bio'] ?? '');

// Validations
$errors = [];

if (empty($username)) {
    $errors[] = "Le nom d'utilisateur est requis";
} elseif (strlen($username) < 3 || strlen($username) > 50) {
    $errors[] = "Le nom d'utilisateur doit contenir entre 3 et 50 caractères";
} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    $errors[] = "Le nom d'utilisateur ne peut contenir que des lettres, chiffres et underscores";
}

if (empty($email)) {
    $errors[] = "L'email est requis";
} elseif (!isValidEmail($email)) {
    $errors[] = "L'email n'est pas valide";
}

if (empty($password)) {
    $errors[] = "Le mot de passe est requis";
} elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
    $errors[] = "Le mot de passe doit contenir au moins " . MIN_PASSWORD_LENGTH . " caractères";
}

if (!empty($errors)) {
    jsonResponse(['success' => false, 'errors' => $errors], 400);
}

try {
    $pdo = getDbConnection();
    
    // Vérifier si l'utilisateur existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => "Ce nom d'utilisateur ou email existe déjà"], 409);
    }
    
    // Traiter l'upload de photo
    $profilePhoto = 'default.png';
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_photo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            jsonResponse(['success' => false, 'error' => 'Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP'], 400);
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
            jsonResponse(['success' => false, 'error' => 'La photo ne doit pas dépasser 5 Mo'], 400);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = $username . '_' . time() . '.' . $extension;
        $destination = PROFILE_PHOTOS_DIR . $newFilename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $profilePhoto = $newFilename;
        }
    }
    
    // Hasher le mot de passe
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insérer l'utilisateur
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, profile_photo, bio) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$username, $email, $hashedPassword, $profilePhoto, $bio]);
    
    $userId = $pdo->lastInsertId();
    
    // Créer une session
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    
    // Créer un token de session
    $sessionToken = generateToken();
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);
    
    $stmt = $pdo->prepare("
        INSERT INTO active_sessions (user_id, session_token, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$userId, $sessionToken, $expiresAt]);
    
    // Mettre à jour le statut en ligne
    $stmt = $pdo->prepare("UPDATE users SET is_online = TRUE WHERE id = ?");
    $stmt->execute([$userId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Inscription réussie',
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email,
            'profile_photo' => $profilePhoto
        ],
        'session_token' => $sessionToken
    ]);
    
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'error' => 'Erreur lors de l\'inscription: ' . $e->getMessage()], 500);
}
