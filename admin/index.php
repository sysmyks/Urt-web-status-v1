<?php
require_once __DIR__ . '/../lang_init.php';
require_once __DIR__ . '/auth_check.php';
$config = require_once __DIR__ . '/../config/config.php';

// Liste des maps pour le sélecteur de changement de map
$mapList = [];
if (file_exists($config['mapinfo_file'])) {
    $mapList = array_keys(json_decode(file_get_contents($config['mapinfo_file']), true) ?? []);
    sort($mapList);
}

$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['admin_dashboard']; ?> — LaFumisterie</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-page">

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <div class="nav-container">
        <a href="/index.php" class="logo">LaFumisterie</a>
        <div class="nav-links">
            <span class="admin-badge"><?php echo $t['admin_dashboard']; ?></span>
            <a href="/status-v1/index.php" class="nav-button"><?php echo $t['nav_status']; ?></a>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <div class="lang-switcher">
                <a href="<?php echo htmlspecialchars(langSwitchUrl('fr')); ?>" class="lang-btn <?php echo $lang==='fr'?'active':''; ?>">FR</a>
                <a href="<?php echo htmlspecialchars(langSwitchUrl('en')); ?>" class="lang-btn <?php echo $lang==='en'?'active':''; ?>">EN</a>
            </div>
            <a href="logout.php" class="btn-logout"><?php echo $t['admin_logout']; ?></a>
        </div>
    </div>
</nav>

<!-- ===== TOAST ===== -->
<div id="toast" class="toast hidden"></div>

<!-- ===== TABS ===== -->
<div class="admin-container">
    <div class="tabs-bar">
        <button class="tab-btn active" data-tab="players"><?php echo $t['admin_tab_players']; ?></button>
        <button class="tab-btn" data-tab="bans"><?php echo $t['admin_tab_bans']; ?></button>
        <button class="tab-btn" data-tab="db-players"><?php echo $t['admin_tab_db_players']; ?></button>
        <button class="tab-btn" data-tab="controls"><?php echo $t['admin_tab_controls']; ?></button>
    </div>

    <!-- ============================= TAB : JOUEURS ============================= -->
    <div id="tab-players" class="tab-content active">
        <div class="tab-header">
            <h2><?php echo $t['admin_players_title']; ?></h2>
            <div class="tab-header-right">
                <span class="auto-refresh-label">
                    <?php echo $t['admin_auto_refresh']; ?> <span id="countdown">15</span>s
                </span>
                <button class="btn-secondary" onclick="loadPlayers()">↺ <?php echo $t['admin_refresh']; ?></button>
            </div>
        </div>
        <div id="players-content">
            <div class="loading">…</div>
        </div>
    </div>

    <!-- ============================= TAB : BANS ============================= -->
    <div id="tab-bans" class="tab-content">
        <div class="tab-header">
            <h2><?php echo $t['admin_bans_title']; ?></h2>
            <button class="btn-secondary" onclick="loadBans()">↺ <?php echo $t['admin_refresh']; ?></button>
        </div>

        <!-- Ajout manuel de ban -->
        <div class="control-card">
            <h3><?php echo $t['admin_manual_ban']; ?></h3>
            <div class="inline-form">
                <input type="text" id="manual-ban-ip" placeholder="<?php echo $t['admin_ban_ip_ph']; ?>" maxlength="15">
                <button class="btn-danger" onclick="addManualBan()">+ <?php echo $t['admin_add_ban']; ?></button>
            </div>
        </div>

        <div id="bans-content">
            <div class="loading">…</div>
        </div>

        <!-- ---- Bans Spunkybot (BDD) ---- -->
        <div class="tab-header" style="margin-top:2rem;">
            <h2><?php echo $t['admin_db_bans_title']; ?></h2>
            <button class="btn-secondary" onclick="loadDbBans()">↺ <?php echo $t['admin_refresh']; ?></button>
        </div>
        <div id="db-bans-content">
            <div class="loading">…</div>
        </div>
    </div>

    <!-- ============================= TAB : JOUEURS BDD ============================= -->
    <div id="tab-db-players" class="tab-content">
        <div class="tab-header">
            <h2><?php echo $t['admin_tab_db_players']; ?></h2>
        </div>

        <!-- Formulaire de recherche -->
        <div class="control-card" style="margin-bottom:1.2rem;">
            <div class="inline-form">
                <input type="text" id="db-search-input"
                       placeholder="<?php echo $t['admin_db_search_ph']; ?>"
                       maxlength="80"
                       onkeydown="if(event.key==='Enter') searchDbPlayers()">
                <button class="btn-primary" onclick="searchDbPlayers()">
                    🔍 <?php echo $t['admin_db_search_btn']; ?>
                </button>
            </div>
        </div>

        <div id="db-players-content">
            <p class="empty-msg"><?php echo $t['admin_db_hint']; ?></p>
        </div>
    </div>
    <div id="tab-controls" class="tab-content">
        <div class="controls-grid">

            <!-- Say -->
            <div class="control-card">
                <h3>💬 <?php echo $t['admin_ctrl_say']; ?></h3>
                <div class="form-group">
                    <input type="text" id="say-msg" placeholder="<?php echo $t['admin_ctrl_say_ph']; ?>" maxlength="150">
                </div>
                <button class="btn-primary" onclick="sendSay()">
                    <?php echo $t['admin_ctrl_say_btn']; ?>
                </button>
            </div>

            <!-- Map change -->
            <div class="control-card">
                <h3>🗺️ <?php echo $t['admin_ctrl_map']; ?></h3>
                <div class="form-group">
                    <select id="map-select">
                        <?php foreach ($mapList as $m): ?>
                        <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn-primary" onclick="changeMap()">
                    <?php echo $t['admin_ctrl_map_btn']; ?>
                </button>
            </div>

            <!-- Map restart -->
            <div class="control-card">
                <h3>🔄 <?php echo $t['admin_ctrl_restart']; ?></h3>
                <p class="control-desc">Redémarre la map sans changer les scores.</p>
                <button class="btn-warning" onclick="mapRestart()">
                    <?php echo $t['admin_ctrl_restart_btn']; ?>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ===== JS ===== -->
