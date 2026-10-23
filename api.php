<?php
declare(strict_types=1);

define('CYLA_CORE', true);
require_once __DIR__ . '/core.php';

set_time_limit(300);

header('Content-Type: application/json');

if (!Cyla::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Token CSRF en POST, ou dans l'URL pour les requêtes GET (ex : liste des groupes)
if (!Cyla::verifyCSRFToken($_POST['csrf_token'] ?? null) && !Cyla::verifyCSRFToken($_GET['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF invalide']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = Cyla::input($method === 'POST' ? $_POST : $_GET, 'action');

// === Upload de fichier ===
if ($method === 'POST' && isset($_FILES['file'])) {
    try {
        $uploadDir = UPLOAD_DIR;
        $freeSpace = disk_free_space($uploadDir);
        if ($freeSpace !== false && $freeSpace < (int) ($_FILES['file']['size'] ?? 0)) {
            throw new RuntimeException('Espace disque insuffisant');
        }

        $validation = Cyla::validateUpload($_FILES['file']);
        if (!$validation['valid']) {
            throw new RuntimeException($validation['error']);
        }

        $filename    = Cyla::generateUniqueFilename($_FILES['file']['name']);
        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
            throw new RuntimeException('Erreur lors du déplacement du fichier');
        }

        clearstatcache(true, $destination);
        if (!is_file($destination) || filesize($destination) !== $_FILES['file']['size']) {
            @unlink($destination);
            throw new RuntimeException('Erreur d\'intégrité du fichier');
        }

        // Assigner automatiquement au groupe si précisé
        $group = Cyla::input($_POST, 'group');
        if ($group !== '') {
            Cyla::setFileGroups($filename, [$group]);
        }

        echo json_encode([
            'success' => true,
            'file' => [
                'name' => $filename,
                'size' => filesize($destination),
                'url'  => 'share.php?file=' . urlencode($filename),
            ],
        ]);
    } catch (RuntimeException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$response = match (true) {
    // === Créer un groupe ===
    $method === 'POST' && $action === 'create_group' => Cyla::createGroup(
        trim(Cyla::input($_POST, 'name')),
        Cyla::input($_POST, 'color', '#d4846a'),
    ),

    // === Supprimer un groupe ===
    $method === 'POST' && $action === 'delete_group' => Cyla::deleteGroup(trim(Cyla::input($_POST, 'name'))),

    // === Assigner des groupes à un fichier ===
    $method === 'POST' && $action === 'set_file_groups' => Cyla::setFileGroups(
        Cyla::input($_POST, 'filename'),
        isset($_POST['groups']) ? (array) $_POST['groups'] : [],
    ),

    // === Lister les groupes ===
    $method === 'GET' && $action === 'list_groups' => ['success' => true, 'groups' => Cyla::listGroups()],

    default => null,
};

if ($response === null) {
    http_response_code(405);
    $response = ['error' => 'Méthode non autorisée'];
}

echo json_encode($response);
