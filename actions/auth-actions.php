<?php
/**
 * actions/auth-actions.php — Obrada prijave korisnika
 *
 * Prima POST sa email i lozinka, proverava u bazi,
 * postavlja sesiju i preusmerava na dashboard.
 */

$email   = trim($_POST['email'] ?? '');
$lozinka = $_POST['lozinka'] ?? '';

// ─── Validacija unosa ───────────────────────────────────────────────────────
if (empty($email) || empty($lozinka)) {
    flashError('Unesite email i lozinku.');
    header('Location: ?page=login');
    exit;
}

// ─── Pretraga korisnika u bazi (prepared statement) ─────────────────────────
$stmt = $conn->prepare("SELECT id, ime, prezime, email, uloga, lozinka_hash FROM korisnik WHERE email = ? AND aktivan = 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    flashError('Pogrešan email ili lozinka.');
    header('Location: ?page=login');
    exit;
}

$korisnik = $result->fetch_assoc();
$stmt->close();

// ─── Provera lozinke ────────────────────────────────────────────────────────
if (!password_verify($lozinka, $korisnik['lozinka_hash'])) {
    flashError('Pogrešan email ili lozinka.');
    header('Location: ?page=login');
    exit;
}

// ─── Postavljanje sesije ────────────────────────────────────────────────────
$_SESSION['user_id'] = $korisnik['id'];
$_SESSION['ime']     = $korisnik['ime'];
$_SESSION['prezime'] = $korisnik['prezime'];
$_SESSION['uloga']   = $korisnik['uloga'];
$_SESSION['email']   = $korisnik['email'];

// ─── Preusmeravanje na dashboard ────────────────────────────────────────────
header('Location: ?page=dashboard');
exit;
