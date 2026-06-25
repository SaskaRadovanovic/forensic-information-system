<?php
/**
 * actions/korisnici-actions.php — Obrada kreiranja novog korisnika
 *
 * Prima POST sa ime, prezime, email, lozinka, uloga.
 * Samo ADMINISTRATOR ima pristup.
 */

requireRole('ADMINISTRATOR');

$ime     = trim($_POST['ime'] ?? '');
$prezime = trim($_POST['prezime'] ?? '');
$email   = trim($_POST['email'] ?? '');
$lozinka = $_POST['lozinka'] ?? '';
$uloga   = $_POST['uloga'] ?? '';

// ─── Validacija unosa ──────────────────────────────────────────────────────
$dozvoljeneUloge = ['ADMINISTRATOR', 'ISTRAZITELJ', 'TEHNICAR', 'VESTAK'];

if (empty($ime) || empty($prezime) || empty($email) || empty($lozinka) || empty($uloga)) {
    flashError('Sva polja su obavezna.');
    header('Location: ?page=korisnik-novi');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flashError('Email adresa nije validna.');
    header('Location: ?page=korisnik-novi');
    exit;
}

if (mb_strlen($lozinka) < 6) {
    flashError('Lozinka mora imati najmanje 6 karaktera.');
    header('Location: ?page=korisnik-novi');
    exit;
}

if (!in_array($uloga, $dozvoljeneUloge, true)) {
    flashError('Nevalidna uloga.');
    header('Location: ?page=korisnik-novi');
    exit;
}

// ─── Provera da li email već postoji ───────────────────────────────────────
$stmt = $conn->prepare("SELECT id FROM korisnik WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $stmt->close();
    flashError('Korisnik sa ovom email adresom već postoji.');
    header('Location: ?page=korisnik-novi');
    exit;
}
$stmt->close();

// ─── Hashovanje lozinke i unos u bazu ──────────────────────────────────────
$lozinkaHash = password_hash($lozinka, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO korisnik (ime, prezime, email, lozinka_hash, uloga) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('sssss', $ime, $prezime, $email, $lozinkaHash, $uloga);

if ($stmt->execute()) {
    $stmt->close();
    flashSuccess("Korisnik {$ime} {$prezime} je uspešno kreiran.");
    header('Location: ?page=dashboard');
    exit;
}

$stmt->close();
flashError('Greška pri kreiranju korisnika.');
header('Location: ?page=korisnik-novi');
exit;
