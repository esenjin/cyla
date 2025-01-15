<?php
define('CYLA_CORE', true);
require_once 'core.php';

$error = null;
$file_info = null;

// Vérifier si un fichier est spécifié
if (!isset($_GET['file'])) {
    $error = 'Aucun fichier spécifié';
} else {
    $filename = $_GET['file'];
    $filepath = UPLOAD_DIR . $filename;
    
    // Vérifier si le fichier existe
    if (!file_exists($filepath)) {
        $error = 'Fichier introuvable';
    } else {
        // Récupérer les informations du fichier
        $file_info = [
            'name' => $filename,
            'size' => filesize($filepath),
            'extension' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
            'preview_type' => getPreviewType(pathinfo($filename, PATHINFO_EXTENSION))
        ];
    }
}

// Construction de l'URL absolue du fichier
$file_url = SITE_URL . 'fichiers/' . ($file_info ? rawurlencode($file_info['name']) : '');
$share_url = SITE_URL . 'share.php?file=' . ($file_info ? rawurlencode($file_info['name']) : '');

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
                        $content = file_get_contents($filepath);
                        echo Cyla::escape($content);
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

<style>
/* Styles spécifiques à la page de partage */
.share-container {
    max-width: 800px;
    margin: 0 auto;
}

.share-container h1 {
    color: var(--color-primary);
    margin-bottom: var(--spacing-sm);
    word-break: break-all;
}

.file-meta {
    color: var(--color-text-muted);
    margin-bottom: var(--spacing-lg);
}

.preview-container {
    background-color: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-lg);
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.preview-content {
    max-width: 100%;
    max-height: 600px;
}

.text-preview {
    width: 100%;
    max-height: 600px;
    overflow: auto;
    padding: var(--spacing-md);
    background-color: var(--color-bg);
    color: var(--color-text);
    font-family: monospace;
    white-space: pre-wrap;
}

.no-preview {
    text-align: center;
    padding: var(--spacing-lg);
    color: var(--color-text-muted);
}

.extension-badge {
    background-color: var(--color-bg-alt);
    color: var(--color-primary);
    padding: var(--spacing-md) var(--spacing-lg);
    border-radius: var(--border-radius);
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: var(--spacing-md);
}

.share-actions {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
}

.share-link {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.input-group {
    display: flex;
    gap: var(--spacing-sm);
}

.input-group input {
    flex: 1;
}

/* Styles responsifs */
@media (max-width: 768px) {
    .input-group {
        flex-direction: column;
    }
    
    .input-group .btn {
        width: 100%;
    }
}

/* Animation de notification */
.notification {
    position: fixed;
    bottom: var(--spacing-lg);
    right: var(--spacing-lg);
    background-color: var(--color-success);
    color: white;
    padding: var(--spacing-md) var(--spacing-lg);
    border-radius: var(--border-radius);
    animation: slideIn 0.3s ease-out, fadeOut 0.3s ease-in 2.7s forwards;
    z-index: 1000;
}

@keyframes slideIn {
    from { transform: translateY(100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}
</style>

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
require 'layout.php';