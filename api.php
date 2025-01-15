<?php
define('CYLA_CORE', true);
require_once 'core.php';

// Vérifier si l'utilisateur est connecté
if (!Cyla::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier le token CSRF
if (!isset($_POST['csrf_token']) || !Cyla::verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF invalide']);
    exit;
}

// Gérer l'upload de fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $validation = Cyla::validateUpload($_FILES['file']);
    
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $validation['error']]);
        exit;
    }

    $filename = Cyla::generateUniqueFilename($_FILES['file']['name']);
    $destination = UPLOAD_DIR . $filename;
    
    if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
        echo json_encode([
            'success' => true,
            'file' => [
                'name' => $filename,
                'size' => filesize($destination),
                'url' => 'share.php?file=' . urlencode($filename)
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de l\'upload du fichier']);
    }
    exit;
}

// Méthode non autorisée
http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);