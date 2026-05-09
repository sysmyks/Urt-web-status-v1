<?php
// Configuration du serveur Urban Terror
return [
    'server_address' => '127.0.0.1', // Adresse IP du serveur
    'server_port' => 27960, // Port du serveur
    'rcon_password' => '', // Mot de passe RCON
    'timeout' => 5, // Délai d'attente pour la connexion
    'cache_duration' => 60, // durée du cache en secondes
    'maps_directory' => '/home/urt/UrbanTerror43/q3ut4', // Dossier contenant les maps
    'local_images_directory' => __DIR__ . '/../images/maps', // Dossier local pour stocker les images
    'mapinfo_file' => '/home/urt/spunkybot-1.13.0/mod/mapinfo.json',
    'records_file' => '/home/urt/spunkybot-1.13.0/mod/jump_records.json',

    // Système de liaison compte web ↔ joueur en jeu
    'web_tokens_file' => '/home/urt/spunkybot-1.13.0/mod/web_tokens.json',
    'web_linked_file'  => '/home/urt/spunkybot-1.13.0/mod/web_linked.json',

    // Base de données Spunkybot (SQLite)
    'spunky_sqlite' => '/home/urt/spunkybot-1.13.0/data.sqlite',

    // Fichier de bans URT
    'banlist_file' => '/home/urt/UrbanTerror43/q3ut4/banlist.txt',

    // -----------------------------------------------------------------------
    // Panneau d'administration
    // Pour générer le hash du mot de passe, visitez /generate_hash.php
    // sur votre serveur (une seule fois) puis supprimez ce fichier.
    // -----------------------------------------------------------------------
    'admin_username'      => 'admin',
    'admin_password_hash' => '', // ← collez ici le hash généré par generate_hash.php
];