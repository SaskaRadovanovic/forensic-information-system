<?php
/**
 * config.php — Konfiguracija aplikacije
 *
 * Pokretanje sesije, konekcija ka bazi podataka,
 * i pomoćne funkcije za autentifikaciju.
 */

// ─── Pokretanje sesije ─────────────────────────────────────────────────────
session_start();

// ─── Parametri baze podataka ────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'forenzis');

// ─── Konekcija ka MySQL bazi ────────────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Provera konekcije
if ($conn->connect_error) {
    die('Greška pri konekciji na bazu: ' . $conn->connect_error);
}

// Postavljanje charset-a na utf8mb4 za podršku srpske latinice
$conn->set_charset('utf8mb4');

// ─── Pomoćne funkcije za autentifikaciju ────────────────────────────────────

/**
 * requireLogin — Proverava da li je korisnik ulogovan.
 * Ako nije, preusmerava na stranicu za prijavu.
 */
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ?page=login');
        exit;
    }
}

/**
 * requireRole — Proverava da li ulogovani korisnik ima jednu od dozvoljenih uloga.
 * Ako nema, prikazuje grešku 403.
 *
 * @param string ...$roles Lista dozvoljenih uloga (npr. 'ADMINISTRATOR', 'TEHNICAR')
 */
function requireRole(string ...$roles): void
{
    if (!in_array($_SESSION['uloga'] ?? '', $roles, true)) {
        http_response_code(403);
        die('<h1>403 — Nemate pristup ovoj stranici</h1><p><a href="?page=dashboard">Nazad na početnu</a></p>');
    }
}

/**
 * currentUser — Vraća podatke o trenutno ulogovanom korisniku iz sesije.
 *
 * @return array Asocijativni niz sa id, ime, prezime, uloga, email
 */
function currentUser(): array
{
    return [
        'id'      => $_SESSION['user_id'] ?? 0,
        'ime'     => $_SESSION['ime'] ?? '',
        'prezime' => $_SESSION['prezime'] ?? '',
        'uloga'   => $_SESSION['uloga'] ?? '',
        'email'   => $_SESSION['email'] ?? '',
    ];
}
