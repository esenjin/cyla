<?php
/**
 * Page de callback Vestikan — c'est l'URL enregistrée comme redirect_uri.
 *
 * Pattern A (Cyla est mono-utilisateur, sans base de données) :
 * une identité Vestikan validée SUFFIT à ouvrir l'accès admin. Le seul
 * vestikan_id possible est celui du maître, puisque Vestikan est un IdP
 * mono-utilisateur.
 *
 * On charge core.php EN PREMIER pour que la session active soit CYLA_SESSION,
 * celle dans laquelle begin() a écrit le state (sinon complete() lèverait
 * « State invalide »).
 */
define('CYLA_CORE', true);
require_once __DIR__ . '/core.php';

require_once __DIR__ . '/vestikan-sdk.php';

try {
    $vk = new Vestikan(require __DIR__ . '/vestikan-config.php');
} catch (VestikanException $e) {
    header('Location: login.php?error=' . urlencode('Connexion Vestikan indisponible'));
    exit;
}

try {
    // Vérifie le state, échange le code en back-channel, renvoie le vestikan_id.
    // Lève VestikanException si quoi que ce soit échoue.
    $vestikanId = $vk->complete();
} catch (VestikanException $e) {
    // Tout échec du flux = refus d'authentification. On n'ouvre AUCUNE session.
    error_log('[Cyla] Échec connexion Vestikan : ' . $e->getMessage());
    header('Location: login.php?error=' . urlencode('Connexion Vestikan refusée'));
    exit;
}

// --- Identité maître prouvée : on ouvre l'accès admin. ---
// On réutilise la mécanique de session existante de Cyla : poser
// $_SESSION['user'] + rafraîchir l'activité rend isLoggedIn() vrai, exactement
// comme après un login natif réussi. On régénère l'id de session pour éviter
// toute fixation.
session_regenerate_id(true);
$_SESSION['user']         = 'admin';        // le compte maître unique de Cyla
$_SESSION['vestikan_id']  = $vestikanId;    // trace de la méthode de connexion
$_SESSION['auth_method']  = 'vestikan';
Cyla::updateActivity();

// Destination mémorisée dans begin() ('admin.php'), sinon l'accueil admin.
$returnTo = $vk->popReturnTo() ?: 'admin.php';

// Sécurité : on n'autorise qu'une redirection interne relative, jamais une URL
// absolue venue de l'extérieur (évite l'open-redirect).
if (!preg_match('#^[a-zA-Z0-9._/-]+$#', $returnTo) || str_contains($returnTo, '//')) {
    $returnTo = 'admin.php';
}

header('Location: ' . $returnTo);
exit;
