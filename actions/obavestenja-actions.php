<?php
/**
 * actions/obavestenja-actions.php — POST obrada za modul Obaveštenja
 *
 * Dve akcije: označi jedno pročitano, označi sve pročitano.
 */

$userId = $_SESSION['user_id'];

// ─── Označi jedno obaveštenje kao pročitano ────────────────────────────────
if ($action === 'procitaj') {
    $obavestenjeId = (int)($_POST['obavestenje_id'] ?? 0);

    if ($obavestenjeId > 0) {
        // Ownership provera — korisnik može označiti samo svoja obaveštenja
        $stmt = $conn->prepare("UPDATE obavestenje SET procitano = 1 WHERE id = ? AND korisnik_id = ?");
        $stmt->bind_param('ii', $obavestenjeId, $userId);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: ?page=obavestenja');
    exit;
}

// ─── Označi sve obaveštenja kao pročitano ──────────────────────────────────
if ($action === 'procitaj-sve') {
    $stmt = $conn->prepare("UPDATE obavestenje SET procitano = 1 WHERE korisnik_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Sva obaveštenja označena kao pročitana.');
    header('Location: ?page=obavestenja');
    exit;
}
