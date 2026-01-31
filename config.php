<?php
/**
 * Configuration du site d'échecs
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'echec_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configuration du site
define('SITE_URL', 'http://localhost/echec');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('PROFILE_PHOTOS_DIR', UPLOAD_DIR . 'profiles/');

// Configuration de sécurité
define('MIN_PASSWORD_LENGTH', 10);
define('SESSION_DURATION', 86400 * 7); // 7 jours

// Configuration de la session PHP - durée de vie longue
ini_set('session.gc_maxlifetime', SESSION_DURATION);
ini_set('session.cookie_lifetime', SESSION_DURATION);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_DURATION,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Connexion à la base de données
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
            exit;
        }
    }
    
    return $pdo;
}

// Fonction pour envoyer une réponse JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Fonction pour vérifier l'authentification
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'error' => 'Non authentifié'], 401);
    }
    
    // Prolonger la session dans la base de données
    extendSession($_SESSION['user_id']);
    
    return $_SESSION['user_id'];
}

// Fonction pour obtenir l'utilisateur courant (optionnel)
function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        // Prolonger la session
        extendSession($_SESSION['user_id']);
    }
    return $_SESSION['user_id'] ?? null;
}

// Fonction pour prolonger la session
function extendSession($userId) {
    static $extended = false;
    
    // Éviter de prolonger la session plusieurs fois par requête
    if ($extended) return;
    $extended = true;
    
    try {
        $pdo = getDbConnection();
        $newExpiry = date('Y-m-d H:i:s', time() + SESSION_DURATION);
        
        // Prolonger la session active
        $stmt = $pdo->prepare("UPDATE active_sessions SET expires_at = ? WHERE user_id = ? AND expires_at > NOW()");
        $stmt->execute([$newExpiry, $userId]);
        
        // Mettre à jour last_seen
        $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW(), is_online = TRUE WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        // Ignorer les erreurs
    }
}

// Fonction pour nettoyer les entrées
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Fonction pour valider l'email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Fonction pour générer un token unique
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// Créer les dossiers nécessaires s'ils n'existent pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(PROFILE_PHOTOS_DIR)) {
    mkdir(PROFILE_PHOTOS_DIR, 0755, true);
}

// Position initiale de l'échiquier
function getInitialBoardState() {
    return json_encode([
        'a8' => 'black_rook', 'b8' => 'black_knight', 'c8' => 'black_bishop', 'd8' => 'black_queen',
        'e8' => 'black_king', 'f8' => 'black_bishop', 'g8' => 'black_knight', 'h8' => 'black_rook',
        'a7' => 'black_pawn', 'b7' => 'black_pawn', 'c7' => 'black_pawn', 'd7' => 'black_pawn',
        'e7' => 'black_pawn', 'f7' => 'black_pawn', 'g7' => 'black_pawn', 'h7' => 'black_pawn',
        'a2' => 'white_pawn', 'b2' => 'white_pawn', 'c2' => 'white_pawn', 'd2' => 'white_pawn',
        'e2' => 'white_pawn', 'f2' => 'white_pawn', 'g2' => 'white_pawn', 'h2' => 'white_pawn',
        'a1' => 'white_rook', 'b1' => 'white_knight', 'c1' => 'white_bishop', 'd1' => 'white_queen',
        'e1' => 'white_king', 'f1' => 'white_bishop', 'g1' => 'white_knight', 'h1' => 'white_rook'
    ]);
}

// Headers CORS pour les requêtes AJAX
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
