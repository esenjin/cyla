<?php
ob_start();

define('CYLA_CORE', true);
require_once __DIR__ . '/core.php';

if (headers_sent($filename, $linenum)) {
    exit("Headers already sent in $filename on line $linenum");
}

if (!Cyla::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error   = Cyla::input($_GET, 'error')   ?: null;
$success = Cyla::input($_GET, 'success') ?: null;

// --- Changement de mot de passe ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!Cyla::verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'Token de sécurité invalide';
    } else {
        $current_password = Cyla::input($_POST, 'current_password');
        $new_password     = Cyla::input($_POST, 'new_password');
        $confirm_password = Cyla::input($_POST, 'confirm_password');
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Tous les champs sont requis';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Les nouveaux mots de passe ne correspondent pas';
        } else {
            global $ADMIN_USERS;
            $username = $_SESSION['user'];
            $user     = $ADMIN_USERS[$username];
            if (!hash_equals($user['password'], hash(HASH_ALGO, $current_password . $user['salt']))) {
                $error = 'Mot de passe actuel incorrect';
            } else {
                $ADMIN_USERS[$username]['password'] = hash(HASH_ALGO, $new_password . $user['salt']);
                $config_content = file_get_contents(__DIR__ . '/config.php');
                $config_content = preg_replace(
                    '/\'password\' => \'' . preg_quote($user['password'], '/') . '\'/',
                    '\'password\' => \'' . $ADMIN_USERS[$username]['password'] . '\'',
                    $config_content
                );
                file_put_contents(__DIR__ . '/config.php', $config_content, LOCK_EX);
                $success = 'Mot de passe modifié avec succès';
            }
        }
    }
}

// --- Suppression de fichier ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_file') {
    if (!Cyla::verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        header('Location: admin.php?error=' . urlencode('Token de sécurité invalide'));
        exit;
    }
    $deleteName = Cyla::input($_POST, 'filename');
    if ($deleteName === '') {
        header('Location: admin.php?error=' . urlencode('Nom de fichier manquant'));
        exit;
    }
    $result = Cyla::deleteFile($deleteName, Cyla::input($_POST, 'path', 'fichiers/'));
    if ($result['success']) {
        Cyla::setFileGroups($deleteName, []);
        header('Location: admin.php?success=' . urlencode('Fichier supprimé avec succès'));
    } else {
        header('Location: admin.php?error=' . urlencode($result['error']));
    }
    exit;
}

// --- Données pour la page ---
$currentPage  = max(1, (int) Cyla::input($_GET, 'page', '1'));
$filesPerPage = (int) Cyla::input($_GET, 'perPage', '24');
$filesPerPage = in_array($filesPerPage, [24, 48, 96], true) ? $filesPerPage : 24;
$filterGroup  = Cyla::input($_GET, 'group');
$filterType   = Cyla::input($_GET, 'type');

// Définition des types de fichiers
$fileTypeFilters = [
    'image'   => ['label' => 'Images',   'icon' => '🖼',  'extensions' => ['png','jpg','jpeg','gif','webp']],
    'video'   => ['label' => 'Vidéos',   'icon' => '🎬',  'extensions' => ['webm','mp4','wmv']],
    'music'   => ['label' => 'Musique',  'icon' => '🎵',  'extensions' => ['mp3','flac','ogg','m3u','m3u8']],
    'archive' => ['label' => 'Archives', 'icon' => '📦',  'extensions' => ['zip','rar']],
    'document'=> ['label' => 'Documents','icon' => '📄',  'extensions' => ['pdf','txt']],
    'code'    => ['label' => 'Code',     'icon' => '🖥',  'extensions' => ['css']],
];

$allGroups = Cyla::listGroups();

// Fonction de filtrage par type
$filterByType = function(array $files) use ($filterType, $fileTypeFilters): array {
    if ($filterType === '' || !isset($fileTypeFilters[$filterType])) return $files;
    $allowed = $fileTypeFilters[$filterType]['extensions'];
    return array_values(array_filter($files, fn($f) => in_array($f['extension'], $allowed)));
};

