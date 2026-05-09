<?php

/**
 * Classe RCON pour Urban Terror / ioquake3
 * Gère la communication UDP RCON avec le serveur de jeu.
 */
class Rcon {
    private string $host;
    private int $port;
    private string $password;
    /** @var resource|null */
    private $socket = null;

    public function __construct(string $host, int $port, string $password) {
        $this->host     = $host;
        $this->port     = $port;
        $this->password = $password;
    }

    public function connect(): bool {
        $this->socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$this->socket) return false;
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 2, 'usec' => 0]);
        return true;
    }

    public function disconnect(): void {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Envoie une commande RCON et retourne la réponse brute.
     */
    public function send(string $command): string {
        if (!$this->socket) return '';
        $packet = "\xFF\xFF\xFF\xFFrcon {$this->password} {$command}\n";
        @socket_sendto($this->socket, $packet, strlen($packet), 0, $this->host, $this->port);
        $buf  = '';
        $from = '';
        $port = 0;
        @socket_recvfrom($this->socket, $buf, 8192, 0, $from, $port);
        // Supprime le header ÿÿÿÿprint\n
        $nl = strpos($buf, "\n");
        return ($nl !== false) ? substr($buf, $nl + 1) : $buf;
    }

    /**
     * Parse la sortie de "rcon status" en liste de joueurs.
     * Format attendu :
     *   num score ping name            lastmsg address               qport rate
     *     0   200   45 ^1PlayerName        0 1.2.3.4:27961         12345 25000
     */
    public function parseStatusPlayers(string $raw): array {
        $players = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            // Ligne joueur : commence par un numéro de slot
            if (!preg_match(
                '/^\s*(\d+)\s+(-?\d+)\s+(\d+)\s+(.+?)\s+(\d+\.\d+\.\d+\.\d+(?::\d+)?)\s+\d+/',
                $line, $m
            )) {
                continue;
            }
            $address = $m[5];
            $ip      = strstr($address, ':', true) ?: $address; // enlève le port
            $players[] = [
                'slot'    => (int)$m[1],
                'score'   => (int)$m[2],
                'ping'    => (int)$m[3],
                'name'    => self::stripColorCodes(trim($m[4])),
                'address' => $address,
                'ip'      => $ip,
            ];
        }
        return $players;
    }

    /**
     * Parse la sortie de "rcon listip" en liste de bans.
     * Format attendu :
     *   0: 1.2.3.4/32
     *   1: 5.6.7.8/32
     */
    public function parseBanList(string $raw): array {
        $bans = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (preg_match('/^(\d+)[:\s]+([\d\.\/]+)/', $line, $m)) {
                $bans[] = [
                    'index' => (int)$m[1],
                    'ip'    => trim($m[2]),
                ];
            }
        }
        return $bans;
    }

    /**
     * Supprime les codes couleur Q3 (^1, ^2, ...) d'une chaîne.
     */
    public static function stripColorCodes(string $text): string {
        return preg_replace('/\^[0-9]/', '', $text);
    }
}
