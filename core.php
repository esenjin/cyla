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
     * Liste les fichiers uploadés
     * @return array
     */
    public static function listFiles() {
        $files = [];
        foreach (glob(UPLOAD_DIR . '*') as $file) {
            $info = pathinfo($file);
            $files[] = [
                'name' => basename($file),
                'size' => filesize($file),
                'extension' => strtolower($info['extension']),
                'uploaded' => filemtime($file),
                'preview_type' => getPreviewType($info['extension'])
            ];
        }
        return $files;
    }
}

// Vérifier et mettre à jour l'activité si l'utilisateur est connecté
if (Cyla::isLoggedIn()) {
    Cyla::updateActivity();
}