if ($filterGroup !== '' && isset($allGroups[$filterGroup])) {
    // Filtrage par groupe : on charge TOUS les fichiers, on filtre, puis on pagine
    $allFileData    = Cyla::listFiles(1, PHP_INT_MAX);
    $groupFileNames = $allGroups[$filterGroup]['files'];
    $filteredAll    = array_values(array_filter($allFileData['files'], fn($f) => in_array($f['name'], $groupFileNames)));
    $filteredAll    = $filterByType($filteredAll);

    $totalFiltered = count($filteredAll);
    $totalSizeFiltered = array_sum(array_column($filteredAll, 'size'));
    $totalPages    = max(1, (int) ceil($totalFiltered / $filesPerPage));
    $currentPage   = max(1, min($currentPage, $totalPages));
    $offset        = ($currentPage - 1) * $filesPerPage;
    $files         = array_slice($filteredAll, $offset, $filesPerPage);

    $fileData = [
        'files'       => $files,
        'total'       => $totalFiltered,
        'totalSize'   => $totalSizeFiltered,
        'totalPages'  => $totalPages,
        'currentPage' => $currentPage,
        'perPage'     => $filesPerPage,
    ];
} elseif ($filterType !== '' && isset($fileTypeFilters[$filterType])) {
    // Filtrage par type uniquement
    $allFileData = Cyla::listFiles(1, PHP_INT_MAX);
    $filteredAll = $filterByType($allFileData['files']);

    $totalFiltered = count($filteredAll);
    $totalSizeFiltered = array_sum(array_column($filteredAll, 'size'));
    $totalPages    = max(1, (int) ceil($totalFiltered / $filesPerPage));
    $currentPage   = max(1, min($currentPage, $totalPages));
    $offset        = ($currentPage - 1) * $filesPerPage;
    $files         = array_slice($filteredAll, $offset, $filesPerPage);

    $fileData = [
        'files'       => $files,
        'total'       => $totalFiltered,
        'totalSize'   => $totalSizeFiltered,
        'totalPages'  => $totalPages,
        'currentPage' => $currentPage,
        'perPage'     => $filesPerPage,
    ];
} else {
    $fileData = Cyla::listFiles($currentPage, $filesPerPage);
    $files    = $fileData['files'];
}

foreach ($files as &$file) {
    $file['groups'] = Cyla::getFileGroups($file['name']);
}
unset($file);

$pageTitle = 'Administration';
ob_start();
?>

<div class="admin-container">

<!-- UPLOAD -->
<div class="card">
    <h2>Téléversement de fichier</h2>
    <div class="upload-zone" id="uploadZone">
        <input type="file" id="fileInput" multiple class="file-input" name="files[]">
        <div class="upload-content">
            <div class="upload-icon">
                <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
            </div>
            <p class="upload-text">
                Glissez-déposez vos fichiers ici<br>
                <span class="upload-text-sub">ou cliquez pour sélectionner</span>
            </p>
            <p class="upload-limit">
                Taille maximale : 100 Mo · Limite : 10 fichiers<br>
                Extensions : <?php echo implode(', ', ALLOWED_EXTENSIONS); ?>
            </p>
        </div>
    </div>
    <?php if (!empty($allGroups)): ?>
    <div class="upload-group-selector">
        <label for="uploadGroup">Ajouter au groupe :</label>
        <select id="uploadGroup">
            <option value="">— Aucun groupe —</option>
            <?php foreach ($allGroups as $gname => $gdata): ?>
                <option value="<?php echo Cyla::escape($gname); ?>"
                        style="color:<?php echo Cyla::escape($gdata['color']); ?>">
                    <?php echo Cyla::escape($gname); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div id="fileList" class="file-list"></div>
</div>

<!-- GROUPES -->
<div class="card">
    <h2>Groupes</h2>
    <div class="groups-manager">
        <div class="group-create-form">
            <input type="text" id="newGroupName" placeholder="Nom du groupe…" maxlength="50">
            <input type="color" id="newGroupColor" value="#d4846a" title="Couleur">
            <button class="btn" onclick="createGroup()">Créer</button>
        </div>
        <div class="groups-list" id="groupsList">
            <?php if (empty($allGroups)): ?>
                <p class="text-muted">Aucun groupe créé.</p>
            <?php else: ?>
                <?php foreach ($allGroups as $gname => $gdata): ?>
                <div class="group-tag-row">
                    <span class="group-badge" style="background:<?php echo Cyla::escape($gdata['color']); ?>">
                        <?php echo Cyla::escape($gname); ?>
                    </span>
                    <span class="group-count"><?php echo count($gdata['files']); ?> fichier(s)</span>
                    <button class="btn btn-danger btn-sm" title="Supprimer"
                            onclick="deleteGroup('<?php echo Cyla::escape(addslashes($gname)); ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FICHIERS -->
