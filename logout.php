<?php
/**
 * Déconnexion des utilisateurs
 */
require_once 'config.php';

$userId = getCurrentUser();

if ($userId) {
    try {
        $pdo = getDbConnection();
        
        // Supprimer les sessions actives
        $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Mettre à jour le statut hors ligne
        $stmt = $pdo->prepare("UPDATE users SET is_online = FALSE, last_seen = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
        
    } catch (PDOException $e) {
        // Ignorer les erreurs de déconnexion
    }
}

// Détruire la session PHP
session_destroy();

jsonResponse([
    'success' => true,
    'message' => 'Déconnexion réussie'
]);
