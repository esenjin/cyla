<?php
/**
 * Configuration Vestikan pour Cyla.
 *
 * ⚠️  NE JAMAIS COMMITTER CE FICHIER : il contient le client_secret.
 *     Ajoutez-le à votre .gitignore (déjà fait dans le .gitignore fourni).
 *
 * Les valeurs client_id / client_secret sont affichées UNE SEULE FOIS lors de
 * l'enregistrement du site dans l'admin Vestikan :
 *     https://concepts.esenjin.xyz/vestikan/admin  → « Sites satellites »
 *
 * Le redirect_uri doit être IDENTIQUE (caractère pour caractère) à celui
 * enregistré dans l'admin Vestikan, en HTTPS, sans slash final superflu.
 */

return [
    'base_url'      => 'https://concepts.esenjin.xyz/vestikan',
    'client_id'     => 'vk_client_xxxxxxxxxxxxxxxx',
    'client_secret' => 'xxxxxxxx...(64 caractères hex)...',
    'redirect_uri'  => 'https://concepts.esenjin.xyz/cyla/vestikan-callback.php',

    // Facultatif : clé de session pour le state anti-CSRF. La valeur par défaut
    // du SDK ('vestikan_state') n'entre pas en collision avec les clés de Cyla,
    // donc inutile de la changer.
    // 'session_key' => 'vestikan_state',
];
