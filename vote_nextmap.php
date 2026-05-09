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

// --- Récupérer l'IP du visiteur ---
$visitorIp = '';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
    $visitorIp = filter_var($ips[0], FILTER_VALIDATE_IP) ? $ips[0] : '';
}
if (empty($visitorIp)) {
    $visitorIp = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : '';
}

// --- Vérifier si l'IP est liée à un GUID ---
$linkedGuid = null;
$linkedName = null;
if ($visitorIp && file_exists($config['web_linked_file'])) {
    $linked = json_decode(file_get_contents($config['web_linked_file']), true) ?? [];
    if (isset($linked[$visitorIp])) {
        $linkedGuid = $linked[$visitorIp]['guid'];
        $linkedName = $linked[$visitorIp]['name'];
    }
}

// --- Si pas lié : générer un token et demander la liaison ---
if ($linkedGuid === null) {
    $token = null;
    $tokens = [];
    if (file_exists($config['web_tokens_file'])) {
        $tokens = json_decode(file_get_contents($config['web_tokens_file']), true) ?? [];
    }
    $now = time();
    foreach ($tokens as $k => $v) {
        if ($v['expires'] < $now) {
            unset($tokens[$k]);
        }
    }
    foreach ($tokens as $k => $v) {
        if ($v['ip'] === $visitorIp) {
            $token = $k;
            break;
        }
    }
    if ($token === null) {
        do {
            $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (isset($tokens[$token]));
        $tokens[$token] = [
            'ip'      => $visitorIp,
            'expires' => $now + 300,
        ];
        file_put_contents($config['web_tokens_file'], json_encode($tokens), LOCK_EX);
    }

    echo json_encode([
        'success'    => false,
        'need_link'  => true,
        'token'      => $token,
        'command'    => "!wt {$token}",
        'message'    => "Compte non lié. Tape ^9!wt {$token}^7 en jeu pour valider.",
    ]);
    exit;
}

// --- Compte lié : trouver le slot du joueur via rcon status ---
$rconPassword  = $config['rcon_password'];
$serverAddress = $config['server_address'];
$serverPort    = $config['server_port'];

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

$socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    echo json_encode(['success' => false, 'message' => 'Impossible de créer le socket']);
    exit;
}
socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 2, 'usec' => 0]);

$statusResponse = sendRcon($socket, $serverAddress, $serverPort, $rconPassword, 'status', true);

$playerSlot   = null;
$fallbackSlot = null;
if ($statusResponse) {
    $lines = explode("\n", $statusResponse);
    foreach ($lines as $line) {
        if (preg_match('/^\s*(\d+)\s+[-\d]+\s+\d+\s+\S.+?\s+(\d+\.\d+\.\d+\.\d+)(?::\d+)?/', $line, $m)) {
            $slot     = (int)$m[1];
            $playerIp = $m[2];
            if ($fallbackSlot === null) {
                $fallbackSlot = $slot;
            }
            if ($playerIp === $visitorIp) {
                $playerSlot = $slot;
                break;
            }
        }
    }
    if ($playerSlot === null) {
        $playerSlot = $fallbackSlot;
    }
}

if ($playerSlot === null) {
    socket_close($socket);
    echo json_encode(['success' => false, 'message' => 'Aucun joueur connecté pour lancer le vote']);
    exit;
}

// Commande nextmap au lieu de map
$sent = sendRcon($socket, $serverAddress, $serverPort, $rconPassword, "spoof {$playerSlot} callvote nextmap {$mapName}");
socket_close($socket);

if ($sent === false) {
    echo json_encode(['success' => false, 'message' => "Erreur d'envoi de la commande"]);
    exit;
}

echo json_encode(['success' => true, 'message' => "Vote nextmap lancé pour {$mapName} !"]);
