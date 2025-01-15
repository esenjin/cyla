<?php
define('CYLA_CORE', true);
require_once 'core.php';

// Vérification du token CSRF pour la déconnexion
$validLogout = isset($_GET['csrf_token']) && Cyla::verifyCSRFToken($_GET['csrf_token']);

if (!$validLogout) {
    // Si pas de token valide, redirection avec message d'erreur
    header('Location: admin.php?error=' . urlencode('Token de sécurité invalide'));
    exit;
}

// Déconnexion de l'utilisateur
Cyla::logout();

// Redirection vers la page de connexion avec message de succès
header('Location: login.php?success=' . urlencode('Vous avez été déconnecté avec succès'));
exit;