<div class="card">
    <div class="files-card-header">
        <h2>Fichiers hébergés</h2>

        <!-- Filtres par type -->
        <div class="type-filter">
            <?php
            $typeBase = '?perPage=' . $filesPerPage . ($filterGroup ? '&group=' . urlencode($filterGroup) : '');
            ?>
            <a href="<?php echo $typeBase; ?>"
               class="type-filter-btn <?php echo $filterType === '' ? 'active' : ''; ?>">
                Tous
            </a>
            <?php foreach ($fileTypeFilters as $typeKey => $typeDef): ?>
                <?php $isActive = ($filterType === $typeKey); ?>
                <a href="<?php echo $typeBase; ?>&type=<?php echo urlencode($typeKey); ?>"
                   class="type-filter-btn <?php echo $isActive ? 'active' : ''; ?>">
                    <span class="type-filter-icon"><?php echo $typeDef['icon']; ?></span>
                    <?php echo Cyla::escape($typeDef['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Filtres par groupe -->
        <?php if (!empty($allGroups)): ?>
        <div class="group-filter">
            <?php $groupBase = '?perPage=' . $filesPerPage . ($filterType ? '&type=' . urlencode($filterType) : ''); ?>
            <a href="<?php echo $groupBase; ?>"
               class="group-filter-btn <?php echo $filterGroup === '' ? 'active' : ''; ?>">Tous les groupes</a>
            <?php foreach ($allGroups as $gname => $gdata): ?>
                <?php $isActive = ($filterGroup === $gname); ?>
                <a href="<?php echo $groupBase; ?>&group=<?php echo urlencode($gname); ?>"
                   class="group-filter-btn <?php echo $isActive ? 'active' : ''; ?>"
                   style="border-color:<?php echo Cyla::escape($gdata['color']); ?>;
                          <?php echo $isActive ? 'background:' . Cyla::escape($gdata['color']) . ';color:#fff;' : 'color:' . Cyla::escape($gdata['color']); ?>">
                    <?php echo Cyla::escape($gname); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($files)): ?>
        <p class="text-muted">Aucun fichier<?php
            if ($filterGroup) echo ' dans ce groupe';
            elseif ($filterType && isset($fileTypeFilters[$filterType])) echo ' de type « ' . Cyla::escape($fileTypeFilters[$filterType]['label']) . ' »';
            else echo ' hébergé';
        ?>.</p>
    <?php else: ?>
        <div class="files-header">
            <p class="files-count">
                <?php
                $sizeMo = round($fileData['totalSize'] / 1024 / 1024, 2);
                if ($filterGroup || $filterType) {
                    $label = '';
                    if ($filterGroup) $label .= '« ' . Cyla::escape($filterGroup) . ' »';
                    if ($filterType && isset($fileTypeFilters[$filterType])) {
                        if ($label) $label .= ' · ';
                        $label .= $fileTypeFilters[$filterType]['icon'] . ' ' . Cyla::escape($fileTypeFilters[$filterType]['label']);
                    }
                    echo $fileData['total'] . ' fichier(s) (' . $sizeMo . ' Mo) — ' . $label;
                } else {
                    $st = (($fileData['currentPage'] - 1) * $fileData['perPage']) + 1;
                    $en = min($st + count($fileData['files']) - 1, $fileData['total']);
                    echo "Affichage de {$st}–{$en} sur {$fileData['total']} fichiers ({$sizeMo} Mo)";
                }
                ?>
            </p>
            <div class="files-per-page-selector">
                <label for="filesPerPage">Par page :</label>
                <select id="filesPerPage" onchange="changeFilesPerPage(this.value)">
                    <option value="24" <?php echo ($filesPerPage==24)?'selected':''; ?>>24</option>
                    <option value="48" <?php echo ($filesPerPage==48)?'selected':''; ?>>48</option>
                    <option value="96" <?php echo ($filesPerPage==96)?'selected':''; ?>>96</option>
                </select>
            </div>
        </div>

        <div class="file-list">
        <?php foreach ($files as $file): ?>
            <div class="file-item">
                <div class="file-preview">
                    <?php if ($file['preview_type'] === 'image'): ?>
                        <img src="<?php echo $file['path'] . Cyla::escape($file['name']); ?>"
                             alt="<?php echo Cyla::escape($file['name']); ?>">
                    <?php else: ?>
                        <div class="preview-placeholder"><?php echo strtoupper($file['extension']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="file-info">
                    <?php if (!empty($file['groups'])): ?>
                    <div class="file-group-badges">
                        <?php foreach ($file['groups'] as $g): ?>
                            <span class="group-badge group-badge-sm"
                                  style="background:<?php echo Cyla::escape($g['color']); ?>">
                                <?php echo Cyla::escape($g['name']); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <p class="file-name"><?php echo Cyla::escape($file['name']); ?></p>
                    <p class="file-meta">
                        <?php echo Cyla::escape(round($file['size']/1024,2)); ?> Ko
                        · <?php echo date('d/m/Y H:i', $file['uploaded']); ?>
                    </p>
                    <div class="file-actions">
                        <a href="share.php?file=<?php echo urlencode($file['name']); ?>&path=<?php echo urlencode($file['path']); ?>"
                           class="btn btn-secondary" title="Voir" target="_blank">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </a>
                        <button class="btn" title="Copier lien de partage"
                                onclick="copyToClipboard('<?php echo SITE_URL; ?>share.php?file=<?php echo urlencode($file['name']); ?>&path=<?php echo urlencode($file['path']); ?>', 'Lien copié !')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                            </svg>
                        </button>
                        <button class="btn" title="Copier URL directe"
                                onclick="copyToClipboard('<?php echo SITE_URL . $file['path'] . urlencode($file['name']); ?>', 'URL copiée !')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                            </svg>
                        </button>
                        <button class="btn btn-groups" title="Gérer les groupes"
                                onclick="openGroupModal('<?php echo Cyla::escape(addslashes($file['name'])); ?>', <?php echo json_encode(array_column($file['groups'], 'name')); ?>)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <rect x="2" y="7" width="20" height="14" rx="2"/>
                                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
                            </svg>
                        </button>
                        <button class="btn btn-danger" title="Supprimer"
                                onclick="confirmDelete('<?php echo Cyla::escape(addslashes($file['name'])); ?>', '<?php echo Cyla::escape($file['path']); ?>')">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <form id="deleteForm-<?php echo Cyla::escape($file['name']); ?>"
                  action="admin.php" method="POST" style="display:none;">
                <input type="hidden" name="action"     value="delete_file">
                <input type="hidden" name="filename"   value="<?php echo Cyla::escape($file['name']); ?>">
                <input type="hidden" name="path"       value="<?php echo Cyla::escape($file['path']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo Cyla::generateCSRFToken(); ?>">
            </form>
        <?php endforeach; ?>
        </div>

        <?php if ($fileData['totalPages'] > 1): ?>
        <div class="pagination">
            <?php
            $gParam = ($filterGroup ? 'group='.urlencode($filterGroup).'&' : '') . ($filterType ? 'type='.urlencode($filterType).'&' : '');
            ?>
            <?php if ($fileData['currentPage'] > 1): ?>
                <a href="?<?php echo $gParam; ?>page=<?php echo $fileData['currentPage']-1; ?>&perPage=<?php echo $filesPerPage; ?>" class="pagination-link">&laquo;</a>
            <?php endif; ?>
            <?php
            $pS = max(1, $fileData['currentPage']-2);
            $pE = min($fileData['totalPages'], $fileData['currentPage']+2);
            if ($pS > 1) { echo '<a href="?'.$gParam.'page=1&perPage='.$filesPerPage.'" class="pagination-link">1</a>'; if ($pS>2) echo '<span class="pagination-ellipsis">…</span>'; }
            for ($i=$pS;$i<=$pE;$i++) { $a=$i===$fileData['currentPage']?'pagination-active':''; echo '<a href="?'.$gParam.'page='.$i.'&perPage='.$filesPerPage.'" class="pagination-link '.$a.'">'.$i.'</a>'; }
            if ($pE < $fileData['totalPages']) { if ($pE<$fileData['totalPages']-1) echo '<span class="pagination-ellipsis">…</span>'; echo '<a href="?'.$gParam.'page='.$fileData['totalPages'].'&perPage='.$filesPerPage.'" class="pagination-link">'.$fileData['totalPages'].'</a>'; }
            ?>
            <?php if ($fileData['currentPage'] < $fileData['totalPages']): ?>
                <a href="?<?php echo $gParam; ?>page=<?php echo $fileData['currentPage']+1; ?>&perPage=<?php echo $filesPerPage; ?>" class="pagination-link">&raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- MODALE GROUPES FICHIER -->
<div id="groupModalOverlay" class="confirmation-overlay" style="display:none;" onclick="closeGroupModal()"></div>
<div id="groupModal" class="confirmation-dialog group-modal" style="display:none;">
    <h3>Groupes du fichier</h3>
    <p id="groupModalFilename" class="file-meta" style="word-break:break-all;margin-bottom:1rem;"></p>
    <div id="groupModalCheckboxes" class="group-modal-checkboxes"></div>
    <div class="confirmation-actions">
        <button class="btn btn-secondary" onclick="closeGroupModal()">Annuler</button>
        <button class="btn" onclick="saveFileGroups()">Enregistrer</button>
    </div>
</div>

<script>
const CSRF_TOKEN  = "<?php echo Cyla::generateCSRFToken(); ?>";
const ALL_GROUPS  = <?php echo json_encode($allGroups); ?>;
const maxFileSize       = <?php echo MAX_FILE_SIZE; ?>;
const allowedExtensions = <?php echo json_encode(ALLOWED_EXTENSIONS); ?>;

// ---- Upload ----
const uploadZone = document.getElementById('uploadZone');
const fileInput  = document.getElementById('fileInput');
const fileListEl = document.getElementById('fileList');

['dragenter','dragover','dragleave','drop'].forEach(ev => {
    uploadZone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); });
    document.body.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); });
});
['dragenter','dragover'].forEach(ev => uploadZone.addEventListener(ev, () => uploadZone.classList.add('upload-zone-active')));
['dragleave','drop'].forEach(ev => uploadZone.addEventListener(ev, () => uploadZone.classList.remove('upload-zone-active')));
uploadZone.addEventListener('drop', e => handleFiles({ target: { files: e.dataTransfer.files } }));
fileInput.addEventListener('change', handleFiles);
uploadZone.addEventListener('click', () => fileInput.click());

