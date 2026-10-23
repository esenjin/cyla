<?php
define('CYLA_CORE', true);
require_once __DIR__ . '/core.php';

// Rediriger si déjà connecté
if (Cyla::isLoggedIn()) {
    header('Location: admin.php');
    exit;
}

// Messages transmis par redirection (déconnexion, échec Vestikan…).
// Seuls les messages émis par Cyla sont affichés : un lien forgé avec un
// texte arbitraire (?error=Votre compte est suspendu…) est ignoré.
const LOGIN_ALLOWED_ERRORS = [
    'Connexion Vestikan indisponible',
    'Connexion Vestikan refusée',
];
const LOGIN_ALLOWED_SUCCESS = [
    'Vous avez été déconnecté avec succès',
];

$error   = in_array(Cyla::input($_GET, 'error'), LOGIN_ALLOWED_ERRORS, true) ? $_GET['error'] : null;
$success = in_array(Cyla::input($_GET, 'success'), LOGIN_ALLOWED_SUCCESS, true) ? $_GET['success'] : null;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du token CSRF
    if (!Cyla::verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'Session invalide, veuillez réessayer';
    } else {
        $username = Cyla::input($_POST, 'username');
        $password = Cyla::input($_POST, 'password');

        if ($username === '' || $password === '') {
            $error = 'Veuillez remplir tous les champs';
        } else if (!Cyla::authenticate($username, $password)) {
            $error = 'Identifiants incorrects';
            // Protection contre le brute force
            sleep(1);
        } else {
            header('Location: admin.php');
            exit;
        }
    }
}

// Contenu de la page
$pageTitle = 'Connexion';
ob_start(); ?>

<div class="login-container card">
    <h1>Connexion à l'administration</h1>
    
    <form method="POST" action="login.php" class="login-form">
        <input type="hidden" name="csrf_token" value="<?php echo Cyla::generateCSRFToken(); ?>">
        
        <div class="form-group">
            <label for="username">Nom d'utilisateur</label>
            <input type="text" 
                   id="username" 
                   name="username" 
                   required 
                   autocomplete="username"
                   value="<?php echo Cyla::escape(Cyla::input($_POST, 'username')); ?>">
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" 
                   id="password" 
                   name="password" 
                   required 
                   autocomplete="current-password">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Se connecter</button>
        </div>
    </form>

    <?php if (file_exists(__DIR__ . '/vestikan-config.php')): ?>
    <div class="login-sso-separator"><span>ou</span></div>

    <div class="login-sso">
        <a href="vestikan-login.php" class="btn btn-vestikan">
            <svg class="vestikan-icon" width="18" height="18" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2 4 5v6c0 5 3.4 8.6 8 11 4.6-2.4 8-6 8-11V5l-8-3z"/>
                <path d="m9 12 2 2 4-4"/>
            </svg>
            Se connecter avec Vestikan
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
/* Styles spécifiques à la page de connexion */
.login-container {
    max-width: 400px;
    margin: 2rem auto;
}

.login-container h1 {
    margin-bottom: var(--spacing-lg);
    text-align: center;
    color: var(--color-primary);
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.form-actions {
    margin-top: var(--spacing-md);
    text-align: center;
}

/* Séparateur « ou » entre login natif et SSO */
.login-sso-separator {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    margin: var(--spacing-lg) 0 var(--spacing-md);
    color: var(--color-text-muted);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.login-sso-separator::before,
.login-sso-separator::after {
    content: "";
    flex: 1;
    height: 1px;
    background-color: var(--color-border);
}

.login-sso {
    display: flex;
    justify-content: center;
}

/* Bouton Vestikan : fond sombre, accent rosé au survol */
.btn-vestikan {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--spacing-sm);
    width: 100%;
    background-color: var(--color-bg);
    color: var(--color-text);
    border: 1px solid var(--color-accent);
    transition: background-color 0.3s ease, border-color 0.3s ease;
}

.btn-vestikan:hover {
    background-color: var(--color-accent);
    border-color: var(--color-primary);
    color: #fff;
}

.btn-vestikan .vestikan-icon {
    color: var(--color-primary);
    flex-shrink: 0;
}

.btn-vestikan:hover .vestikan-icon {
    color: #fff;
}

/* Animation de l'erreur */
.alert-error {
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';