<?php
// Prevent direct access to this file
if (!defined('CYLA_CORE')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Accès direct interdit');
}

// Site configuration
define('SITE_NAME', 'Cyla');
define('SITE_VERSION', '3.0.1');
define('SITE_URL', 'https://concepts.esenjin.xyz/cyla/');

// Files configuration
define('LEGACY_UPLOAD_DIRS', [
    __DIR__ . '/v1/img/fichiers/',
    __DIR__ . '/v2/file/'
]);
define('UPLOAD_DIR', __DIR__ . '/fichiers/');
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100 Mo en octets
define('ALLOWED_EXTENSIONS', [
    'png', 'jpg', 'jpeg', 'gif', 'webm', 'mp4', 'wmv',
    'mp3', 'flac', 'ogg', 'zip', 'css', 'pdf',
    'zip', 'rar', 'm3u', 'm3u8', 'txt'
]);

// Preview configuration
define('PREVIEW_IMAGES', ['png', 'jpg', 'jpeg', 'gif']);
define('PREVIEW_VIDEOS', ['webm', 'mp4', 'wmv']);
define('PREVIEW_AUDIOS', ['mp3', 'flac', 'ogg']);
define('PREVIEW_TEXTS', ['txt', 'css', 'm3u', 'm3u8']);

// Security configuration
define('HASH_ALGO', 'sha256'); // Algorithme de hachage pour les mots de passe
define('SALT_LENGTH', 32); // Longueur du sel pour le hachage

// Admin users (format: 'username' => ['password' => 'hashed_password', 'salt' => 'random_salt'])
$ADMIN_USERS = [
    'admin' => [
        'password' => 'a94637ad7685d8a3e64c97eddd7751a0ff55434a607361b7304edf41b39ab7f8', // Default: 'password'
        'salt' => 'defaultsalt123'
    ]
];

// Session configuration
define('SESSION_LIFETIME', 3600); // Durée de vie de la session en secondes (1 heure)
define('SESSION_NAME', 'CYLA_SESSION');

// Error reporting
define('DEBUG_MODE', false); // À mettre à false en production
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Fonction pour vérifier si une extension est autorisée
function isAllowedExtension($extension) {
    return in_array(strtolower($extension), ALLOWED_EXTENSIONS);
}

// Fonction pour vérifier si un fichier peut avoir un aperçu
function canPreview($extension) {
    $extension = strtolower($extension);
    return in_array($extension, PREVIEW_IMAGES) ||
           in_array($extension, PREVIEW_VIDEOS) ||
           in_array($extension, PREVIEW_AUDIOS) ||
           in_array($extension, PREVIEW_TEXTS);
}

// Fonction pour obtenir le type d'aperçu
function getPreviewType($extension) {
    $extension = strtolower($extension);
    if (in_array($extension, PREVIEW_IMAGES)) return 'image';
    if (in_array($extension, PREVIEW_VIDEOS)) return 'video';
    if (in_array($extension, PREVIEW_AUDIOS)) return 'audio';
    if (in_array($extension, PREVIEW_TEXTS)) return 'text';
    return 'none';
}