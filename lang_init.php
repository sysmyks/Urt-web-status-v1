<?php
/**
 * Initialisation de la langue du site.
 * Doit être inclus EN PREMIER dans chaque page (avant tout output).
 *
 * Priorité : GET ?lang= > session > cookie > défaut 'fr'
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_allowedLangs = ['fr', 'en'];

// Si un changement de langue est demandé via l'URL
if (isset($_GET['lang']) && in_array($_GET['lang'], $_allowedLangs, true)) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
    setcookie('lang', $lang, time() + (365 * 24 * 3600), '/');

    // Rediriger vers la même page sans le paramètre lang
    $params = $_GET;
    unset($params['lang']);
    $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
    $redirect = $baseUrl . (!empty($params) ? '?' . http_build_query($params) : '');
    header('Location: ' . $redirect);
    exit;
}

// Déterminer la langue active
if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $_allowedLangs, true)) {
    $lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $_allowedLangs, true)) {
    $lang = $_COOKIE['lang'];
    $_SESSION['lang'] = $lang;
} else {
    $lang = 'fr';
}

// Charger les traductions dans $t
$t = require __DIR__ . '/lang/' . $lang . '.php';

/**
 * Génère l'URL de changement de langue en préservant les paramètres GET existants.
 */
function langSwitchUrl(string $targetLang): string {
    $params = $_GET;
    unset($params['lang']);
    $params['lang'] = $targetLang;
    return strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($params);
}
