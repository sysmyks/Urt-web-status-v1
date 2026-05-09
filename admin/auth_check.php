<?php
/**
 * Garde de session admin.
 * Inclure EN PREMIER dans chaque page admin (après lang_init.php).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Pour les requêtes AJAX, retourner JSON 401 plutôt que rediriger
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session expirée', 'redirect' => 'login.php']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// Génère le CSRF token si absent
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
