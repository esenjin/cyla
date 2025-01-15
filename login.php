<?php
define('CYLA_CORE', true);
require_once 'core.php';

// Rediriger si déjà connecté
if (Cyla::isLoggedIn()) {
    header('Location: admin.php');
    exit;
}

$error = null;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !Cyla::verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Session invalide, veuillez réessayer';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
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
                   value="<?php echo isset($_POST['username']) ? Cyla::escape($_POST['username']) : ''; ?>">
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
require 'layout.php';