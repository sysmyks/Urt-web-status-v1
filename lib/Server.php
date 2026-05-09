<?php

class Server {
    private $host;
    private $port;
    private $timeout;
    private $cacheFile;
    private $cacheExpiry = 60; // 60 secondes
    private $statusParser;

    public function __construct($host, $port, $timeout = 1) {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->cacheFile = __DIR__ . '/../cache/server_status.json';
        $this->statusParser = new StatusParser();
    }

    public function getStatus() {
        // Vérifie si le cache est valide
        if ($this->isCacheValid()) {
            return json_decode(file_get_contents($this->cacheFile), true);
        }
        
        // Si pas de cache valide, interroge le serveur
        $rawData = $this->queryServer();
        
        // Parse les données avant de les mettre en cache
        $statusData = $this->statusParser->parse($rawData);
        
        // Stocke les données parsées dans le cache
        $this->writeCache($statusData);
        
        return $statusData;
    }

    private function queryServer() {
        // Création d'un socket UDP
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            return $this->getErrorResponse();
        }

        // Configuration du timeout
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 1, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 1, 'usec' => 0]);
        
        // Bind le socket à n'importe quelle adresse
        if (!socket_bind($socket, '0.0.0.0', 0)) {
            socket_close($socket);
            return $this->getErrorResponse();
        }

        // Envoi de la commande status
        $command = "\xFF\xFF\xFF\xFFgetstatus\n";
        if (!socket_sendto($socket, $command, strlen($command), 0, $this->host, $this->port)) {
            socket_close($socket);
            return $this->getErrorResponse();
        }

        // Lecture de la réponse
        $response = '';
        $from = '';
        $port = 0;
        
        $result = socket_recvfrom($socket, $response, 8192, 0, $from, $port);
        if ($result === false) {
            socket_close($socket);
            return $this->getErrorResponse();
        }

        socket_close($socket);
        return $response;
    }

    private function isCacheValid() {
        if (!file_exists($this->cacheFile)) {
            return false;
        }

        $cacheTime = filemtime($this->cacheFile);
        return (time() - $cacheTime) < $this->cacheExpiry;
    }

    private function writeCache($data) {
        file_put_contents($this->cacheFile, json_encode($data));
    }

    private function getErrorResponse() {
        return [
            'error' => 'Erreur serveur',
            'hostname_colored' => 'Serveur non disponible',
            'mapname' => 'inconnu',
            'gametype' => 'Jump',
            'players' => 0,
            'maxplayers' => 0,
            'players_list' => []
        ];
    }
}