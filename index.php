<?php
define('CYLA_CORE', true);
require_once 'core.php';

// Rediriger vers l'admin si déjà connecté
if (Cyla::isLoggedIn()) {
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Accueil</title>
    <link rel="icon" type="image/png" href="logocyla.png">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="home-container">
        <main class="home-hero">
            <div class="home-content">
                <h1>Cyla</h1>
                <p class="home-description">Le disque nuagique personnel d'Esenjin.</p>
                <a href="admin.php" class="home-button">
                    Accéder à l'administration
                    <svg class="home-button-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </main>

        <footer class="home-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - Version <?php echo SITE_VERSION; ?></p>
        </footer>
    </div>
</body>
</html>

<style>
/* Styles spécifiques à la page d'accueil */
.home-container {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background-color: var(--color-bg);
}

.home-hero {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--spacing-lg);
}

.home-content {
    text-align: center;
}

.home-content h1 {
    font-size: 4rem;
    font-weight: bold;
    color: var(--color-primary);
    margin-bottom: var(--spacing-md);
}

.home-description {
    font-size: 1.5rem;
    color: var(--color-text-muted);
    margin-bottom: var(--spacing-lg);
}

.home-button {
    display: inline-flex;
    align-items: center;
    padding: var(--spacing-md) var(--spacing-lg);
    background-color: var(--color-primary);
    color: var(--color-text);
    text-decoration: none;
    border-radius: var(--border-radius);
    font-weight: 500;
    transition: background-color 0.2s ease;
}

.home-button:hover {
    background-color: var(--color-primary-hover);
}

.home-button-icon {
    width: 1.25rem;
    height: 1.25rem;
    margin-left: var(--spacing-sm);
}

.home-footer {
    text-align: center;
    padding: var(--spacing-lg);
    color: var(--color-text-muted);
    background-color: var(--color-bg-alt);
    border-top: 1px solid var(--color-border);
}

/* Animation d'entrée */
.home-content {
    animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>