<script>
const CSRF   = <?php echo json_encode($csrf); ?>;
const LANG   = <?php echo json_encode([
    'no_players'       => $t['admin_no_players'],
    'no_bans'          => $t['admin_no_bans'],
    'ok_kick'          => $t['admin_ok_kick'],
    'ok_ban'           => $t['admin_ok_ban'],
    'ok_unban'         => $t['admin_ok_unban'],
    'ok_say'           => $t['admin_ok_say'],
    'ok_map'           => $t['admin_ok_map'],
    'ok_restart'       => $t['admin_ok_restart'],
    'ok_ban_added'     => $t['admin_ok_ban_added'],
    'err_rcon'         => $t['admin_err_rcon'],
    'err_generic'      => $t['admin_err_generic'],
    'col_slot'         => $t['admin_col_slot'],
    'col_name'         => $t['admin_col_name'],
    'col_score'        => $t['admin_col_score'],
    'col_ping'         => $t['admin_col_ping'],
    'col_ip'           => $t['admin_col_ip'],
    'col_actions'      => $t['admin_col_actions'],
    'col_index'        => $t['admin_col_index'],
    'kick'             => $t['admin_kick'],
    'ban'              => $t['admin_ban'],
    'unban'            => $t['admin_unban'],
    'confirm_kick'     => $t['admin_confirm_kick'],
    'confirm_ban'      => $t['admin_confirm_ban'],
    'confirm_unban'    => $t['admin_confirm_unban'],
    'confirm_restart'  => $t['admin_ctrl_confirm_restart'],
    'confirm_map'      => $t['admin_ctrl_confirm_map'],
    'ban_ip_ph'        => $t['admin_ban_ip_ph'],
    // BDD
    'db_no_results'    => $t['admin_db_no_results'],
    'db_no_bans'       => $t['admin_db_no_bans'],
    'db_col_id'        => $t['admin_db_col_id'],
    'db_col_first'     => $t['admin_db_col_first'],
    'db_col_last'      => $t['admin_db_col_last'],
    'db_col_plays'     => $t['admin_db_col_plays'],
    'db_col_role'      => $t['admin_db_col_role'],
    'db_col_expires'   => $t['admin_db_col_expires'],
    'db_col_date'      => $t['admin_db_col_date'],
    'db_col_reason'    => $t['admin_db_col_reason'],
    'db_perm_label'    => $t['admin_db_perm_label'],
    'db_ban_1d'        => $t['admin_db_ban_1d'],
    'db_ban_7d'        => $t['admin_db_ban_7d'],
    'db_ban_30d'       => $t['admin_db_ban_30d'],
    'db_ban_perm'      => $t['admin_db_ban_perm'],
    'db_ban_reason_ph' => $t['admin_db_ban_reason_ph'],
    'db_ban_confirm'   => $t['admin_db_ban_confirm'],
    'db_ban_cancel'    => $t['admin_db_ban_cancel'],
    'ok_db_ban'        => $t['admin_ok_db_ban'],
    'ok_db_unban'      => $t['admin_ok_db_unban'],
    'err_sqlite'       => $t['admin_err_sqlite'],
    'col_name_label'   => $t['admin_col_name'],
]); ?>;

