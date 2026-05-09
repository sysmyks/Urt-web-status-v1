<?php

class StatusParser {
    public function parse($data) {
        if (is_array($data) && isset($data['error'])) {
            return $data;
        }

        // Nettoyer les données brutes
        $cleanData = substr($data, strpos($data, "\n") + 1);
        $parts = explode("\\", $cleanData);
        
        // Récupérer les informations de base
        $hostname = $this->findValue('sv_hostname', $parts);
        
        // Extraire les informations de base
        $result = [
            'hostname_colored' => $this->convertColorCodes($hostname),
            'mapname' => $this->findValue('mapname', $parts),
            'gametype' => 'Jump',
            'players' => 0,
            'maxplayers' => (int)$this->findValue('sv_maxclients', $parts),
            'players_list' => []
        ];

        // Extraire la liste des joueurs
        $lines = explode("\n", $data);
        array_shift($lines); // Enlever la première ligne (statusResponse)
        
        foreach ($lines as $line) {
            if (preg_match('/^(\d+)\s+(\d+)\s+"([^"]+)"/', $line, $matches)) {
                $result['players_list'][] = [
                    'score' => $matches[1],
                    'ping' => $matches[2],
                    'name' => $this->convertColorCodes($matches[3]) // Ajout de la conversion des codes couleur
                ];
            }
        }
        
        // Mettre à jour le nombre de joueurs
        $result['players'] = count($result['players_list']);

     
        return $result;
    }

    private function findValue($key, $array) {
        $index = array_search($key, $array);
        if ($index !== false && isset($array[$index + 1])) {
            return $array[$index + 1];
        }
        return null;
    }

    private function convertColorCodes($text) {
        if (empty($text)) return '';
        
        // Échapper d'abord tous les caractères HTML spéciaux
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        $colors = [
            '^1' => '<span style="color: #FF0000;">',
            '^2' => '<span style="color: #00FF00;">',
            '^3' => '<span style="color: #FFFF00;">',
            '^4' => '<span style="color: #0000FF;">',
            '^5' => '<span style="color: #00FFFF;">',
            '^6' => '<span style="color: #FF00FF;">',
            '^7' => '<span style="color: #FFFFFF;">',
            '^8' => '<span style="color: #FFA500;">',
            '^9' => '<span style="color: #808080;">',
            '^0' => '<span style="color: #000000;">'
        ];

        $result = $text;
        foreach ($colors as $code => $html) {
            // Remplacer les codes de couleur échappés (^1 devient ^1)
            $escapedCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
            $result = str_replace($escapedCode, $html, $result);
        }
        
        // Fermer tous les spans
        $count = substr_count($result, '<span');
        $result .= str_repeat('</span>', $count);

        return $result;
    }
}