<?php
if (!defined('CYLA_CORE')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Accès direct interdit');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?><?php echo isset($pageTitle) ? ' - ' . $pageTitle : ''; ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react/18.2.0/umd/react.production.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/react-dom/18.2.0/umd/react-dom.production.min.js"></script>
    <?php if (isset($file_info) && $file_info['preview_type'] === 'image'): ?>
        <meta property="og:title" content="<?php echo Cyla::escape($file_info['name']); ?>">
        <meta property="og:description" content="Fichier hébergé sur <?php echo SITE_NAME; ?>">
        <meta property="og:image" content="<?php echo Cyla::escape($file_url); ?>">
        <meta property="og:url" content="<?php echo Cyla::escape($share_url); ?>">
        <meta property="og:type" content="website">
    <?php endif; ?>
</head>
<body>
    <div id="passwordModalRoot"></div>
    <header>
        <nav>
            <div class="nav-brand">
                <a href="index.php"><?php echo SITE_NAME; ?></a>
            </div>
            <?php if (Cyla::isLoggedIn()): ?>
                <div class="nav-menu">
                    <a href="admin.php" class="nav-link">Administration</a>
                    <button 
                        type="button" 
                        id="changePasswordBtn" 
                        class="nav-link" 
                        onclick="console.log('Clic natif détecté')"
                    >
                        Changer le mot de passe
                    </button>
                    <a href="logout.php?csrf_token=<?php echo Cyla::generateCSRFToken(); ?>" class="nav-link">Déconnexion</a>
                </div>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo Cyla::escape($error); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo Cyla::escape($success); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($content)) echo $content; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - Version <?php echo SITE_VERSION; ?></p>
    </footer>

    <script src="js/main.js"></script>
</body>
</html>