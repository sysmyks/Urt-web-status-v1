<?php
/**
 * UTILITAIRE — Générateur de hash pour le panneau admin.
 *
 * ⚠️  SUPPRIMER CE FICHIER après utilisation !
 *
 * Visitez cette page sur votre serveur, entrez votre mot de passe,
 * copiez le hash généré dans config/config.php ('admin_password_hash').
 */

// Restriction localhost désactivée temporairement pour génération du hash.

$hash  = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générateur de hash — Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #111; color: #e0e0e0;
               display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .box { background: #1a1a1a; border: 1px solid #333; border-radius: 10px;
               padding: 36px 32px; width: 100%; max-width: 480px; }
        h1 { color: #00b4d8; margin: 0 0 8px; font-size: 1.4rem; }
        p.sub { color: #888; font-size: 0.85rem; margin: 0 0 24px; }
        label { display: block; color: #999; font-size: 0.85rem; margin-bottom: 5px; }
        input[type=password] { width: 100%; padding: 10px 12px; background: #252525;
               border: 1px solid #333; border-radius: 6px; color: #e0e0e0;
               font-size: 0.95rem; margin-bottom: 16px; }
        input[type=password]:focus { outline: none; border-color: #00b4d8; }
        button { background: #00b4d8; color: #000; border: none; padding: 10px 22px;
                 border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.95rem; }
        .error { background: rgba(255,60,60,0.1); border: 1px solid rgba(255,60,60,0.3);
                 color: #ff6b6b; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
        .result { background: #0d2a1a; border: 1px solid #2d6a4f; border-radius: 6px;
                  padding: 16px; margin-top: 22px; }
        .result p { margin: 0 0 8px; color: #52b788; font-weight: bold; }
        .result code { display: block; background: #111; padding: 12px; border-radius: 4px;
                       word-break: break-all; font-size: 0.82rem; color: #ccc; }
        .result .step { color: #888; font-size: 0.85rem; margin-top: 12px; }
        .warn { background: rgba(255,180,0,0.1); border: 1px solid rgba(255,180,0,0.3);
                color: #ffc107; padding: 12px 14px; border-radius: 6px; margin-top: 22px;
                font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="box">
    <h1>🔑 Générateur de hash admin</h1>
    <p class="sub">Définissez le mot de passe du panneau d'administration.</p>

    <?php if ($error): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!$hash): ?>
    <form method="POST">
        <label>Mot de passe (min. 8 caractères)</label>
        <input type="password" name="password" required autofocus>
        <label>Confirmation</label>
        <input type="password" name="confirm" required>
        <button type="submit">Générer le hash</button>
    </form>
    <?php else: ?>
    <div class="result">
        <p>✅ Hash généré avec succès !</p>
        <code><?php echo htmlspecialchars($hash); ?></code>
        <p class="step">
            Copiez ce hash dans <strong>config/config.php</strong> :<br>
            <code>'admin_password_hash' => '<?php echo htmlspecialchars($hash); ?>'</code>
        </p>
    </div>
    <?php endif; ?>

    <div class="warn">
        ⚠️ <strong>Supprimez ce fichier</strong> (<code>generate_hash.php</code>)
        dès que vous avez copié le hash dans votre config.
    </div>
</div>
</body>
</html>
