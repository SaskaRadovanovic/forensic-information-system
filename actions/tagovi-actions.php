<?php
/**
 * actions/tagovi-actions.php — POST obrada za modul Tagovi
 *
 * Obrađuje kreiranje i brisanje tagova.
 */

// ─── Kreiranje novog taga ──────────────────────────────────────────────────
if ($page === 'tag-novi') {
    requireRole('ADMINISTRATOR');

    $naziv = trim($_POST['naziv'] ?? '');
    $boja  = trim($_POST['boja'] ?? '#FACC15');

    if (empty($naziv)) {
        flashError('Naziv taga je obavezan.');
        header('Location: ?page=tag-novi');
        exit;
    }

    // Provera jedinstvenosti
    $stmt = $conn->prepare("SELECT id FROM tag WHERE naziv = ?");
    $stmt->bind_param('s', $naziv);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        flashError('Tag sa tim nazivom već postoji.');
        header('Location: ?page=tag-novi');
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO tag (naziv, boja) VALUES (?, ?)");
    $stmt->bind_param('ss', $naziv, $boja);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Tag uspešno kreiran.');
    header('Location: ?page=tagovi');
    exit;
}

// ─── Brisanje taga ─────────────────────────────────────────────────────────
if ($page === 'tagovi' && $action === 'obrisi') {
    requireRole('ADMINISTRATOR');

    $tagId = (int)($_POST['tag_id'] ?? 0);

    if ($tagId < 1) {
        flashError('Nevalidan tag.');
        header('Location: ?page=tagovi');
        exit;
    }

    // CASCADE briše dokument_tag automatski
    $stmt = $conn->prepare("DELETE FROM tag WHERE id = ?");
    $stmt->bind_param('i', $tagId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Tag uspešno obrisan.');
    header('Location: ?page=tagovi');
    exit;
}
