<?php
define('CYLA_CORE', true);
require_once 'core.php';

// Augmenter la limite de temps d'exécution pour les uploads multiples
set_time_limit(300); // 5 minutes

// Vérifier si l'utilisateur est connecté
if (!Cyla::isLoggedIn()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier le token CSRF
if (!isset($_POST['csrf_token']) || !Cyla::verifyCSRFToken($_POST['csrf_token'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF invalide']);
    exit;
}

// Gérer l'upload de fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    header('Content-Type: application/json');
    
    try {
        // Vérification de l'espace disque disponible
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
        
        // Upload avec gestion de la mémoire
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
            throw new Exception('Erreur lors du déplacement du fichier');
        }

        // Vérifier l'intégrité du fichier
        if (!file_exists($destination) || filesize($destination) !== $_FILES['file']['size']) {
            unlink($destination); // Nettoyer en cas d'erreur
            throw new Exception('Erreur d\'intégrité du fichier');
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
        echo json_encode([
            'error' => $e->getMessage(),
            'details' => error_get_last()
        ]);
    }
    exit;
}

// Méthode non autorisée
http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);