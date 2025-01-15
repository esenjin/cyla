<?php
// debug :
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('CYLA_CORE', true);
require_once 'core.php';

// Vérifions si des headers ont déjà été envoyés
if (headers_sent($filename, $linenum)) {
    exit("Headers already sent in $filename on line $linenum");
}

// Vérifier si l'utilisateur est connecté
if (!Cyla::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;

// Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!isset($_POST['csrf_token']) || !Cyla::verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Token de sécurité invalide';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Tous les champs sont requis';
        } else if ($new_password !== $confirm_password) {
            $error = 'Les nouveaux mots de passe ne correspondent pas';
        } else {
            global $ADMIN_USERS;
            $username = $_SESSION['user'];
            $user = $ADMIN_USERS[$username];
            
            // Vérifier l'ancien mot de passe
            if (hash(HASH_ALGO, $current_password . $user['salt']) !== $user['password']) {
                $error = 'Mot de passe actuel incorrect';
            } else {
                // Mettre à jour le mot de passe
                $ADMIN_USERS[$username]['password'] = hash(HASH_ALGO, $new_password . $user['salt']);
                
                // Sauvegarder dans le fichier de configuration
                $config_content = file_get_contents('config.php');
                $config_content = preg_replace(
                    '/\'password\' => \'' . preg_quote($user['password'], '/') . '\'/',
                    '\'password\' => \'' . $ADMIN_USERS[$username]['password'] . '\'',
                    $config_content
                );
                file_put_contents('config.php', $config_content);
                
                $success = 'Mot de passe modifié avec succès';
            }
        }
    }
}

// Traitement de l'upload de fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    if (!isset($_POST['csrf_token']) || !Cyla::verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Token de sécurité invalide';
    } else if (!isset($_FILES['file'])) {
        $error = 'Aucun fichier n\'a été envoyé';
    } else {
        $validation = Cyla::validateUpload($_FILES['file']);
        
        if (!$validation['valid']) {
            $error = $validation['error'];
        } else {
            $filename = Cyla::generateUniqueFilename($_FILES['file']['name']);
            $destination = UPLOAD_DIR . $filename;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
                $success = 'Fichier uploadé avec succès';
            } else {
                $error = 'Erreur lors de l\'upload du fichier';
            }
        }
    }
}

// Liste des fichiers
$files = Cyla::listFiles();

// Contenu de la page
$pageTitle = 'Administration';
ob_start(); ?>

