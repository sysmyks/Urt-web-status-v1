<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/config/config.php';

// Accepter uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$mapName = isset($_POST['map']) ? trim($_POST['map']) : '';

// Valider que le nom de map n'est pas vide
if (empty($mapName)) {
    echo json_encode(['success' => false, 'message' => 'Nom de map manquant']);
    exit;
}

// Valider que la map existe dans mapinfo.json (sécurité : on n'accepte que des maps connues)
if (!file_exists($config['mapinfo_file'])) {
    echo json_encode(['success' => false, 'message' => 'Fichier mapinfo introuvable']);
    exit;
}
$allMaps = json_decode(file_get_contents($config['mapinfo_file']), true);
if (!isset($allMaps[$mapName])) {
    echo json_encode(['success' => false, 'message' => 'Map inconnue']);
    exit;
}

// Valider le nom de map : uniquement lettres, chiffres, tirets et underscores
if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $mapName)) {
    echo json_encode(['success' => false, 'message' => 'Nom de map invalide']);
    exit;
}

$rconPassword = $config['rcon_password'];
$serverAddress = $config['server_address'];
$serverPort = $config['server_port'];

// Fonction pour envoyer une commande RCON et lire optionnellement la réponse
function sendRcon($socket, $serverAddress, $serverPort, $rconPassword, $command, $readResponse = false) {
    $packet = "\xFF\xFF\xFF\xFFrcon {$rconPassword} {$command}\n";
    $sent = @socket_sendto($socket, $packet, strlen($packet), 0, $serverAddress, $serverPort);
    if ($sent === false) {
        return false;
    }
    if ($readResponse) {
        $response = '';
        $from = '';
        $port = 0;
        @socket_recvfrom($socket, $response, 8192, 0, $from, $port);
        return $response;
    }
    return true;
}

// Créer le socket
$socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    echo json_encode(['success' => false, 'message' => 'Impossible de créer le socket']);
    exit;
}
socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 2, 'usec' => 0]);

// Récupérer le status pour trouver le slot d'un joueur connecté
// "callvote" est une commande client, on doit la "spoof" depuis un joueur réel
$statusResponse = sendRcon($socket, $serverAddress, $serverPort, $rconPassword, 'status', true);

$playerSlot = null;
if ($statusResponse) {
    // Format RCON status: "  0   -1  42 PlayerName  0  127.0.0.1:port  0  16384"
    $lines = explode("\n", $statusResponse);
    foreach ($lines as $line) {
        if (preg_match('/^\s*(\d+)\s+[-\d]+\s+\d+\s+\S/', $line, $matches)) {
            $playerSlot = (int)$matches[1];
            break;
        }
    }
}

if ($playerSlot === null) {
    socket_close($socket);
    echo json_encode(['success' => false, 'message' => 'Aucun joueur connecté pour lancer le vote']);
    exit;
}

// Envoyer "spoof <slot> callvote map <mapname>" via RCON
// La commande spoof exécute callvote comme si elle venait du joueur connecté
$sent = sendRcon($socket, $serverAddress, $serverPort, $rconPassword, "spoof {$playerSlot} callvote map {$mapName}");
socket_close($socket);

if ($sent === false) {
    echo json_encode(['success' => false, 'message' => "Erreur d'envoi de la commande"]);
    exit;
}

echo json_encode(['success' => true, 'message' => "Vote lancé pour {$mapName} !"]);
