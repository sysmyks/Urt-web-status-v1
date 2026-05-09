<?php
require_once __DIR__ . '/lang_init.php';
$config = require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/MapDataManager.php';
require_once __DIR__ . '/lib/MapImageManager.php';

// Récupérer le nom de la map depuis l'URL
$mapName = isset($_GET['map']) ? $_GET['map'] : null;

if (!$mapName) {
    header('Location: maps.php');
    exit;
}

// Récupérer les informations de la map avec la vidéo YouTube
$mapDataManager = new MapDataManager($config['mapinfo_file'], $config['records_file']);
$mapInfo = $mapDataManager->getMapInfo($mapName);
$mapRecord = $mapDataManager->getMapRecord($mapName);

// Vérifier si la map existe
if (empty($mapInfo)) {
    $mapExists = false;
} else {
    $mapExists = true;
    // Charger l'image de la map
    $mapImageManager = new MapImageManager($config['maps_directory'], $config['local_images_directory']);
    $mapImageUrl = $mapImageManager->getMapImage($mapName);
    
    if (!file_exists(__DIR__ . '/' . $mapImageUrl)) {
        $mapImageUrl = 'images/maps/default.jpg';
    }
    
    // Convertir la difficulté si nécessaire
    if (is_array($mapInfo['difficulty'])) {
        $mapInfo['difficulty'] = $mapInfo['difficulty'][0];
    }
    
    // Vérifier s'il y a une vidéo YouTube associée
    $youtubeUrl = isset($mapInfo['youtube_url']) ? $mapInfo['youtube_url'] : '';
    $youtubeEmbedId = '';
    
    // Extraire l'ID de la vidéo YouTube s'il y a une URL
    if (!empty($youtubeUrl)) {
        // Format https://www.youtube.com/watch?v=VIDEO_ID
        if (preg_match('/[?&]v=([^&]+)/', $youtubeUrl, $matches)) {
            $youtubeEmbedId = $matches[1];
        }
        // Format https://youtu.be/VIDEO_ID
        else if (preg_match('/youtu\.be\/([^?&]+)/', $youtubeUrl, $matches)) {
            $youtubeEmbedId = $matches[1];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mapExists ? htmlspecialchars($mapName) : $t['unknown_map']; ?> - Détails</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/maps_styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="/index.php" class="logo">LaFumisterie</a>
            <div class="nav-links">
                <a href="/index.php" class="nav-button"><?php echo $t['nav_home']; ?></a>
                <a href="/status-v1/index.php" class="nav-button"><?php echo $t['nav_status']; ?></a>
                <a href="/status-v1/maps.php" class="nav-button active"><?php echo $t['nav_maps']; ?></a>
                <a href="https://github.com/sysmyks" class="nav-button"><?php echo $t['nav_github']; ?></a>
                <a href="/status-v1/admin/login.php" class="nav-button"><?php echo $t['nav_admin']; ?></a>
            </div>
            <div class="lang-switcher">
                <a href="<?php echo htmlspecialchars(langSwitchUrl('fr')); ?>" class="lang-btn <?php echo $lang === 'fr' ? 'active' : ''; ?>">FR</a>
                <a href="<?php echo htmlspecialchars(langSwitchUrl('en')); ?>" class="lang-btn <?php echo $lang === 'en' ? 'active' : ''; ?>">EN</a>
            </div>
        </div>
    </nav>
    
    <div class="content">
        <?php if (!$mapExists): ?>
            <div class="error-message">
                <h1><?php echo $t['map_not_found_title']; ?></h1>
                <p><?php echo $t['map_not_found_msg']; ?></p>
                <a href="maps.php" class="back-button"><?php echo $t['back_to_maps']; ?></a>
            </div>
        <?php else: ?>
            <div class="map-detail-header">
                <a href="maps.php" class="back-button"><?php echo $t['back_arrow_maps']; ?></a>
                <h1 class="page-title"><?php echo htmlspecialchars($mapName); ?></h1>
            </div>
            
            <div class="map-detail-container">
                <div class="map-detail-image">
                    <img src="<?php echo $mapImageUrl; ?>" alt="<?php echo htmlspecialchars($mapName); ?>" class="detail-image">
                    
                    <?php if (!empty($youtubeEmbedId)): ?>
                    <div class="map-video-container">
                        <h3><?php echo $t['demo_video']; ?></h3>
                        <div class="video-wrapper">
                            <iframe 
                                width="560" 
                                height="315" 
                                src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtubeEmbedId); ?>" 
                                title="YouTube video player" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="map-detail-info">
                    <button class="map-vote-btn" id="voteBtn" data-mapname="<?php echo htmlspecialchars($mapName); ?>"
                        data-txt-default="<?php echo htmlspecialchars($t['vote_map']); ?>"
                        data-txt-sending="<?php echo htmlspecialchars($t['vote_sending']); ?>"
                        data-txt-sent="<?php echo htmlspecialchars($t['vote_sent']); ?>"
                        data-txt-network-error="<?php echo htmlspecialchars($t['vote_error_network']); ?>"
                    ><?php echo $t['vote_map']; ?></button>
                    <div class="info-section">
                        <h2><?php echo $t['info_section']; ?></h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label"><?php echo $t['label_author']; ?></div>
                                <div class="info-value"><?php echo htmlspecialchars($mapInfo['author'] ?? $t['unknown']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><?php echo $t['label_difficulty']; ?></div>
                                <div class="info-value"><?php echo htmlspecialchars($mapInfo['difficulty'] ?? '?'); ?>/100</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><?php echo $t['label_jumps']; ?></div>
                                <div class="info-value"><?php echo htmlspecialchars($mapInfo['jumps'] ?? '0'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label"><?php echo $t['label_download']; ?></div>
                                <div class="info-value"><a href="https://lafumisterie.net/q3ut4/<?php echo urlencode($mapName); ?>.pk3" class="download-link" target="_blank"><?php echo htmlspecialchars($mapName); ?></a></div>
                            </div>
                            <?php if (isset($mapInfo['description'])): ?>
                            <div class="info-item">
                                <div class="info-label"><?php echo $t['label_description']; ?></div>
                                <div class="info-value"><?php echo htmlspecialchars($mapInfo['description']); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($mapInfo['release_date'])): ?>
                            <div class="info-item">
                                <div class="info-label"><?php echo $t['label_release_date']; ?></div>
                                <div class="info-value"><?php echo htmlspecialchars($mapInfo['release_date']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($mapRecord && isset($mapRecord['best'])): ?>
                <div class="records-section full-width">
                    <h2><?php echo $t['records_section']; ?></h2>
                    
                    <div class="tabs">
                        <?php foreach ($mapRecord['all'] as $way => $records): ?>
                        <button class="tab-btn <?php echo $way === array_key_first($mapRecord['all']) ? 'active' : ''; ?>" data-tab="way<?php echo $way; ?>">Way <?php echo htmlspecialchars($way); ?></button>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php foreach ($mapRecord['all'] as $way => $records): ?>
                    <div id="way<?php echo $way; ?>" class="tab-content <?php echo $way === array_key_first($mapRecord['all']) ? 'active' : ''; ?>">
                        <table class="records-table">
                            <thead>
                                <tr>
                                    <th><?php echo $t['th_position']; ?></th>
                                    <th><?php echo $t['th_player']; ?></th>
                                    <th><?php echo $t['th_time']; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $index => $record): ?>
                                <tr <?php echo $index === 0 ? 'class="best-time"' : ''; ?>>
                                    <td>#<?php echo $index + 1; ?></td>
                                    <td><?php echo $record['player']; ?></td>
                                    <td><?php echo htmlspecialchars($record['time']); ?>s</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Vote map
        const voteBtn = document.getElementById('voteBtn');
        if (voteBtn) {
            const txtDefault      = voteBtn.dataset.txtDefault;
            const txtSending      = voteBtn.dataset.txtSending;
            const txtSent         = voteBtn.dataset.txtSent;
            const txtNetworkError = voteBtn.dataset.txtNetworkError;

            voteBtn.addEventListener('click', function() {
                const mapName = this.getAttribute('data-mapname');
                const button = this;
                button.disabled = true;
                button.textContent = txtSending;

                const formData = new FormData();
                formData.append('map', mapName);

                fetch('vote_map.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        button.textContent = txtSent;
                        button.classList.add('voted');
                    } else {
                        button.textContent = '❌ ' + data.message;
                        button.disabled = false;
                    }
                    setTimeout(() => {
                        button.textContent = txtDefault;
                        button.classList.remove('voted');
                        button.disabled = false;
                    }, 4000);
                })
                .catch(() => {
                    button.textContent = txtNetworkError;
                    button.disabled = false;
                    setTimeout(() => {
                        button.textContent = txtDefault;
                    }, 3000);
                });
            });
        }

        // Script pour les onglets des records
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Désactiver tous les onglets
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                    
                    // Activer l'onglet cliqué
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>