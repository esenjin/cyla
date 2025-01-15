<?php
// Charger la configuration avant tout
require_once __DIR__ . '/config.php';

// Démarrer la session
session_name(SESSION_NAME);
session_start();

/**
 * Classe principale de Cyla
 */
class Cyla {
    /**
     * Vérifie si un utilisateur est connecté
     * @return bool
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user']) && isset($_SESSION['last_activity']) &&
               (time() - $_SESSION['last_activity']) < SESSION_LIFETIME;
    }

    /**
     * Met à jour le timestamp de dernière activité
     */
    public static function updateActivity() {
        $_SESSION['last_activity'] = time();
    }

    /**
     * Authentifie un utilisateur
     * @param string $username Nom d'utilisateur
     * @param string $password Mot de passe
     * @return bool
     */
    public static function authenticate($username, $password) {
        global $ADMIN_USERS;
        
        if (!isset($ADMIN_USERS[$username])) {
            return false;
        }

        $user = $ADMIN_USERS[$username];
        $hashed = hash(HASH_ALGO, $password . $user['salt']);
        
        if ($hashed === $user['password']) {
            $_SESSION['user'] = $username;
            self::updateActivity();
            return true;
        }
        
        return false;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public static function logout() {
        session_destroy();
    }

    /**
     * Génère un nom de fichier unique
     * @param string $originalName Nom original du fichier
     * @return string
     */
    public static function generateUniqueFilename($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . $extension;
    }

    /**
     * Vérifie si un fichier peut être uploadé
     * @param array $file Informations du fichier ($_FILES)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateUpload($file) {
        $result = ['valid' => true, 'error' => null];
        
        // Vérifier les erreurs d'upload PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['valid'] = false;
            $result['error'] = 'Erreur lors de l\'upload';
            return $result;
        }

        // Vérifier la taille
        if ($file['size'] > MAX_FILE_SIZE) {
            $result['valid'] = false;
            $result['error'] = 'Le fichier dépasse la taille maximale autorisée';
            return $result;
        }

        // Vérifier l'extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isAllowedExtension($extension)) {
            $result['valid'] = false;
            $result['error'] = 'Extension de fichier non autorisée';
            return $result;
        }

        return $result;
    }

    /**
     * Sécurise les sorties HTML
     * @param string $text Texte à sécuriser
     * @return string
     */
    public static function escape($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Génère un token CSRF
     * @return string
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifie un token CSRF
     * @param string $token Token à vérifier
     * @return bool
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Liste les fichiers uploadés avec pagination
     * @param int $page Numéro de la page (commence à 1)
     * @param int $perPage Nombre d'éléments par page
     * @return array ['files' => array, 'total' => int, 'totalPages' => int]
     */
    public static function listFiles($page = 1, $perPage = 20) {
        $files = [];
        $allFiles = [];
        
        // Ajouter les fichiers du dossier principal
        $allFiles = array_merge($allFiles, glob(UPLOAD_DIR . '*'));
        
        // Ajouter les fichiers des dossiers hérités
        foreach (LEGACY_UPLOAD_DIRS as $dir) {
            if (is_dir($dir)) {
                $allFiles = array_merge($allFiles, glob($dir . '*'));
            }
        }
        
        // Trier les fichiers par date de modification (plus récent en premier)
        usort($allFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Calculer la pagination
        $total = count($allFiles);
        $totalPages = ceil($total / $perPage);
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        
        // Récupérer uniquement les fichiers de la page courante
        $pageFiles = array_slice($allFiles, $offset, $perPage);
        
        foreach ($pageFiles as $file) {
            $info = pathinfo($file);
            $relativePath = '';
            
            // Déterminer le chemin relatif selon le dossier
            if (strpos($file, UPLOAD_DIR) === 0) {
                $relativePath = 'fichiers/';
            } elseif (strpos($file, __DIR__ . '/v1/img/fichiers/') === 0) {
                $relativePath = 'v1/img/fichiers/';
            } elseif (strpos($file, __DIR__ . '/v2/file/') === 0) {
                $relativePath = 'v2/file/';
            }
            
            $files[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'extension' => strtolower($info['extension'] ?? ''),
                'uploaded' => filemtime($file),
                'preview_type' => getPreviewType($info['extension'] ?? ''),
                'path' => $relativePath // Ajout du chemin relatif
            ];
        }
        
        return [
            'files' => $files,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'perPage' => $perPage
        ];
    }

    /**
     * Supprime un fichier
     * @param string $filename Nom du fichier à supprimer
     * @param string $path Chemin relatif du fichier
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function deleteFile($filename, $path = 'fichiers/') {
        // Déterminer le chemin complet selon le dossier
        $basePath = '';
        switch ($path) {
            case 'v1/img/fichiers/':
                $basePath = __DIR__ . '/v1/img/fichiers/';
                break;
            case 'v2/file/':
                $basePath = __DIR__ . '/v2/file/';
                break;
            default:
                $basePath = UPLOAD_DIR;
        }
        
        $filepath = $basePath . $filename;
        
        // Vérifier que le fichier existe et est dans le bon dossier
        if (!file_exists($filepath) || !is_file($filepath)) {
            return ['success' => false, 'error' => 'Fichier introuvable'];
        }
        
        // Vérifier que le fichier est bien dans un des dossiers autorisés
        $realpath = realpath($filepath);
        $allowed = false;
        
        if (strpos($realpath, realpath(UPLOAD_DIR)) === 0) {
            $allowed = true;
        } else {
            foreach (LEGACY_UPLOAD_DIRS as $dir) {
                if (strpos($realpath, realpath($dir)) === 0) {
                    $allowed = true;
                    break;
                }
            }
        }
        
        if (!$allowed) {
            return ['success' => false, 'error' => 'Chemin de fichier non autorisé'];
        }
        
        // Supprimer le fichier
        if (unlink($filepath)) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Erreur lors de la suppression du fichier'];
        }
    }
}

// Vérifier et mettre à jour l'activité si l'utilisateur est connecté
if (Cyla::isLoggedIn()) {
    Cyla::updateActivity();
}