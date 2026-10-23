<?php
declare(strict_types=1);

/*
 * Prérequis : PHP 8.5 ou supérieur.
 *
 * Cette vérification est faite AVANT tout autre chargement, pour afficher un
 * message clair plutôt qu'une erreur obscure sur un serveur trop ancien.
 * Ce fichier (et config.php) n'utilise volontairement aucune syntaxe propre
 * à PHP 8.5 (comme l'opérateur |>) afin que ce message puisse s'afficher
 * même sous PHP 8.0 → 8.4.
 */
const CYLA_MIN_PHP_VERSION    = '8.5.0';
const CYLA_MIN_PHP_VERSION_ID = 80500;

if (PHP_VERSION_ID < CYLA_MIN_PHP_VERSION_ID) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Cyla nécessite PHP ' . CYLA_MIN_PHP_VERSION . ' ou supérieur (version détectée : ' . PHP_VERSION . ').');
}

// Charger la configuration avant tout
require_once __DIR__ . '/config.php';

// Démarrer la session
session_name(SESSION_NAME);
session_start();

/**
 * Classe principale de Cyla
 */
final class Cyla {
    /**
     * Dossiers de stockage connus : chemin relatif (utilisé dans les URLs) => chemin absolu.
     * Le premier est le dossier principal, les suivants sont les dossiers hérités.
     *
     * @return array<string, string>
     */
    public static function storageDirs(): array {
        return [
            'fichiers/'        => UPLOAD_DIR,
            'v1/img/fichiers/' => __DIR__ . '/v1/img/fichiers/',
            'v2/file/'         => __DIR__ . '/v2/file/',
        ];
    }

    /**
     * Vérifie si un utilisateur est connecté
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user'], $_SESSION['last_activity']) &&
               (time() - (int) $_SESSION['last_activity']) < SESSION_LIFETIME;
    }

    /**
     * Met à jour le timestamp de dernière activité
     */
    public static function updateActivity(): void {
        $_SESSION['last_activity'] = time();
    }