function isValidFile(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowedExtensions.includes(ext)) return { valid: false, error: `Extension .${ext} non autorisée` };
    if (file.size > maxFileSize) return { valid: false, error: 'Fichier trop volumineux (max 100 Mo)' };
    return { valid: true };
}

function handleFiles(e) {
    const files = Array.from(e.target.files);
    if (files.length > 10) { alert('Maximum 10 fichiers à la fois.'); return; }
    fileListEl.innerHTML = '';
    files.forEach(file => {
        const v  = isValidFile(file);
        const el = document.createElement('div');
        el.className = 'file-item ' + (v.valid ? '' : 'file-item-error');
        el.innerHTML = `<div class="file-item-content">
            <div class="file-item-name">${file.name}</div>
            <div class="file-item-size">${(file.size/1024/1024).toFixed(2)} Mo</div>
            ${v.valid ? '<div class="file-item-progress"><div class="progress-bar"></div></div>' : `<div class="file-item-error-message">${v.error}</div>`}
        </div>`;
        fileListEl.appendChild(el);
        if (v.valid) uploadFile(file, el);
    });
}

async function uploadFile(file, element) {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('csrf_token', CSRF_TOKEN);
    const gs = document.getElementById('uploadGroup');
    if (gs && gs.value) fd.append('group', gs.value);
    try {
        const res  = await fetch('api.php', { method: 'POST', body: fd });
        const data = await res.json();
        element.classList.add(data.success ? 'file-item-success' : 'file-item-error');
        if (data.success) setTimeout(() => window.location.reload(), 2000);
    } catch { element.classList.add('file-item-error'); }
}

