<?php
/**
 * Démarre la connexion « Se connecter avec Vestikan ».
 *
 * On charge core.php EN PREMIER : il fait session_name(SESSION_NAME) puis
 * session_start(). Ainsi le state anti-CSRF que le SDK écrit en session est
 * stocké dans la session CYLA_SESSION, la même qui survivra jusqu'au callback.
 */
define('CYLA_CORE', true);
require_once __DIR__ . '/core.php';

// Déjà connecté ? Inutile de repasser par l'IdP.
if (Cyla::isLoggedIn()) {
    header('Location: admin.php');
    exit;
}

require_once __DIR__ . '/vestikan-sdk.php';

try {
    $vk = new Vestikan(require __DIR__ . '/vestikan-config.php');
} catch (VestikanException $e) {
    // Config absente ou incomplète : on retombe proprement sur le login natif.
    header('Location: login.php?error=' . urlencode('Connexion Vestikan indisponible'));
    exit;
}

// Après authentification réussie, on veut atterrir sur l'administration.
// begin() redirige vers Vestikan et ne revient jamais (exit interne).
$vk->begin('admin.php');
