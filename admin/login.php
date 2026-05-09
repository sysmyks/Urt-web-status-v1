<?php
// lang_init.php doit être AVANT session_start() car il démarre la session
require_once __DIR__ . '/../lang_init.php';
$config = require_once __DIR__ . '/../config/config.php';

// Si le hash n'est pas configuré, afficher un message d'aide
$notConfigured = empty($config['admin_password_hash']);

// Déjà connecté → redirection
if (!$notConfigured && !empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// ---------- Gestion du brute-force ----------
$maxAttempts     = 5;
$lockoutDuration = 300; // 5 minutes en secondes
$attempts        = $_SESSION['login_attempts']    ?? 0;
$lastAttempt     = $_SESSION['last_attempt_time'] ?? 0;
$isLocked        = ($attempts >= $maxAttempts && (time() - $lastAttempt) < $lockoutDuration);
$remaining       = $isLocked ? (int)ceil(($lockoutDuration - (time() - $lastAttempt)) / 60) : 0;

$error = '';

if (!$notConfigured && $_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === $config['admin_username']
        && password_verify($password, $config['admin_password_hash'])
    ) {
        // Succès
        $_SESSION['login_attempts']    = 0;
        $_SESSION['admin_logged_in']   = true;
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['login_attempts']    = $attempts + 1;
        $_SESSION['last_attempt_time'] = time();
        $attempts   = $_SESSION['login_attempts'];
        $isLocked   = ($attempts >= $maxAttempts);
        $error      = $t['admin_login_error'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['admin_login_title']; ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="login-page">

<div class="login-container">
    <div class="login-box">
        <div class="login-logo">LaFumisterie</div>
        <h1><?php echo $t['admin_login_title']; ?></h1>
        <p class="login-subtitle"><?php echo $t['admin_login_subtitle']; ?></p>

        <?php if ($notConfigured): ?>
        <div class="alert alert-warning">
            <?php echo $t['admin_not_configured']; ?>
        </div>

        <?php else: ?>

        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($isLocked): ?>
        <div class="alert alert-warning">
            <?php echo sprintf($t['admin_locked'], $remaining); ?>
        </div>
        <?php else: ?>
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label><?php echo $t['admin_username']; ?></label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label><?php echo $t['admin_password']; ?></label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-primary btn-full"><?php echo $t['admin_login_btn']; ?></button>
        </form>
        <?php if ($attempts > 0): ?>
        <p class="attempt-warning">
            <?php echo sprintf($t['admin_attempts'], $attempts, $maxAttempts); ?>
        </p>
        <?php endif; ?>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

</body>
</html>
