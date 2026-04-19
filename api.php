<?php
define('CYLA_CORE', true);
require_once 'core.php';

set_time_limit(300);

if (!Cyla::isLoggedIn()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

if (!isset($_POST['csrf_token']) || !Cyla::verifyCSRFToken($_POST['csrf_token'])) {
    // Pour les requêtes GET avec token dans l'URL (ex: suppression de groupe)
    if (!isset($_GET['csrf_token']) || !Cyla::verifyCSRFToken($_GET['csrf_token'])) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF invalide']);
        exit;
    }
}

header('Content-Type: application/json');

// === Upload de fichier ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $uploadDir = UPLOAD_DIR;
        $freeSpace = disk_free_space($uploadDir);
        if ($freeSpace < $_FILES['file']['size']) {
            throw new Exception('Espace disque insuffisant');
        }

        $validation = Cyla::validateUpload($_FILES['file']);
        if (!$validation['valid']) {
            throw new Exception($validation['error']);
        }

        $filename = Cyla::generateUniqueFilename($_FILES['file']['name']);
        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
            throw new Exception('Erreur lors du déplacement du fichier');
        }

        if (!file_exists($destination) || filesize($destination) !== $_FILES['file']['size']) {
            unlink($destination);
            throw new Exception('Erreur d\'intégrité du fichier');
        }

        // Assigner automatiquement au groupe si précisé
        if (!empty($_POST['group'])) {
            Cyla::setFileGroups($filename, [$_POST['group']]);
        }

        echo json_encode([
            'success' => true,
            'file' => [
                'name' => $filename,
                'size' => filesize($destination),
                'url' => 'share.php?file=' . urlencode($filename)
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// === Créer un groupe ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
    $name  = trim($_POST['name'] ?? '');
    $color = $_POST['color'] ?? '#d4846a';
    echo json_encode(Cyla::createGroup($name, $color));
    exit;
}

// === Supprimer un groupe ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_group') {
    $name = trim($_POST['name'] ?? '');
    echo json_encode(Cyla::deleteGroup($name));
    exit;
}

// === Assigner des groupes à un fichier ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_file_groups') {
    $filename = $_POST['filename'] ?? '';
    $groups   = isset($_POST['groups']) ? (array)$_POST['groups'] : [];
    echo json_encode(Cyla::setFileGroups($filename, $groups));
    exit;
}

// === Lister les groupes ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list_groups') {
    echo json_encode(['success' => true, 'groups' => Cyla::listGroups()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);
