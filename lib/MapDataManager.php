<?php

class MapDataManager {
    private $mapInfoFile;
    private $recordsFile;

    public function __construct($mapInfoFile, $recordsFile) {
        $this->mapInfoFile = $mapInfoFile;
        $this->recordsFile = $recordsFile;
    }

    public function getMapInfo($mapName) {
        $mapInfo = [];
        
        if (file_exists($this->mapInfoFile)) {
            $mapData = json_decode(file_get_contents($this->mapInfoFile), true);
            if (isset($mapData[$mapName])) {
                $mapInfo = $mapData[$mapName];
                // Conversion de la difficulté en pourcentage si c'est un tableau
                if (is_array($mapInfo['difficulty'])) {
                    $mapInfo['difficulty'] = $mapInfo['difficulty'][0];
                }
                
                // Traitement de l'URL YouTube si elle existe
                if (isset($mapInfo['youtube_url']) && !empty($mapInfo['youtube_url'])) {
                    $mapInfo['youtube_embed_id'] = $this->extractYoutubeId($mapInfo['youtube_url']);
                }
            }
        }

        return $mapInfo;
    }
    
    /**
     * Extrait l'ID de la vidéo YouTube à partir de l'URL
     * 
     * @param string $url L'URL YouTube
     * @return string|null L'ID de la vidéo ou null si non trouvé
     */
    private function extractYoutubeId($url) {
        // Format https://www.youtube.com/watch?v=VIDEO_ID
        if (preg_match('/[?&]v=([^&]+)/', $url, $matches)) {
            return $matches[1];
        }
        // Format https://youtu.be/VIDEO_ID
        else if (preg_match('/youtu\.be\/([^?&]+)/', $url, $matches)) {
            return $matches[1];
        }
        // Format https://www.youtube.com/embed/VIDEO_ID
        else if (preg_match('/\/embed\/([^?&\/]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    public function getMapRecord($mapName) {
        if (file_exists($this->recordsFile)) {
            $recordsData = json_decode(file_get_contents($this->recordsFile), true);
            if (isset($recordsData[$mapName])) {
                $records = $recordsData[$mapName];
                $wayRecords = [];
                $allRecords = [];
                
                // Organiser tous les records par way
                foreach ($records as $player => $attempts) {
                    foreach ($attempts as $way => $time) {
                        // Pour garder le meilleur temps par way
                        if (!isset($wayRecords[$way]) || $time < $wayRecords[$way]['raw_time']) {
                            $totalSeconds = $time / 1000;
                            $minutes = floor($totalSeconds / 60);
                            $remainingSeconds = $totalSeconds - ($minutes * 60);
                            
                            $timeFormatted = $minutes > 0 
                                ? sprintf("%d:%06.3f", $minutes, $remainingSeconds)
                                : sprintf("%06.3f", $remainingSeconds);
                            
                            $wayRecords[$way] = [
                                'time' => $timeFormatted,
                                'player' => $player,
                                'raw_time' => $time,
                                'way' => $way
                            ];
                        }
                        
                        // Pour garder tous les temps de tous les joueurs
                        if (!isset($allRecords[$way])) {
                            $allRecords[$way] = [];
                        }
                        
                        $totalSeconds = $time / 1000;
                        $minutes = floor($totalSeconds / 60);
                        $remainingSeconds = $totalSeconds - ($minutes * 60);
                        
                        $timeFormatted = $minutes > 0 
                            ? sprintf("%d:%06.3f", $minutes, $remainingSeconds)
                            : sprintf("%06.3f", $remainingSeconds);
                        
                        $allRecords[$way][] = [
                            'time' => $timeFormatted,
                            'player' => $player,
                            'raw_time' => $time
                        ];
                    }
                }
                
                // Trier les records par temps pour chaque way
                foreach ($allRecords as &$wayTimes) {
                    usort($wayTimes, function($a, $b) {
                        return $a['raw_time'] - $b['raw_time'];
                    });
                }
                
                // Trier par numéro de way
                ksort($wayRecords);
                ksort($allRecords);
                
                return [
                    'best' => $wayRecords,
                    'all' => $allRecords
                ];
            }
        }
        return null;
    }
}