<?php
/**
 * Endpoint AJAX du panneau admin.
 * Toutes les actions serveur passent par ici (POST + CSRF).
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../lang_init.php';
require_once __DIR__ . '/auth_check.php';
$config = require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/Rcon.php';
require_once __DIR__ . '/../lib/SpunkyDb.php';

// ---------- Helper SQLite ----------
function getDb(array $config): SpunkyDb {
    if (empty($config['spunky_sqlite'])) {
        jsonError($GLOBALS['t']['admin_err_sqlite'] ?? 'SQLite non configuré.');
    }
    try {
        return new SpunkyDb($config['spunky_sqlite']);
    } catch (RuntimeException $e) {
        jsonError($GLOBALS['t']['admin_err_sqlite'] ?? $e->getMessage());
    }
}

// ---------- CSRF ----------
$csrfToken = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide.']);
    exit;
}

$action = trim($_POST['action'] ?? '');

// ---------- Helpers ----------
function jsonOk(string $message, array $extra = []): void {
    echo json_encode(array_merge(['success' => true, 'message' => $message], $extra));
    exit;
}
function jsonError(string $message, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function getRcon(array $config): Rcon {
    $rcon = new Rcon($config['server_address'], $config['server_port'], $config['rcon_password']);
    if (!$rcon->connect()) {
        jsonError('Impossible de créer le socket RCON.');
    }
    return $rcon;
}

// ---------- Validation des entrées ----------
function requirePost(string $key, string $pattern = ''): string {
    $val = trim($_POST[$key] ?? '');
    if ($val === '') jsonError("Paramètre manquant : $key.");
    if ($pattern && !preg_match($pattern, $val)) jsonError("Paramètre invalide : $key.");
    return $val;
}

// ============================================================
// ACTIONS
// ============================================================

switch ($action) {

    // ------------------------------------------------------------------
    // STATUS : liste des joueurs en ligne
    // ------------------------------------------------------------------
    case 'status':
        $rcon = getRcon($config);
        $raw  = $rcon->send('status');
        $rcon->disconnect();

        if ($raw === '') jsonError($t['admin_err_rcon']);

        $players = $rcon->parseStatusPlayers($raw);
        jsonOk('ok', ['players' => $players]);
        break;

    // ------------------------------------------------------------------
    // KICK : expulse un joueur par son numéro de slot
    // ------------------------------------------------------------------
    case 'kick':
        $slot = requirePost('slot', '/^\d+$/');
        $rcon = getRcon($config);
        $rcon->send('clientkick ' . (int)$slot);
        $rcon->disconnect();
        jsonOk($t['admin_ok_kick']);
        break;

    // ------------------------------------------------------------------
    // BAN : bannit un joueur (IP ban + kick)
    // ------------------------------------------------------------------
    case 'ban':
        $slot = requirePost('slot', '/^\d+$/');
        $ip   = requirePost('ip',   '/^[\d\.]+$/');

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            jsonError("Adresse IP invalide.");
        }

        // Écriture dans banlist.txt
        $banFile = $config['banlist_file'] ?? '';
        if ($banFile && file_exists($banFile)) {
            $lines = file($banFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $already = false;
            foreach ($lines as $l) { if (strpos($l, $ip . ':') === 0) { $already = true; break; } }
            if (!$already) { file_put_contents($banFile, $ip . ':-1' . PHP_EOL, FILE_APPEND | LOCK_EX); }
        }

        // Kick + rehashbans via RCON
        $rcon = getRcon($config);
        $rcon->send('clientkick ' . (int)$slot);
        $rcon->send('rehashbans');
        $rcon->disconnect();
        jsonOk($t['admin_ok_ban']);
        break;

    // ------------------------------------------------------------------
    // BAN_IP : bannit une IP manuellement (sans slot)
    // ------------------------------------------------------------------
    case 'ban_ip':
        $ip = requirePost('ip', '/^[\d\.]+$/');
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            jsonError("Adresse IP invalide.");
        }
        $banFile = $config['banlist_file'] ?? '';
        if ($banFile && file_exists($banFile)) {
            $lines = file($banFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $already = false;
            foreach ($lines as $l) { if (strpos($l, $ip . ':') === 0) { $already = true; break; } }
            if (!$already) { file_put_contents($banFile, $ip . ':-1' . PHP_EOL, FILE_APPEND | LOCK_EX); }
        }
        // Recharger les bans côté serveur
        $rcon = getRcon($config);
        $rcon->send('rehashbans');
        $rcon->disconnect();
        jsonOk($t['admin_ok_ban_added']);
        break;

    // ------------------------------------------------------------------
    // UNBAN : supprime un ban par IP
    // ------------------------------------------------------------------
    case 'unban':
        $ip = requirePost('ip', '/^[\d\.\/]+$/');
        $ipClean = explode('/', $ip)[0];
        if (!filter_var($ipClean, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            jsonError("Adresse IP invalide.");
        }
        $banFile = $config['banlist_file'] ?? '';
        if ($banFile && file_exists($banFile)) {
            $lines = file($banFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $filtered = array_filter($lines, function($l) use ($ipClean) {
                return strpos($l, $ipClean . ':') !== 0;
            });
            file_put_contents($banFile, implode(PHP_EOL, $filtered) . PHP_EOL, LOCK_EX);
        }
        // Recharger les bans côté serveur
        $rcon = getRcon($config);
        $rcon->send('rehashbans');
        $rcon->disconnect();
        jsonOk($t['admin_ok_unban']);
        break;

    // ------------------------------------------------------------------
    // BANLIST : liste des IPs bannies (lecture directe du fichier)
    // ------------------------------------------------------------------
    case 'banlist':
        $banFile = $config['banlist_file'] ?? '';
        $bans = [];
        if ($banFile && file_exists($banFile)) {
            $lines = file($banFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $i => $line) {
                $parts = explode(':', $line);
                $ip = trim($parts[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $bans[] = ['index' => $i, 'ip' => $ip];
                }
            }
        }
        jsonOk('ok', ['bans' => $bans]);
        break;

    // ------------------------------------------------------------------
    // SAY : envoie un message sur le serveur
    // ------------------------------------------------------------------
    case 'say':
        $message = requirePost('message');
        // Limite longueur et filtre les sauts de ligne
        $message = substr(str_replace(["\n", "\r"], ' ', $message), 0, 150);
        $rcon = getRcon($config);
        $rcon->send('say ' . $message);
        $rcon->disconnect();
        jsonOk($t['admin_ok_say']);
        break;

    // ------------------------------------------------------------------
    // MAP : change la carte
    // ------------------------------------------------------------------
    case 'map':
        $mapName = requirePost('mapname');
        // Validation : uniquement lettres, chiffres, tirets, underscores
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $mapName)) {
            jsonError("Nom de map invalide.");
        }
        // Vérification que la map existe dans la base locale
        if (file_exists($config['mapinfo_file'])) {
            $allMaps = json_decode(file_get_contents($config['mapinfo_file']), true) ?? [];
            if (!array_key_exists($mapName, $allMaps)) {
                jsonError("Map inconnue.");
            }
        }
        $rcon = getRcon($config);
        $rcon->send('map ' . $mapName);
        $rcon->disconnect();
        jsonOk($t['admin_ok_map']);
        break;

    // ------------------------------------------------------------------
    // MAP_RESTART : redémarre la map en cours
    // ------------------------------------------------------------------
    case 'map_restart':
        $rcon = getRcon($config);
        $rcon->send('map_restart');
        $rcon->disconnect();
        jsonOk($t['admin_ok_restart']);
        break;

    // ------------------------------------------------------------------
    // DB_PLAYERS : recherche joueurs dans la BDD Spunkybot
    // ------------------------------------------------------------------
    case 'db_players':
        $query = trim($_POST['query'] ?? '');
        if (strlen($query) < 2) {
            jsonError('Requête trop courte (minimum 2 caractères).');
        }
        $db      = getDb($config);
        $players = $db->searchPlayers($query);
        jsonOk('ok', ['players' => $players]);
        break;

    // ------------------------------------------------------------------
    // DB_BANS : liste des bans actifs dans la BDD Spunkybot
    // ------------------------------------------------------------------
    case 'db_bans':
        $db   = getDb($config);
        $bans = $db->getActiveBans();
        jsonOk('ok', ['bans' => $bans]);
        break;

    // ------------------------------------------------------------------
    // DB_BAN : bannit un joueur dans la BDD + RCON
    // ------------------------------------------------------------------
    case 'db_ban':
        $playerId = (int)requirePost('player_id', '/^\d+$/');
        $guid     = requirePost('guid',    '/^[a-zA-Z0-9]{1,64}$/');
        $name     = requirePost('name');
        $ip       = requirePost('ip',      '/^[\d\.]+$/');
        $reason   = substr(strip_tags(trim($_POST['reason'] ?? 'Ban admin')), 0, 150);
        $durInput = requirePost('duration', '/^(1|7|30|perm)$/');

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            jsonError("Adresse IP invalide.");
        }

        $durMap = ['1' => 86400, '7' => 604800, '30' => 2592000, 'perm' => 0];
        $seconds = $durMap[$durInput] ?? 0;

        $db = getDb($config);

        // Récupère l'ID dans xlrstats pour l'entrée ban_list
        $player = $db->getPlayerById($playerId);
        if (!$player) jsonError("Joueur introuvable.");

        $db->banPlayer($playerId, $guid, $name, $ip, $seconds, $reason);

        // Aussi banni au niveau RCON pour effet immédiat si le joueur est en ligne
        if (!empty($config['rcon_password'])) {
            try {
                $rcon = getRcon($config);
                $rcon->send('addip ' . $ip);
                $rcon->send('writeip');
                $rcon->disconnect();
            } catch (Exception $e) {
                // RCON facultatif pour les bans BDD
            }
        }

        jsonOk($t['admin_ok_db_ban'] ?? 'Joueur banni.');
        break;

    // ------------------------------------------------------------------
    // DB_UNBAN : supprime un ban de la BDD + RCON
    // ------------------------------------------------------------------
    case 'db_unban':
        $banId = (int)requirePost('ban_id', '/^\d+$/');

        $db  = getDb($config);
        $row = $db->unbanById($banId);

        if (empty($row)) {
            jsonError("Ban introuvable.");
        }

        // Retire aussi le ban RCON si l'IP est connue
        if (!empty($config['rcon_password']) && !empty($row['ip'])) {
            try {
                $rcon = getRcon($config);
                $rcon->send('removeip ' . $row['ip']);
                $rcon->send('writeip');
                $rcon->disconnect();
            } catch (Exception $e) {
                // RCON facultatif
            }
        }

        jsonOk($t['admin_ok_db_unban'] ?? 'Ban supprimé.', ['name' => $row['name']]);
        break;

    default:
        jsonError('Action inconnue.', 400);
}
