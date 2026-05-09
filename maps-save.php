<?php
$config = require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/MapDataManager.php';
require_once __DIR__ . '/lib/MapImageManager.php';

// Récupérer les paramètres de recherche et filtrage
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$difficultyFilter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';

// Récupérer toutes les maps depuis le fichier de configuration
$allMapsData = [];
if (file_exists($config['mapinfo_file'])) {
    $allMapsData = json_decode(file_get_contents($config['mapinfo_file']), true);
}

// Filtrer les maps en fonction des critères de recherche
$filteredMaps = [];
foreach ($allMapsData as $mapName => $mapInfo) {
    $difficulty = is_array($mapInfo['difficulty']) ? $mapInfo['difficulty'][0] : $mapInfo['difficulty'];
    $difficultyClass = 'easy';
    if ($difficulty > 70) $difficultyClass = 'hard';
    else if ($difficulty > 30) $difficultyClass = 'medium';
    
    // Filtrer par recherche de nom
    if (!empty($searchTerm) && stripos($mapName, $searchTerm) === false) {
        continue;
    }
    
    // Filtrer par difficulté
    if ($difficultyFilter !== 'all') {
        if ($difficultyFilter === 'easy' && $difficulty > 30) continue;
        if ($difficultyFilter === 'medium' && ($difficulty <= 30 || $difficulty > 70)) continue;
        if ($difficultyFilter === 'hard' && $difficulty <= 70) continue;
    }
    
    $filteredMaps[$mapName] = $mapInfo;
}

// Trier les maps par difficulté (croissante)
uasort($filteredMaps, function($a, $b) {
    $diffA = is_array($a['difficulty']) ? $a['difficulty'][0] : $a['difficulty'];
    $diffB = is_array($b['difficulty']) ? $b['difficulty'][0] : $b['difficulty'];
    return $diffA - $diffB;
});

// Configuration de la pagination
$mapsPerPage = 12; // Nombre de maps par page
$totalMaps = count($filteredMaps);
$totalPages = max(1, ceil($totalMaps / $mapsPerPage));

// Obtenir la page actuelle depuis l'URL
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;

// Déterminer quelles maps afficher sur la page actuelle
$startIndex = ($currentPage - 1) * $mapsPerPage;
$mapsOnCurrentPage = array_slice($filteredMaps, $startIndex, $mapsPerPage, true);

// Initialiser les gestionnaires d'images après avoir déterminé quelles maps afficher
$mapDataManager = new MapDataManager($config['mapinfo_file'], $config['records_file']);
$mapImageManager = new MapImageManager($config['maps_directory'], $config['local_images_directory']);