    /**
     * Authentifie un utilisateur
     */
    #[\NoDiscard('le résultat de l\'authentification doit être vérifié')]
    public static function authenticate(string $username, string $password): bool {
        global $ADMIN_USERS;

        if (!isset($ADMIN_USERS[$username])) {
            return false;
        }

        $user   = $ADMIN_USERS[$username];
        $hashed = hash(HASH_ALGO, $password . $user['salt']);

        // Comparaison en temps constant
        if (hash_equals($user['password'], $hashed)) {
            session_regenerate_id(true);
            $_SESSION['user'] = $username;
            self::updateActivity();
            return true;
        }

        return false;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public static function logout(): void {
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Génère un nom de fichier unique
     */
    public static function generateUniqueFilename(string $originalName): string {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return uniqid() . '_' . time() . '.' . $extension;
    }

    /**
     * Vérifie si un fichier peut être uploadé
     * @param array $file Informations du fichier ($_FILES)
     * @return array{valid: bool, error: ?string}
     */
    #[\NoDiscard]
    public static function validateUpload(array $file): array {
        // Vérifier les erreurs d'upload PHP (et rejeter les envois multiples mal formés)
        if (!is_string($file['name'] ?? null) || ($file['error'] ?? null) !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'Erreur lors de l\'upload'];
        }

        // Vérifier la taille
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['valid' => false, 'error' => 'Le fichier dépasse la taille maximale autorisée'];
        }

        // Vérifier l'extension
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!isAllowedExtension($extension)) {
            return ['valid' => false, 'error' => 'Extension de fichier non autorisée'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Sécurise les sorties HTML
     */
    public static function escape(string|int|float|null $text): string {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Lit un paramètre texte de requête ($_GET / $_POST) de façon sûre :
     * renvoie $default si la clé est absente ou n'est pas une chaîne
     * (ex. ?name[]=… envoyé par un client malveillant), ce qui évite
     * les TypeError fatales avec les fonctions typées.
     */
    public static function input(array $source, string $key, string $default = ''): string {
        $value = $source[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /**
     * Génère un token CSRF
     */
    public static function generateCSRFToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifie un token CSRF (accepte n'importe quelle entrée : un tableau
     * passé via ?csrf_token[]= ne provoque plus d'erreur fatale).
     */
    #[\NoDiscard]
    public static function verifyCSRFToken(mixed $token): bool {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Résout un fichier hébergé vers son chemin absolu, en garantissant qu'il
     * se trouve bien dans un des dossiers de stockage autorisés.
     *
     * @param string $filename Nom du fichier (sans dossier)
     * @param string $path     Chemin relatif du dossier (clé de storageDirs())
     * @return string|null Chemin absolu réel, ou null si introuvable / non autorisé
     */
    public static function resolveFilePath(string $filename, string $path = 'fichiers/'): ?string {
        // Un nom de fichier ne doit contenir aucun séparateur de dossier.
        if ($filename === '' || $filename !== basename($filename) || str_contains($filename, "\0")) {
            return null;
        }

        $dirs    = self::storageDirs();
        $baseDir = $dirs[$path] ?? UPLOAD_DIR;
        $realDir = realpath($baseDir);
        if ($realDir === false) {
            return null;
        }

        $realFile = realpath($realDir . DIRECTORY_SEPARATOR . $filename);
        if ($realFile === false || !is_file($realFile)) {
            return null;
        }

        // Le fichier doit être STRICTEMENT dans le dossier (le séparateur final
        // évite qu'un dossier « fichiers-bis » soit accepté pour « fichiers »).
        return str_starts_with($realFile, $realDir . DIRECTORY_SEPARATOR) ? $realFile : null;
    }

    /**
     * Normalise un chemin relatif reçu de l'extérieur vers une clé connue.
     */
    public static function normalizeStoragePath(mixed $path): string {
        return is_string($path) && array_key_exists($path, self::storageDirs())
            ? $path
            : array_key_first(self::storageDirs());
    }

    /**
     * Liste les fichiers uploadés avec pagination
     * @param int $page Numéro de la page (commence à 1)
     * @param int $perPage Nombre d'éléments par page
     * @return array{files: array, total: int, totalSize: int, totalPages: int, currentPage: int, perPage: int}
     */
    public static function listFiles(int $page = 1, int $perPage = 20): array {
        $perPage  = max(1, $perPage);
        $allFiles = [];

        // Fichiers du dossier principal puis des dossiers hérités
        foreach (self::storageDirs() as $relativePath => $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '*') ?: [] as $file) {
                if (is_file($file)) {
                    $allFiles[] = ['file' => $file, 'path' => $relativePath, 'mtime' => (int) filemtime($file)];
                }
            }
        }

        // Trier les fichiers par date de modification (plus récent en premier)
        usort($allFiles, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        // Calculer la pagination
        $total      = count($allFiles);
        $totalSize  = array_sum(array_map(static fn(array $f): int => (int) filesize($f['file']), $allFiles));
        $totalPages = (int) ceil($total / $perPage);
        $page       = max(1, min($page, $totalPages));
        $offset     = ($page - 1) * $perPage;

        // Récupérer uniquement les fichiers de la page courante
        $files = [];
        foreach (array_slice($allFiles, $offset, $perPage) as $entry) {
            $extension = pathinfo($entry['file'], PATHINFO_EXTENSION);

            $files[] = [
                'name'         => basename($entry['file']),
                'size'         => (int) filesize($entry['file']),
                'extension'    => strtolower($extension),
                'uploaded'     => $entry['mtime'],
                'preview_type' => getPreviewType($extension),
                'path'         => $entry['path'],
            ];
        }

        return [
            'files'       => $files,
            'total'       => $total,
            'totalSize'   => $totalSize,
            'totalPages'  => $totalPages,
            'currentPage' => $page,
            'perPage'     => $perPage,
        ];
    }

    /**
     * Supprime un fichier
     * @return array{success: bool, error?: string}
     */
    #[\NoDiscard]
    public static function deleteFile(string $filename, string $path = 'fichiers/'): array {
        $filepath = self::resolveFilePath($filename, self::normalizeStoragePath($path));

        if ($filepath === null) {
            return ['success' => false, 'error' => 'Fichier introuvable'];
        }

        if (unlink($filepath)) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Erreur lors de la suppression du fichier'];
    }

    // -------------------------
    // Gestion des groupes
    // -------------------------

    private static function groupsFile(): string {
        return __DIR__ . '/groups.json';
    }

    /**
     * Charge tous les groupes depuis le fichier JSON
     * @return array<string, array{files: list<string>, color: string}>
     */
    public static function listGroups(): array {
        $file = self::groupsFile();
        if (!is_file($file)) {
            return [];
        }
        $raw  = file_get_contents($file);
        $data = $raw === false ? null : json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Sauvegarde les groupes dans le fichier JSON
     */
    private static function saveGroups(array $groups): void {
        $json = json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents(self::groupsFile(), $json, LOCK_EX);
    }

    /**
     * Crée un nouveau groupe
     */
    public static function createGroup(string $name, string $color = '#d4846a'): array {
        $name = trim($name);
        if ($name === '') {
            return ['success' => false, 'error' => 'Nom de groupe vide'];
        }
        // N'accepter qu'une couleur hexadécimale (la valeur est injectée dans des attributs style)
        if (!preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $color)) {
            $color = '#d4846a';
        }
        $groups = self::listGroups();
        if (isset($groups[$name])) {
            return ['success' => false, 'error' => 'Ce groupe existe déjà'];
        }
        $groups[$name] = ['files' => [], 'color' => $color];
        self::saveGroups($groups);
        return ['success' => true];
    }

    /**
     * Supprime un groupe
     */
    public static function deleteGroup(string $name): array {
        $groups = self::listGroups();
        if (!isset($groups[$name])) {
            return ['success' => false, 'error' => 'Groupe introuvable'];
        }
        unset($groups[$name]);
        self::saveGroups($groups);
        return ['success' => true];
    }

    /**
     * Assigne ou retire un fichier d'un groupe
     * @param list<mixed> $groupNames Noms des groupes (les valeurs non textuelles sont ignorées)
     */
    public static function setFileGroups(string $filename, array $groupNames): array {
        $groups = self::listGroups();
        // Retirer le fichier de tous les groupes d'abord
        foreach ($groups as &$group) {
            $group['files'] = array_values(array_filter($group['files'], static fn($f) => $f !== $filename));
        }
        unset($group);
        // Ajouter dans les groupes sélectionnés
        foreach ($groupNames as $gname) {
            if (is_string($gname) && isset($groups[$gname]) && !in_array($filename, $groups[$gname]['files'], true)) {
                $groups[$gname]['files'][] = $filename;
            }
        }
        self::saveGroups($groups);
        return ['success' => true];
    }

    /**
     * Retourne les groupes d'un fichier donné
     * @return list<array{name: string, color: string}>
     */
    public static function getFileGroups(string $filename): array {
        $result = [];
        foreach (self::listGroups() as $gname => $group) {
            if (in_array($filename, $group['files'], true)) {
                $result[] = ['name' => (string) $gname, 'color' => $group['color']];
            }
        }
        return $result;
    }
}

// Vérifier et mettre à jour l'activité si l'utilisateur est connecté
if (Cyla::isLoggedIn()) {
    Cyla::updateActivity();
}