// ---- Groupes ----
async function createGroup() {
    const name  = document.getElementById('newGroupName').value.trim();
    const color = document.getElementById('newGroupColor').value;
    if (!name) { alert('Entrez un nom de groupe.'); return; }
    const fd = new FormData();
    fd.append('action', 'create_group');
    fd.append('name', name);
    fd.append('color', color);
    fd.append('csrf_token', CSRF_TOKEN);
    const res  = await fetch('api.php', { method: 'POST', body: fd });
    const data = await res.json();
    data.success ? window.location.reload() : alert(data.error || 'Erreur.');
}

async function deleteGroup(name) {
    if (!confirm(`Supprimer le groupe « ${name} » ?\nLes fichiers ne seront pas supprimés.`)) return;
    const fd = new FormData();
    fd.append('action', 'delete_group');
    fd.append('name', name);
    fd.append('csrf_token', CSRF_TOKEN);
    const res  = await fetch('api.php', { method: 'POST', body: fd });
    const data = await res.json();
    data.success ? window.location.reload() : alert(data.error || 'Erreur.');
}

// ---- Modale groupes fichier ----
let _modalFilename = '';
function openGroupModal(filename, currentGroups) {
    _modalFilename = filename;
    document.getElementById('groupModalFilename').textContent = filename;
    const container = document.getElementById('groupModalCheckboxes');
    container.innerHTML = '';
    const entries = Object.entries(ALL_GROUPS);
    if (entries.length === 0) {
        container.innerHTML = '<p class="text-muted">Aucun groupe disponible.</p>';
    } else {
        entries.forEach(([gname, gdata]) => {
            const label = document.createElement('label');
            label.className = 'group-checkbox-label';
            label.innerHTML = `<input type="checkbox" value="${gname}" ${currentGroups.includes(gname)?'checked':''}>
                <span class="group-badge" style="background:${gdata.color}">${gname}</span>`;
            container.appendChild(label);
        });
    }
    document.getElementById('groupModalOverlay').style.display = 'block';
    document.getElementById('groupModal').style.display = 'block';
}