// ====================================================================
// Requête AJAX générique
// ====================================================================
async function rconAction(params) {
    const form = new FormData();
    form.append('csrf_token', CSRF);
    for (const [k, v] of Object.entries(params)) form.append(k, v);
    const res  = await fetch('action.php', { method: 'POST', body: form,
        headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (res.status === 401) { location.href = 'login.php'; return null; }
    return res.json();
}

// ====================================================================
// Toast notification
// ====================================================================
function showToast(msg, type = 'success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast ' + type;
    el.classList.remove('hidden');
    clearTimeout(el._timer);
    el._timer = setTimeout(() => el.classList.add('hidden'), 3500);
}

// ====================================================================
// TABS
// ====================================================================
// Gestion onglet DB : charger les bans BDD lorsqu'on l'ouvre
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const id = 'tab-' + btn.dataset.tab;
        document.getElementById(id).classList.add('active');
        if (btn.dataset.tab === 'bans') { loadBans(); loadDbBans(); }
    });
});

// ====================================================================
// TAB PLAYERS — chargement & auto-refresh
// ====================================================================
let countdownVal = 15;
let countdownTimer;

function startCountdown() {
    clearInterval(countdownTimer);
    countdownVal = 15;
    document.getElementById('countdown').textContent = countdownVal;
    countdownTimer = setInterval(() => {
        countdownVal--;
        document.getElementById('countdown').textContent = countdownVal;
        if (countdownVal <= 0) loadPlayers();
    }, 1000);
}

