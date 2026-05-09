<?php

/**
 * SpunkyDb — Accès en lecture/écriture à la base SQLite de Spunkybot.
 *
 * Tables utilisées :
 *   xlrstats  : historique joueurs (stats + dernière IP)
 *   ban_list  : bans gérés par Spunkybot
 */
class SpunkyDb {
    private PDO $pdo;

    public function __construct(string $dbPath) {
        if (!file_exists($dbPath)) {
            throw new RuntimeException("SQLite introuvable : $dbPath");
        }
        $dsn = 'sqlite:' . $dbPath;
        $this->pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 3,
        ]);
        // WAL pour éviter les blocages avec Spunkybot
        $this->pdo->exec('PRAGMA journal_mode=WAL;');
        $this->pdo->exec('PRAGMA busy_timeout=2000;');
    }

    // ------------------------------------------------------------------
    // Recherche de joueurs dans xlrstats
    // ------------------------------------------------------------------
    /**
     * Cherche des joueurs par nom (partiel) ou IP exacte.
     * Retourne jusqu'à $limit résultats triés par dernière connexion.
     */
    public function searchPlayers(string $query, int $limit = 60): array {
        $query = trim($query);
        if ($query === '') return [];

        $like = '%' . $query . '%';
        $stmt = $this->pdo->prepare(
            'SELECT x.id, x.guid, x.name, x.ip_address,
                    x.first_seen, x.last_played, x.num_played, x.admin_role,
                    CASE WHEN b.id IS NOT NULL THEN 1 ELSE 0 END AS is_banned,
                    b.id    AS ban_id,
                    b.expires   AS ban_expires,
                    b.reason    AS ban_reason
             FROM xlrstats x
             LEFT JOIN ban_list b ON (b.guid = x.guid OR b.ip_address = x.ip_address)
                                  AND b.expires > datetime(\'now\')
             WHERE x.name LIKE :like OR x.ip_address = :exact
             GROUP BY x.id
             ORDER BY x.last_played DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':like',  $like,  PDO::PARAM_STR);
        $stmt->bindValue(':exact', $query, PDO::PARAM_STR);
        $stmt->bindValue(':lim',   $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupère un joueur par son ID dans xlrstats.
     */
    public function getPlayerById(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT id, guid, name, ip_address, first_seen, last_played, num_played, admin_role
             FROM xlrstats WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // Gestion des bans (ban_list)
    // ------------------------------------------------------------------
    /**
     * Liste les bans actifs (expires > maintenant).
     */
    public function getActiveBans(int $limit = 100): array {
        $stmt = $this->pdo->prepare(
            'SELECT id, guid, name, ip_address, expires, timestamp, reason
             FROM ban_list
             WHERE expires > datetime(\'now\')
             ORDER BY timestamp DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Bannit un joueur dans la ban_list de Spunkybot.
     *
     * @param int    $playerId        ID dans xlrstats (utilisé comme id dans ban_list)
     * @param string $guid
     * @param string $name
     * @param string $ip
     * @param int    $durationSeconds  Durée en secondes. 0 = permanent (20 ans).
     * @param string $reason
     */
    public function banPlayer(int $playerId, string $guid, string $name, string $ip,
                              int $durationSeconds, string $reason): bool {
        if ($durationSeconds <= 0) {
            // Permanent : 20 ans, comme Spunkybot !permban
            $durationSeconds = 630720000;
        }
        $expires   = date('Y-m-d H:i:s', time() + $durationSeconds);
        $timestamp = date('Y-m-d H:i:s');

        // Vérifier si un ban existe déjà pour ce guid
        $stmt = $this->pdo->prepare(
            'SELECT id, expires FROM ban_list WHERE guid = ? LIMIT 1'
        );
        $stmt->execute([$guid]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Prolonger uniquement si la nouvelle expiration est plus lointaine
            if ($expires > $existing['expires']) {
                $upd = $this->pdo->prepare(
                    'UPDATE ban_list SET ip_address=?, expires=?, reason=? WHERE guid=?'
                );
                $upd->execute([$ip, $expires, $reason, $guid]);
            }
        } else {
            $ins = $this->pdo->prepare(
                'INSERT INTO ban_list (id, guid, name, ip_address, expires, timestamp, reason)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([$playerId, $guid, $name, $ip, $expires, $timestamp, $reason]);
        }
        return true;
    }

    /**
     * Supprime un ban par son ID (comme !unban de Spunkybot).
     * Supprime aussi les doublons (même guid ou même IP).
     *
     * @return array ['guid'=>..., 'name'=>..., 'ip'=>...] ou [] si non trouvé
     */
    public function unbanById(int $banId): array {
        $stmt = $this->pdo->prepare(
            'SELECT guid, name, ip_address FROM ban_list WHERE id = ?'
        );
        $stmt->execute([$banId]);
        $row = $stmt->fetch();
        if (!$row) return [];

        // Supprime le ban principal
        $this->pdo->prepare('DELETE FROM ban_list WHERE id = ?')->execute([$banId]);
        // Supprime les doublons (même guid ou même IP)
        $this->pdo->prepare(
            'DELETE FROM ban_list WHERE guid = ? OR ip_address = ?'
        )->execute([$row['guid'], $row['ip_address']]);

        return [
            'guid' => $row['guid'],
            'name' => $row['name'],
            'ip'   => $row['ip_address'],
        ];
    }
}
