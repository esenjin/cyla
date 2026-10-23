<?php
define('CYLA_CORE', true);
require_once __DIR__ . '/core.php';

$error = null;
$file_info = null;
$filepath = null;

$filename = Cyla::input($_GET, 'file');
$path     = Cyla::normalizeStoragePath($_GET['path'] ?? null);

// Vérifier si un fichier est spécifié
if ($filename === '') {
    $error = 'Aucun fichier spécifié';
} else {
    // Résolution sécurisée : le fichier doit exister et se trouver DANS un des
    // dossiers de stockage (toute tentative « ../ » est rejetée).
    $filepath = Cyla::resolveFilePath($filename, $path);

    if ($filepath === null) {
        $error = 'Fichier introuvable';
    } else {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $file_info = [
            'name'         => $filename,
            'size'         => (int) filesize($filepath),
            'extension'    => strtolower($extension),
            'preview_type' => getPreviewType($extension),
        ];
    }
}

// Construction des URLs
$file_url = SITE_URL . $path . ($file_info ? rawurlencode($file_info['name']) : '');
$share_url = SITE_URL . 'share.php?file=' . ($file_info ? rawurlencode($file_info['name']) : '') . '&path=' . urlencode($path);

// Contenu de la page
$pageTitle = $file_info ? $file_info['name'] : 'Fichier introuvable';
ob_start(); ?>

<?php if ($error): ?>
    <div class="card">
        <h1>Erreur</h1>
        <p><?php echo Cyla::escape($error); ?></p>
        <a href="index.php" class="btn">Retour à l'accueil</a>
    </div>
<?php else: ?>
    <div class="share-container">
        <div class="card">
            <h1><?php echo Cyla::escape($file_info['name']); ?></h1>
            
            <div class="file-meta">
                <p>
                    Taille : <?php echo Cyla::escape(round($file_info['size'] / 1024, 2)); ?> Ko
                    · Type : <?php echo Cyla::escape(strtoupper($file_info['extension'])); ?>
                </p>
            </div>

            <div class="preview-container">
                <?php if ($file_info['preview_type'] === 'image'): ?>
                    <img src="<?php echo Cyla::escape($file_url); ?>" 
                         alt="<?php echo Cyla::escape($file_info['name']); ?>"
                         class="preview-content">
                <?php elseif ($file_info['preview_type'] === 'video'): ?>
                    <video controls class="preview-content">
                        <source src="<?php echo Cyla::escape($file_url); ?>" 
                                type="video/<?php echo $file_info['extension'] === 'webm' ? 'webm' : 'mp4'; ?>">
                        Votre navigateur ne supporte pas la lecture de vidéos.
                    </video>
                <?php elseif ($file_info['preview_type'] === 'audio'): ?>
                    <audio controls class="preview-content">
                        <source src="<?php echo Cyla::escape($file_url); ?>" 
                                type="audio/<?php echo $file_info['extension']; ?>">
                        Votre navigateur ne supporte pas la lecture audio.
                    </audio>
                <?php elseif ($file_info['preview_type'] === 'text'): ?>
                    <pre class="text-preview"><?php 
                        // Lire et afficher le contenu du fichier texte de manière sécurisée
                        $content = file_get_contents($filepath);
                        if ($content !== false) {
                            echo Cyla::escape($content);
                        } else {
                            echo "Erreur lors de la lecture du fichier";
                        }
                    ?></pre>
                <?php else: ?>
                    <div class="no-preview">
                        <div class="extension-badge">
                            <?php echo Cyla::escape(strtoupper($file_info['extension'])); ?>
                        </div>
                        <p>Aperçu non disponible pour ce type de fichier</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="share-actions">
                <div class="share-link">
                    <label for="share-url">Lien de partage :</label>
                    <div class="input-group">
                        <input type="text" 
                               id="share-url" 
                               value="<?php echo Cyla::escape($share_url); ?>" 
                               readonly>
                        <button class="btn" onclick="handleCopy('share-url', 'Lien de partage copié !')">
                            Copier
                        </button>
                    </div>
                </div>

                <div class="share-link">
                    <label for="direct-url">Lien direct :</label>
                    <div class="input-group">
                        <input type="text" 
                               id="direct-url" 
                               value="<?php echo Cyla::escape($file_url); ?>" 
                               readonly>
                        <button class="btn" onclick="handleCopy('direct-url', 'Lien direct copié !')">
                            Copier
                        </button>
                    </div>
                </div>

                <?php if (in_array($file_info['preview_type'], ['image', 'video'])): ?>
                <div class="share-link">
                    <label for="embed-code">Code d'intégration :</label>
                    <div class="input-group">
                        <input type="text" 
                               id="embed-code" 
                               value="<?php 
                                    if ($file_info['preview_type'] === 'image') {
                                        echo Cyla::escape('<img src="' . $file_url . '" alt="' . $file_info['name'] . '">');
                                    } else {
                                        echo Cyla::escape('<video controls><source src="' . $file_url . '" type="video/' . 
                                            ($file_info['extension'] === 'webm' ? 'webm' : 'mp4') . '"></video>');
                                    }
                               ?>" 
                               readonly>
                        <button class="btn" onclick="handleCopy('embed-code', 'Code d\'intégration copié !')">
                            Copier
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <a href="<?php echo Cyla::escape($file_url); ?>" 
                   class="btn btn-secondary" 
                   download="<?php echo Cyla::escape($file_info['name']); ?>">
                    Télécharger
                </a>
            </div>
        </div>
    </div>

<script>
// Fonction pour gérer la copie
async function handleCopy(elementId, message) {
    const input = document.getElementById(elementId);
    if (!input) {
        console.error(`Element with id ${elementId} not found`);
        return;
    }

    try {
        // Utiliser l'API Clipboard moderne
        await navigator.clipboard.writeText(input.value);
        
        // Créer et afficher la notification
        const notification = document.createElement('div');
        notification.className = 'notification';
        notification.textContent = message;
        document.body.appendChild(notification);
        
        // Supprimer la notification après l'animation
        setTimeout(() => {
            notification.remove();
        }, 3000);
    } catch (err) {
        console.error('Erreur lors de la copie :', err);
        alert('Erreur lors de la copie');
    }
}
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';