function closeGroupModal() {
    document.getElementById('groupModalOverlay').style.display = 'none';
    document.getElementById('groupModal').style.display = 'none';
}

async function saveFileGroups() {
    const groups = Array.from(document.querySelectorAll('#groupModalCheckboxes input:checked')).map(cb => cb.value);
    const fd = new FormData();
    fd.append('action', 'set_file_groups');
    fd.append('filename', _modalFilename);
    fd.append('csrf_token', CSRF_TOKEN);
    groups.forEach(g => fd.append('groups[]', g));
    const res  = await fetch('api.php', { method: 'POST', body: fd });
    const data = await res.json();
    data.success ? window.location.reload() : alert(data.error || 'Erreur.');
}

// ---- Utilitaires ----
function copyToClipboard(text, msg) {
    navigator.clipboard.writeText(text)
        .then(()  => showNotif(msg))
        .catch(()  => showNotif('Erreur lors de la copie', true));
}

function showNotif(msg, isError=false) {
    const n = document.createElement('div');
    n.className = 'notification' + (isError ? ' error' : '');
    n.textContent = msg;
    document.body.appendChild(n);
    setTimeout(() => n.remove(), 3000);
}

function confirmDelete(filename, path) {
    const overlay = document.createElement('div');
    overlay.className = 'confirmation-overlay';
    const dialog  = document.createElement('div');
    dialog.className = 'confirmation-dialog';
    dialog.innerHTML = `<h3>Confirmer la suppression</h3>
        <p>Supprimer « ${filename} » ?</p>
        <div class="confirmation-actions">
            <button class="btn btn-secondary" onclick="closeConfirmDialog()">Annuler</button>
            <button class="btn btn-danger"    onclick="submitDelete('${filename}')">Supprimer</button>
        </div>`;
    document.body.appendChild(overlay);
    document.body.appendChild(dialog);
}

function closeConfirmDialog() {
    document.querySelector('.confirmation-overlay')?.remove();
    document.querySelector('.confirmation-dialog')?.remove();
}

function submitDelete(filename) {
    document.getElementById('deleteForm-' + filename)?.submit();
    closeConfirmDialog();
}

function changeFilesPerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('perPage', perPage);
    url.searchParams.delete('page'); // reset to page 1
    window.location.href = url.toString();
}
</script>
<script>window.CSRF_TOKEN = "<?php echo Cyla::generateCSRFToken(); ?>";</script>
<script src="js/password-modal.js"></script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
ob_end_flush();