<div class="admin-container">
<div class="card">
        <h2>Téléversement de fichier</h2>
        
        <div class="upload-zone" id="uploadZone">
            <input type="file" id="fileInput" multiple class="file-input" name="files[]">
            <div class="upload-content">
                <div class="upload-icon">
                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="17 8 12 3 7 8" />
                        <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                </div>
                <p class="upload-text">
                    Glissez-déposez vos fichiers ici<br>
                    <span class="upload-text-sub">ou cliquez pour sélectionner</span>
                </p>
                <p class="upload-limit">
                    Taille maximale : 100 Mo<br>
                    Extensions : <?php echo implode(', ', ALLOWED_EXTENSIONS); ?>
                </p>
            </div>
        </div>

        <div id="fileList" class="file-list"></div>
    </div>

    <script>
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const maxFileSize = <?php echo MAX_FILE_SIZE; ?>;
        const allowedExtensions = <?php echo json_encode(ALLOWED_EXTENSIONS); ?>;

        // Gestion du drag & drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            uploadZone.classList.add('upload-zone-active');
        }

        function unhighlight(e) {
            uploadZone.classList.remove('upload-zone-active');
        }

        // Gestion du drop
        uploadZone.addEventListener('drop', handleDrop, false);
        fileInput.addEventListener('change', handleFiles, false);
        uploadZone.addEventListener('click', () => fileInput.click());

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles({ target: { files: files } });
        }

        function isValidFile(file) {
            const ext = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(ext)) {
                return { valid: false, error: `Extension .${ext} non autorisée` };
            }
            if (file.size > maxFileSize) {
                return { valid: false, error: 'Fichier trop volumineux (max 100 Mo)' };
            }
            return { valid: true };
        }

        function handleFiles(e) {
            const files = Array.from(e.target.files);
            
            // Vider la liste précédente
            fileList.innerHTML = '';
            
            files.forEach(file => {
                const validation = isValidFile(file);
                const fileElement = createFileElement(file, validation);
                fileList.appendChild(fileElement);
                
                if (validation.valid) {
                    uploadFile(file, fileElement);
                }
            });
        }

        function createFileElement(file, validation) {
            const div = document.createElement('div');
            div.className = 'file-item ' + (validation.valid ? '' : 'file-item-error');
            
            div.innerHTML = `
                <div class="file-item-content">
                    <div class="file-item-name">${file.name}</div>
                    <div class="file-item-size">${(file.size / 1024 / 1024).toFixed(2)} Mo</div>
                    ${validation.valid ? 
                        '<div class="file-item-progress"><div class="progress-bar"></div></div>' : 
                        `<div class="file-item-error-message">${validation.error}</div>`
                    }
                </div>
            `;
            
            return div;
        }

        async function uploadFile(file, element) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('csrf_token', '<?php echo Cyla::generateCSRFToken(); ?>');
            
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Erreur lors de l\'upload');
                }

                const result = await response.json();
                
                if (result.success) {
                    element.classList.add('file-item-success');
                } else {
                    element.classList.add('file-item-error');
                }
            } catch (error) {
                element.classList.add('file-item-error');
                console.error('Error:', error);
            }
        }
    </script>

    <div class="card">
        <h2>Fichiers hébergés</h2>
        <?php if (empty($files)): ?>
            <p class="text-muted">Aucun fichier hébergé</p>
        <?php else: ?>
            <div class="file-list">
                <?php foreach ($files as $file): ?>
                    <div class="file-item">
                        <div class="file-preview">
                            <?php if ($file['preview_type'] === 'image'): ?>
                                <img src="fichiers/<?php echo Cyla::escape($file['name']); ?>" alt="<?php echo Cyla::escape($file['name']); ?>">
                            <?php else: ?>
                                <div class="preview-placeholder">
                                    <?php echo strtoupper($file['extension']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="file-info">
                            <p class="file-name"><?php echo Cyla::escape($file['name']); ?></p>
                            <p class="file-meta">
                                <?php echo Cyla::escape(round($file['size'] / 1024, 2)); ?> Ko
                                · <?php echo date('d/m/Y H:i', $file['uploaded']); ?>
                            </p>
                            <div class="file-actions">
                                <a href="share.php?file=<?php echo urlencode($file['name']); ?>" class="btn btn-secondary" target="_blank">Voir</a>
                                <button class="btn" onclick="copyShareLink('<?php echo SITE_URL; ?>share.php?file=<?php echo urlencode($file['name']); ?>')">Copier le lien</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Styles spécifiques à la page d'administration */
.admin-container {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-lg);
}

.admin-container h2 {
    color: var(--color-primary);
    margin-bottom: var(--spacing-md);
}

.help-text {
    font-size: 0.9rem;
    color: var(--color-text-muted);
    margin-top: var(--spacing-xs);
}

.preview-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--color-bg);
    color: var(--color-text-muted);
    font-size: 2rem;
    font-weight: bold;
}

.file-name {
    font-weight: bold;
    word-break: break-all;
}

.file-meta {
    font-size: 0.9rem;
    color: var(--color-text-muted);
    margin: var(--spacing-xs) 0;
}

.file-actions {
    display: flex;
    gap: var(--spacing-sm);
    margin-top: var(--spacing-sm);
}
</style>

<script>
// Fonction pour copier le lien de partage
function copyShareLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('Lien copié dans le presse-papier');
    }).catch(err => {
        console.error('Erreur lors de la copie :', err);
        alert('Erreur lors de la copie du lien');
    });
}
</script>
<div id="passwordModalRoot"></div>
<script>
    window.CSRF_TOKEN = "<?php echo Cyla::generateCSRFToken(); ?>";
</script>
<script src="js/password-modal.js"></script>
<?php
$content = ob_get_clean();
require 'layout.php';
ob_end_flush();