// Fonction pour générer l'URL de pagination avec les paramètres de recherche
function buildPaginationUrl($page, $search, $filter) {
    $params = ['page' => $page];
    if (!empty($search)) $params['search'] = $search;
    if ($filter !== 'all') $params['filter'] = $filter;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maps du Serveur</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/maps_styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="/index.php" class="logo">LaFumisterie</a>
            <div class="nav-links">
                <a href="/index.php" class="nav-button">Dépôt</a>
                <a href="/status-v1/index.php" class="nav-button">Status Serveur</a>
                <a href="/status-v1/maps.php" class="nav-button active">Maps</a>
                <a href="https://github.com/sysmyks" class="nav-button">GitHub</a>
            </div>
        </div>
    </nav>
    
    <div class="content">
        <h1 class="page-title">Maps du Serveur</h1>
        
        <div class="maps-filter">
            <form action="maps.php" method="get" id="searchForm">
                <input type="text" id="mapSearch" name="search" placeholder="Rechercher une map..." class="search-input" value="<?php echo htmlspecialchars($searchTerm); ?>">
                <input type="hidden" name="filter" id="difficultyFilter" value="<?php echo htmlspecialchars($difficultyFilter); ?>">
                <div class="filter-buttons">
                    <button type="button" class="filter-btn <?php echo $difficultyFilter === 'all' ? 'active' : ''; ?>" data-filter="all">Toutes</button>
                    <button type="button" class="filter-btn <?php echo $difficultyFilter === 'easy' ? 'active' : ''; ?>" data-filter="easy">Facile (1-30)</button>
                    <button type="button" class="filter-btn <?php echo $difficultyFilter === 'medium' ? 'active' : ''; ?>" data-filter="medium">Moyenne (31-70)</button>
                    <button type="button" class="filter-btn <?php echo $difficultyFilter === 'hard' ? 'active' : ''; ?>" data-filter="hard">Difficile (71-100)</button>
                </div>
            </form>
        </div>
        
        <div class="maps-grid">
            <?php foreach ($mapsOnCurrentPage as $mapName => $mapInfo): 
                $difficulty = is_array($mapInfo['difficulty']) ? $mapInfo['difficulty'][0] : $mapInfo['difficulty'];
                $difficultyClass = 'easy';
                if ($difficulty > 70) $difficultyClass = 'hard';
                else if ($difficulty > 30) $difficultyClass = 'medium';
                
                $mapImageUrl = $mapImageManager->getMapImage($mapName);
                if (!file_exists(__DIR__ . '/' . $mapImageUrl)) {
                    $mapImageUrl = 'images/maps/default.jpg';
                }
            ?>
            <div class="map-card <?php echo $difficultyClass; ?>" data-mapname="<?php echo htmlspecialchars($mapName); ?>">
                <div class="map-card-image" style="background-image: url('<?php echo $mapImageUrl; ?>')"></div>
                <div class="map-card-content">
                    <h3 class="map-title"><?php echo htmlspecialchars($mapName); ?></h3>
                    <div class="map-info">
                        <div class="difficulty-badge difficulty-<?php echo $difficultyClass; ?>">
                            <?php echo htmlspecialchars($difficulty); ?>/100
                        </div>
                        <div class="map-author">
                            <?php echo htmlspecialchars($mapInfo['author'] ?? 'Inconnu'); ?>
                        </div>
                    </div>
                    <a href="map_details.php?map=<?php echo urlencode($mapName); ?>" class="map-details-btn">Voir détails</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($mapsOnCurrentPage)): ?>
        <div class="no-maps">
            <p>Aucune map ne correspond à votre recherche.</p>
            <?php if (!empty($searchTerm) || $difficultyFilter !== 'all'): ?>
            <a href="maps.php" class="back-button">Réinitialiser les filtres</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="<?php echo buildPaginationUrl(1, $searchTerm, $difficultyFilter); ?>" class="page-link first">«</a>
                <a href="<?php echo buildPaginationUrl($currentPage - 1, $searchTerm, $difficultyFilter); ?>" class="page-link prev">‹</a>
            <?php endif; ?>
            
            <?php
            // Afficher un maximum de 5 liens de pagination
            $startPage = max(1, min($currentPage - 2, $totalPages - 4));
            $endPage = min($totalPages, max($currentPage + 2, 5));
            
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="<?php echo buildPaginationUrl($i, $searchTerm, $difficultyFilter); ?>" class="page-link <?php echo $i == $currentPage ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?php echo buildPaginationUrl($currentPage + 1, $searchTerm, $difficultyFilter); ?>" class="page-link next">›</a>
                <a href="<?php echo buildPaginationUrl($totalPages, $searchTerm, $difficultyFilter); ?>" class="page-link last">»</a>
            <?php endif; ?>
            
            <div class="page-info">Page <?php echo $currentPage; ?> sur <?php echo $totalPages; ?> (<?php echo $totalMaps; ?> maps)</div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.getElementById('searchForm');
            const searchInput = document.getElementById('mapSearch');
            const filterButtons = document.querySelectorAll('.filter-btn');
            const difficultyFilterInput = document.getElementById('difficultyFilter');
            
            // Activer la recherche lors de la saisie de texte après un délai
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    searchForm.submit();
                }, 500); // Soumettre après 500ms d'inactivité
            });
            
            // Activer les filtres de difficulté
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    difficultyFilterInput.value = filter;
                    searchForm.submit();
                });
            });
            
            // Soumettre le formulaire sur Enter
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    e.preventDefault();
                    searchForm.submit();
                }
            });
        });
    </script>
</body>
</html>