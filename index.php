<?php
/**
 * index.php — Front controller
 *
 * Sve zahteve obrađuje jedan fajl. Rutiranje preko GET parametra ?page=X.
 * POST zahtevi se prosleđuju odgovarajućem action fajlu.
 */

header('Content-Type: text/html; charset=UTF-8');
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// ─── Čitanje parametara ─────────────────────────────────────────────────────
$page   = $_GET['page']   ?? 'dashboard';
$action = $_GET['action'] ?? null;

// ─── Whitelist dozvoljenih stranica (zaštita od path traversal) ─────────────
$dozvoljeneStranice = [
    'login', 'logout', 'dashboard',
    'predmeti', 'predmet-detalji', 'predmet-novi', 'predmet-izmeni',
    'dokazi', 'dokaz-detalji', 'dokaz-novi', 'dokaz-izmeni',
    'dokazi-zahtevi',
    'dokumentacija', 'dokument-detalji', 'dokument-novi', 'dokument-izmeni',
    'tagovi', 'tag-novi',
    'analize', 'analiza-detalji', 'analiza-nova', 'analiza-izmeni',
    'analiza-dodela', 'analiza-rezultat',
    'obavestenja',
    'izvestaji',
    'izvestaj-analize',
    'analitika',
    'istorija-predmeta',
    'korisnik-novi',
];

// Ako stranica nije u whitelist-u, prikaži 404
if (!in_array($page, $dozvoljeneStranice, true)) {
    http_response_code(404);
    die('<h1>404 — Stranica nije pronađena</h1><p><a href="?page=dashboard">Nazad na početnu</a></p>');
}

// ─── Logout ─────────────────────────────────────────────────────────────────
if ($page === 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

// ─── Login — poseban tok (fullscreen, bez layouta) ─────────────────────────
if ($page === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require __DIR__ . '/actions/auth-actions.php';
    } else {
        require __DIR__ . '/pages/login.php';
    }
    exit;
}

// ─── Provera prijave za sve ostale stranice ─────────────────────────────────
requireLogin();

// ─── Mapiranje stranica na action fajlove ───────────────────────────────────
$actionMapa = [
    'dokaz-novi'       => 'actions/dokazi-actions.php',
    'dokaz-izmeni'     => 'actions/dokazi-actions.php',
    'dokaz-detalji'    => 'actions/dokazi-actions.php',
    'dokazi-zahtevi'   => 'actions/dokazi-actions.php',
    'dokument-novi'    => 'actions/dokumenti-actions.php',
    'dokument-izmeni'  => 'actions/dokumenti-actions.php',
    'dokument-detalji' => 'actions/dokumenti-actions.php',
    'tag-novi'         => 'actions/tagovi-actions.php',
    'tagovi'           => 'actions/tagovi-actions.php',
    'predmet-novi'     => 'actions/predmeti-actions.php',
    'predmet-izmeni'   => 'actions/predmeti-actions.php',
    'predmet-detalji'  => 'actions/predmeti-actions.php',
    'predmeti'         => 'actions/predmeti-actions.php',
    'analiza-nova'     => 'actions/analize-actions.php',
    'analiza-izmeni'   => 'actions/analize-actions.php',
    'analiza-detalji'  => 'actions/analize-actions.php',
    'analiza-dodela'   => 'actions/analize-actions.php',
    'analiza-rezultat' => 'actions/analize-actions.php',
    'obavestenja'      => 'actions/obavestenja-actions.php',
    'korisnik-novi'    => 'actions/korisnici-actions.php',
];

// ─── POST obrada — prosleđivanje action fajlu ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($actionMapa[$page])) {
        require __DIR__ . '/' . $actionMapa[$page];
    }
    exit;
}

// ─── PDF izveštaji — GET zahtev bez layouta (direktan download) ─────────────
if (in_array($action, ['izvestaj-dokaz', 'zbirni-izvestaj'])) {
    require __DIR__ . '/actions/izvestaji-actions.php';
    exit;
}

if ($action === 'izvestaj-analize-pdf') {
    require __DIR__ . '/actions/izvestaj-analize-export.php';
    exit;
}

// ─── GET prikaz — layout + stranica ─────────────────────────────────────────
require __DIR__ . '/layout/header.php';
require __DIR__ . "/pages/{$page}.php";
require __DIR__ . '/layout/footer.php';

ob_end_flush();
