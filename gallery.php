<?php
// Configuration
define('PASSWORD', 'votre_mot_de_passe');
define('FILES_DIR', './file');
define('FILES_PER_PAGE', 50);

session_start();
$authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['password']) && $_POST['password'] === PASSWORD) {
        $_SESSION['authenticated'] = true;
        $authenticated = true;
    } elseif (isset($_POST['page'])) {
        // API pour le chargement progressif
        $page = intval($_POST['page']);
        $sortBy = $_POST['sortBy'] ?? 'date';
        $sortDesc = $_POST['sortDesc'] ?? 'true';
        
        $files = getFiles($page, $sortBy, $sortDesc === 'true');
        header('Content-Type: application/json');
        echo json_encode($files);
        exit;
    }
}

function getFiles($page = 0, $sortBy = 'date', $sortDesc = true) {
    $allFiles = array_diff(scandir(FILES_DIR), ['.', '..']);
    $files = [];
    
    foreach ($allFiles as $file) {
        $path = FILES_DIR . '/' . $file;
        if (is_file($path)) {
            $files[] = [
                'name' => $file,
                'url' => 'file/' . rawurlencode($file),
                'shareUrl' => 'share/' . rawurlencode($file),
                'date' => filemtime($path),
                'type' => strtolower(pathinfo($file, PATHINFO_EXTENSION))
            ];
        }
    }

    // Tri
    usort($files, function($a, $b) use ($sortBy, $sortDesc) {
        $result = $sortBy === 'date' 
            ? $b['date'] - $a['date']
            : strcasecmp($a['name'], $b['name']);
        return $sortDesc ? $result : -$result;
    });

    // Pagination
    $offset = $page * FILES_PER_PAGE;
    return array_slice($files, $offset, FILES_PER_PAGE);
}

if (!$authenticated) {
    // Afficher le formulaire de connexion
    include 'header.php';
    ?>
    <form class="login-form" method="POST">
        <input type="password" name="password" placeholder="Mot de passe">
        <button type="submit">Accéder</button>
    </form>
    <?php
    include 'footer.php';
    exit;
}

$initialFiles = getFiles();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Galerie de fichiers</title>
    <style>
        :root {
            --bg-dark: #1a1a1a;
            --bg-card: #2d2d2d;
            --color-beige: #d4c4a8;
            --color-beige-dark: #8b7355;
        }

        body {
            background: var(--bg-dark);
            color: var(--color-beige);
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        .controls {
            position: sticky;
            top: 0;
            background: var(--bg-dark);
            padding: 10px 0;
            margin-bottom: 20px;
            z-index: 100;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .file-card {
            background: var(--bg-card);
            border-radius: 8px;
            overflow: hidden;
        }

        .preview {
            height: 150px;
            position: relative;
            background: #000;
        }

        .preview img, .preview video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .preview audio {
            position: absolute;
            bottom: 10px;
            left: 5%;
            width: 90%;
        }

        .info {
            padding: 10px;
        }

        .date {
            font-size: 0.8em;
            color: var(--color-beige-dark);
        }

        #loading {
            text-align: center;
            padding: 20px;
            display: none;
        }

        .file-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 10px;
            text-align: center;
            word-break: break-word;
            font-size: 1.2em;
        }

        .info .name {
            color: var(--color-beige);
            text-decoration: none;
            word-break: break-all;
            display: block;
            padding: 5px 0;
        }

.info .name:hover {
    color: white;
    text-decoration: underline;
}
    </style>
</head>
<body>
    <div class="controls">
        <button onclick="changeSortBy('date')" id="sortDate">
            Date <?= $sortBy === 'date' ? ($sortDesc ? '↓' : '↑') : '' ?>
        </button>
        <button onclick="changeSortBy('name')" id="sortName">
            Nom <?= $sortBy === 'name' ? ($sortDesc ? '↓' : '↑') : '' ?>
        </button>
    </div>

    <div class="grid" id="fileGrid">
    </div>
    <div id="loading">Chargement...</div>

    <script>
    let page = 0;
    let loading = false;
    let sortBy = 'date';
    let sortDesc = true;
    let noMoreFiles = false;

function createFileCard(file) {
    const card = document.createElement('div');
    card.className = 'file-card';
    
    const preview = document.createElement('div');
    preview.className = 'preview';
    
    const ext = file.type.toLowerCase();
    let previewContent = '';
    
    if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
        previewContent = `<img src="${file.url}" loading="lazy" alt="${file.name}">`;
    } else if (['mp3', 'flac', 'ogg'].includes(ext)) {
        previewContent = `<audio controls src="${file.url}"></audio>`;
    } else if (['mp4', 'webm', 'wmv'].includes(ext)) {
        previewContent = `<video controls loading="lazy"><source src="${file.url}" type="video/${ext}"></video>`;
    } else {
        const icon = getFileIcon(ext);
        previewContent = `<div class="file-icon">${icon} ${file.name}</div>`;
    }
    
    card.innerHTML = `
        <div class="preview">${previewContent}</div>
        <div class="info">
            <a href="${file.shareUrl}" target="_blank" class="name" title="Ouvrir dans un nouvel onglet">${file.name}</a>
            <div class="date">${new Date(file.date * 1000).toLocaleString()}</div>
        </div>
    `;
    
    return card;
}

function getFileIcon(ext) {
    const icons = {
        'pdf': '📄',
        'zip': '📦',
        'rar': '📦',
        'txt': '📝',
        'm3u': '🎵',
        'm3u8': '🎵',
        'css': '📰'
    };
    return icons[ext] || '📎';
}

    async function loadFiles() {
        if (loading || noMoreFiles) return;
        
        loading = true;
        document.getElementById('loading').style.display = 'block';
        
        const formData = new FormData();
        formData.append('page', page);
        formData.append('sortBy', sortBy);
        formData.append('sortDesc', sortDesc);
        
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const files = await response.json();
            
            if (files.length < <?= FILES_PER_PAGE ?>) {
                noMoreFiles = true;
            }
            
            const grid = document.getElementById('fileGrid');
            files.forEach(file => {
                grid.appendChild(createFileCard(file));
            });
            
            page++;
        } catch (error) {
            console.error('Erreur de chargement:', error);
        } finally {
            loading = false;
            document.getElementById('loading').style.display = 'none';
        }
    }

    function changeSortBy(newSortBy) {
        if (newSortBy === sortBy) {
            sortDesc = !sortDesc;
        } else {
            sortBy = newSortBy;
            sortDesc = true;
        }
        
        // Réinitialiser
        page = 0;
        noMoreFiles = false;
        document.getElementById('fileGrid').innerHTML = '';
        loadFiles();
        
        // Mettre à jour les boutons
        document.getElementById('sortDate').textContent = 
            `Date ${sortBy === 'date' ? (sortDesc ? '↓' : '↑') : ''}`;
        document.getElementById('sortName').textContent = 
            `Nom ${sortBy === 'name' ? (sortDesc ? '↓' : '↑') : ''}`;
    }

    // Détecter le scroll pour charger plus de fichiers
    window.addEventListener('scroll', () => {
        if ((window.innerHeight + window.scrollY) >= document.documentElement.scrollHeight - 500) {
            loadFiles();
        }
    });

    // Charger les premiers fichiers
    loadFiles();
    </script>
</body>
</html>