async function loadPlayers() {
    document.getElementById('players-content').innerHTML = '<div class="loading">…</div>';
    const data = await rconAction({ action: 'status' });
    startCountdown();
    if (!data) return;
    if (!data.success) { showToast(LANG.err_generic + data.message, 'error'); return; }

    const players = data.players;
    if (!players.length) {
        document.getElementById('players-content').innerHTML =
            '<p class="empty-msg">' + LANG.no_players + '</p>';
        return;
    }

    let html = `<div class="table-wrap"><table class="admin-table">
        <thead><tr>
            <th>${LANG.col_slot}</th><th>${LANG.col_name}</th>
            <th>${LANG.col_score}</th><th>${LANG.col_ping}</th>
            <th>${LANG.col_ip}</th><th>${LANG.col_actions}</th>
        </tr></thead><tbody>`;

    players.forEach(p => {
        const name = escHtml(p.name);
        html += `<tr>
            <td>${p.slot}</td>
            <td class="player-name-cell">${name}</td>
            <td>${p.score}</td>
            <td><span class="ping ${pingClass(p.ping)}">${p.ping} ms</span></td>
            <td class="ip-cell">${escHtml(p.ip)}</td>
            <td class="actions-cell">
                <button class="btn-sm btn-warning"
                    onclick="kickPlayer(${p.slot},'${name}')">
                    ${LANG.kick}
                </button>
                <button class="btn-sm btn-danger"
                    onclick="banPlayer(${p.slot},'${name}','${escHtml(p.ip)}')">
                    ${LANG.ban}
                </button>
            </td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    document.getElementById('players-content').innerHTML = html;
}

function pingClass(ping) {
    if (ping < 60)  return 'ping-good';
    if (ping < 150) return 'ping-ok';
    return 'ping-bad';
}

async function kickPlayer(slot, name) {
    if (!confirm(LANG.confirm_kick.replace('%s', name))) return;
    const data = await rconAction({ action: 'kick', slot });
    if (!data) return;
    showToast(data.success ? LANG.ok_kick : LANG.err_generic + data.message,
              data.success ? 'success' : 'error');
    if (data.success) loadPlayers();
}

async function banPlayer(slot, name, ip) {
    if (!confirm(LANG.confirm_ban.replace('%s', name).replace('%s', ip))) return;
    const data = await rconAction({ action: 'ban', slot, ip });
    if (!data) return;
    showToast(data.success ? LANG.ok_ban : LANG.err_generic + data.message,
              data.success ? 'success' : 'error');
    if (data.success) loadPlayers();
}

// ====================================================================
// TAB BANS — chargement
// ====================================================================
async function loadBans() {
    document.getElementById('bans-content').innerHTML = '<div class="loading">…</div>';
    const data = await rconAction({ action: 'banlist' });
    if (!data) return;
    if (!data.success) { showToast(LANG.err_generic + data.message, 'error'); return; }

    const bans = data.bans;
    if (!bans.length) {
        document.getElementById('bans-content').innerHTML =
            '<p class="empty-msg">' + LANG.no_bans + '</p>';
        return;
    }

    let html = `<div class="table-wrap"><table class="admin-table">
        <thead><tr>
            <th>${LANG.col_index}</th><th>IP</th><th>${LANG.col_actions}</th>
        </tr></thead><tbody>`;

    bans.forEach(b => {
        html += `<tr>
            <td>${b.index}</td>
            <td>${escHtml(b.ip)}</td>
            <td><button class="btn-sm btn-primary"
                    onclick="unbanIp('${escHtml(b.ip)}')">
                ${LANG.unban}
            </button></td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    document.getElementById('bans-content').innerHTML = html;
}

async function unbanIp(ip) {
    if (!confirm(LANG.confirm_unban.replace('%s', ip))) return;
    const data = await rconAction({ action: 'unban', ip });
    if (!data) return;
    showToast(data.success ? LANG.ok_unban : LANG.err_generic + data.message,
              data.success ? 'success' : 'error');
    if (data.success) loadBans();
}

async function addManualBan() {
    const ip = document.getElementById('manual-ban-ip').value.trim();
    if (!ip) return;
    const data = await rconAction({ action: 'ban_ip', ip });
    if (!data) return;
    showToast(data.success ? LANG.ok_ban_added : LANG.err_generic + data.message,
              data.success ? 'success' : 'error');
    if (data.success) {
        document.getElementById('manual-ban-ip').value = '';
        loadBans();
    }
}

// ====================================================================
// TAB CONTROLS
// ====================================================================
async function sendSay() {
    const msg = document.getElementById('say-msg').value.trim();
    if (!msg) return;
    const data = await rconAction({ action: 'say', message: msg });
    if (!data) return;
    showToast(data.success ? LANG.ok_say : LANG.err_generic + data.message,
              data.success ? 'success' : 'error');
    if (data.success) document.getElementById('say-msg').value = '';
}

async function changeMap() {
    const mapname = document.getElementById('map-select').value;
    if (!mapname) return;
    if (!confirm(LANG.confirm_map.replace('%s', mapname))) return;
    const data = await rconAction({ action: 'map', mapname });
    if (!data) return;
    showToast(data.success ? LANG.ok_map : LANG.err_generic + data.message,
              data.success ? 'success' : 'error');
}

async function mapRestart() {
    if (!confirm(LANG.confirm_restart)) return;
    const data = await rconAction({ action: 'map_restart' });
    if (!data) return;
    showToast(data.success ? LANG.ok_restart : LANG.err_generic + data.message,
              data.success ? 'success' : 'error');
}

// ====================================================================
// TAB DB — Bans Spunkybot (BDD)
// ====================================================================
async function loadDbBans() {
    const cont = document.getElementById('db-bans-content');
    cont.innerHTML = '<div class="loading">…</div>';
    const data = await rconAction({ action: 'db_bans' });
    if (!data) return;
    if (!data.success) {
        cont.innerHTML = '<p class="empty-msg">' + escHtml(data.message) + '</p>';
        return;
    }
    const bans = data.bans;
    if (!bans.length) {
        cont.innerHTML = '<p class="empty-msg">' + LANG.db_no_bans + '</p>';
        return;
    }
    let html = `<div class="table-wrap"><table class="admin-table">
        <thead><tr>
            <th>${LANG.col_name_label}</th>
            <th>IP</th>
            <th>${LANG.db_col_reason}</th>
            <th>${LANG.db_col_expires}</th>
            <th>${LANG.db_col_date}</th>
            <th>${LANG.col_actions}</th>
        </tr></thead><tbody>`;
    bans.forEach(b => {
        const isPerm = b.expires && parseInt(b.expires) > 9000000000;
        const expiresLabel = isPerm ? LANG.db_perm_label : escHtml(b.expires ?? '?');
        html += `<tr>
            <td>${escHtml(b.name)}</td>
            <td>${escHtml(b.ip_address)}</td>
            <td>${escHtml(b.reason ?? '')}</td>
            <td>${expiresLabel}</td>
            <td>${escHtml(b.timestamp ?? '')}</td>
            <td><button class="btn-sm btn-primary"
                    onclick="dbUnbanPlayer(${b.id}, '${escHtml(b.name)}')">
                ${LANG.unban}
            </button></td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    cont.innerHTML = html;
}

async function dbUnbanPlayer(banId, name) {
    if (!confirm(LANG.confirm_unban.replace('%s', name))) return;
    const data = await rconAction({ action: 'db_unban', ban_id: banId });
    if (!data) return;
    showToast(data.success ? LANG.ok_db_unban : LANG.err_generic + data.message,
              data.success ? 'success' : 'error');
    if (data.success) loadDbBans();
}

// ====================================================================
// TAB DB — Recherche joueurs
// ====================================================================
async function searchDbPlayers() {
    const query = document.getElementById('db-search-input').value.trim();
    const cont  = document.getElementById('db-players-content');
    if (query.length < 2) {
        cont.innerHTML = '<p class="empty-msg">' + LANG.db_no_results + '</p>';
        return;
    }
    cont.innerHTML = '<div class="loading">…</div>';
    const data = await rconAction({ action: 'db_players', query });
    if (!data) return;
    if (!data.success) {
        cont.innerHTML = '<p class="empty-msg">' + escHtml(data.message) + '</p>';
        return;
    }
    const players = data.players;
    if (!players.length) {
        cont.innerHTML = '<p class="empty-msg">' + LANG.db_no_results + '</p>';
        return;
    }
    let html = `<div class="table-wrap"><table class="admin-table">
        <thead><tr>
            <th>${LANG.db_col_id}</th>
            <th>${LANG.col_name_label}</th>
            <th>IP</th>
            <th>${LANG.db_col_first}</th>
            <th>${LANG.db_col_last}</th>
            <th>${LANG.db_col_plays}</th>
            <th>${LANG.db_col_role}</th>
            <th>${LANG.col_actions}</th>
        </tr></thead><tbody>`;

    players.forEach(p => {
        const isBanned = parseInt(p.is_banned) === 1;
        const banBadge = isBanned
            ? '<span class="ping ping-bad">Ban</span>'
            : '';
        html += `<tr id="dbrow-${p.id}">
            <td>${p.id}</td>
            <td>${escHtml(p.name)} ${banBadge}</td>
            <td>${escHtml(p.ip_address)}</td>
            <td>${escHtml(p.first_seen ?? '')}</td>
            <td>${escHtml(p.last_played ?? '')}</td>
            <td>${p.num_played}</td>
            <td>${p.admin_role}</td>
            <td class="actions-cell">
                ${isBanned
                    ? `<button class="btn-sm btn-primary"
                            onclick="dbUnbanPlayer(${p.ban_id}, '${escHtml(p.name)}')">
                            ${LANG.unban}</button>`
                    : `<button class="btn-sm btn-danger"
                            onclick="toggleBanForm(${p.id}, '${escHtml(p.guid)}',
                                '${escHtml(p.name)}', '${escHtml(p.ip_address)}')">
                            ${LANG.ban}</button>`
                }
            </td>
        </tr>
        <tr id="dbbanform-${p.id}" class="ban-form-row" style="display:none;">
            <td colspan="8">
                <div class="inline-form" style="flex-wrap:wrap;gap:8px;padding:8px 0;">
                    <input type="text" id="dbreason-${p.id}"
                           placeholder="${escHtml(LANG.db_ban_reason_ph)}"
                           maxlength="150" style="flex:1;min-width:180px;">
                    <select id="dbduration-${p.id}">
                        <option value="1">${LANG.db_ban_1d}</option>
                        <option value="7">${LANG.db_ban_7d}</option>
                        <option value="30">${LANG.db_ban_30d}</option>
                        <option value="perm">${LANG.db_ban_perm}</option>
                    </select>
                    <button class="btn-sm btn-danger"
                        onclick="commitDbBan(${p.id}, '${escHtml(p.guid)}',
                            '${escHtml(p.name)}', '${escHtml(p.ip_address)}')">
                        ${LANG.db_ban_confirm}
                    </button>
                    <button class="btn-sm btn-secondary"
                        onclick="toggleBanForm(${p.id})">
                        ${LANG.db_ban_cancel}
                    </button>
                </div>
            </td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    cont.innerHTML = html;
}

function toggleBanForm(id) {
    const row = document.getElementById('dbbanform-' + id);
    if (!row) return;
    row.style.display = (row.style.display === 'none' || !row.style.display) ? '' : 'none';
}

async function commitDbBan(playerId, guid, name, ip) {
    const reason   = document.getElementById('dbreason-'  + playerId).value.trim() || 'Ban admin';
    const duration = document.getElementById('dbduration-' + playerId).value;
    const data = await rconAction({
        action: 'db_ban', player_id: playerId, guid, name, ip, reason, duration
    });
    if (!data) return;
    showToast(data.success ? LANG.ok_db_ban : LANG.err_generic + data.message,
              data.success ? 'success' : 'error');
    if (data.success) searchDbPlayers(); // rafraîchit les résultats
}

// ====================================================================
// Utilitaires
// ====================================================================
function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// Chargement initial
loadPlayers();
</script>

</body>
</html>
