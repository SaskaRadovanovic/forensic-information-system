<?php
/**
 * actions/dokumenti-actions.php — POST obrada za modul Dokumentacija
 *
 * Obrađuje kreiranje, izmenu i arhiviranje dokumenata.
 */

// ─── Kreiranje novog dokumenta ─────────────────────────────────────────────
if ($page === 'dokument-novi') {
    $naziv       = trim($_POST['naziv'] ?? '');
    $predmetId   = (int)($_POST['predmet_id'] ?? 0);
    $tipDokumenta = trim($_POST['tip_dokumenta'] ?? '');
    $opis        = trim($_POST['opis'] ?? '');

    // Validacija
    if (empty($naziv) || $predmetId < 1) {
        flashError('Popunite obavezna polja: naziv i predmet.');
        header('Location: ?page=dokument-novi');
        exit;
    }

    $autorId = $_SESSION['user_id'];

    $conn->begin_transaction();
    try {
        // INSERT dokument
        $stmt = $conn->prepare("INSERT INTO dokument (naziv, putanja, verzija, status, datum_kreiranja, predmet_id, autor_id) VALUES (?, 'simulirano', 1, 'AKTIVAN', NOW(), ?, ?)");
        $stmt->bind_param('sii', $naziv, $predmetId, $autorId);
        $stmt->execute();
        $dokumentId = $conn->insert_id;
        $stmt->close();

        // INSERT metapodaci
        if ($tipDokumenta !== '') {
            $stmt = $conn->prepare("INSERT INTO metapodatak (kljuc, vrednost, dokument_id) VALUES ('tipDokumenta', ?, ?)");
            $stmt->bind_param('si', $tipDokumenta, $dokumentId);
            $stmt->execute();
            $stmt->close();
        }
        if ($opis !== '') {
            $stmt = $conn->prepare("INSERT INTO metapodatak (kljuc, vrednost, dokument_id) VALUES ('opis', ?, ?)");
            $stmt->bind_param('si', $opis, $dokumentId);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        flashSuccess('Dokument uspešno kreiran.');
        header("Location: ?page=dokument-detalji&id={$dokumentId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška pri kreiranju dokumenta: ' . $e->getMessage());
        header('Location: ?page=dokument-novi');
        exit;
    }
}

// ─── Izmena dokumenta ──────────────────────────────────────────────────────
if ($page === 'dokument-izmeni') {
    $dokumentId    = (int)($_GET['id'] ?? 0);
    $naziv         = trim($_POST['naziv'] ?? '');
    $tipDokumenta  = trim($_POST['tip_dokumenta'] ?? '');
    $opis          = trim($_POST['opis'] ?? '');
    $razlogIzmene  = trim($_POST['razlog_izmene'] ?? '');

    // Provera da dokument postoji i nije arhiviran
    $stmt = $conn->prepare("SELECT * FROM dokument WHERE id = ?");
    $stmt->bind_param('i', $dokumentId);
    $stmt->execute();
    $dokument = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dokument) {
        flashError('Dokument nije pronađen.');
        header('Location: ?page=dokumentacija');
        exit;
    }
    if ($dokument['status'] === 'ARHIVIRAN') {
        flashError('Arhiviran dokument se ne može menjati.');
        header("Location: ?page=dokument-detalji&id={$dokumentId}");
        exit;
    }

    if (empty($naziv)) {
        flashError('Naziv je obavezan.');
        header("Location: ?page=dokument-izmeni&id={$dokumentId}");
        exit;
    }

    $conn->begin_transaction();
    try {
        // Sačuvaj staru verziju u arhivu
        $razlogDb = $razlogIzmene ?: null;
        $stmt = $conn->prepare("INSERT INTO dokument_arhiva (putanja_stara_verzija, verzija, razlog_izmene, datum_arhiviranja, dokument_id, sacuvao_id) VALUES (?, ?, ?, NOW(), ?, ?)");
        $stmt->bind_param('sisii', $dokument['putanja'], $dokument['verzija'], $razlogDb, $dokumentId, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();

        // Obriši stare metapodatke
        $stmt = $conn->prepare("DELETE FROM metapodatak WHERE dokument_id = ?");
        $stmt->bind_param('i', $dokumentId);
        $stmt->execute();
        $stmt->close();

        // UPDATE dokument (inkrementiraj verziju)
        $novaVerzija = $dokument['verzija'] + 1;
        $stmt = $conn->prepare("UPDATE dokument SET naziv = ?, verzija = ? WHERE id = ?");
        $stmt->bind_param('sii', $naziv, $novaVerzija, $dokumentId);
        $stmt->execute();
        $stmt->close();

        // INSERT novi metapodaci
        if ($tipDokumenta !== '') {
            $stmt = $conn->prepare("INSERT INTO metapodatak (kljuc, vrednost, dokument_id) VALUES ('tipDokumenta', ?, ?)");
            $stmt->bind_param('si', $tipDokumenta, $dokumentId);
            $stmt->execute();
            $stmt->close();
        }
        if ($opis !== '') {
            $stmt = $conn->prepare("INSERT INTO metapodatak (kljuc, vrednost, dokument_id) VALUES ('opis', ?, ?)");
            $stmt->bind_param('si', $opis, $dokumentId);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        flashSuccess('Dokument uspešno izmenjen (verzija ' . $novaVerzija . ').');
        header("Location: ?page=dokument-detalji&id={$dokumentId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška pri izmeni: ' . $e->getMessage());
        header("Location: ?page=dokument-izmeni&id={$dokumentId}");
        exit;
    }
}

// ─── Arhiviranje dokumenta ─────────────────────────────────────────────────
if ($page === 'dokument-detalji' && $action === 'arhiviraj') {
    requireRole('ADMINISTRATOR');

    $dokumentId = (int)($_GET['id'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM dokument WHERE id = ?");
    $stmt->bind_param('i', $dokumentId);
    $stmt->execute();
    $dokument = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dokument || $dokument['status'] === 'ARHIVIRAN') {
        flashError('Dokument je već arhiviran ili ne postoji.');
        header("Location: ?page=dokument-detalji&id={$dokumentId}");
        exit;
    }

    // Sačuvaj u arhivu pre arhiviranja
    $stmt = $conn->prepare("INSERT INTO dokument_arhiva (putanja_stara_verzija, verzija, razlog_izmene, datum_arhiviranja, dokument_id, sacuvao_id) VALUES (?, ?, 'Arhiviranje dokumenta', NOW(), ?, ?)");
    $stmt->bind_param('siii', $dokument['putanja'], $dokument['verzija'], $dokumentId, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE dokument SET status = 'ARHIVIRAN' WHERE id = ?");
    $stmt->bind_param('i', $dokumentId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Dokument uspešno arhiviran.');
    header("Location: ?page=dokument-detalji&id={$dokumentId}");
    exit;
}

// ─── Upravljanje tagovima na dokumentu ─────────────────────────────────────
if ($page === 'dokument-detalji' && $action === 'toggle-tag') {
    $dokumentId = (int)($_GET['id'] ?? 0);
    $tagId      = (int)($_POST['tag_id'] ?? 0);

    if ($dokumentId < 1 || $tagId < 1) {
        flashError('Nevalidni podaci.');
        header("Location: ?page=dokument-detalji&id={$dokumentId}");
        exit;
    }

    // Proveri da li tag već postoji na dokumentu
    $stmt = $conn->prepare("SELECT 1 FROM dokument_tag WHERE dokument_id = ? AND tag_id = ?");
    $stmt->bind_param('ii', $dokumentId, $tagId);
    $stmt->execute();
    $postoji = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($postoji) {
        // Ukloni tag
        $stmt = $conn->prepare("DELETE FROM dokument_tag WHERE dokument_id = ? AND tag_id = ?");
        $stmt->bind_param('ii', $dokumentId, $tagId);
        $stmt->execute();
        $stmt->close();
        flashSuccess('Tag uklonjen sa dokumenta.');
    } else {
        // Dodaj tag
        $stmt = $conn->prepare("INSERT INTO dokument_tag (dokument_id, tag_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $dokumentId, $tagId);
        $stmt->execute();
        $stmt->close();
        flashSuccess('Tag dodat na dokument.');
    }

    header("Location: ?page=dokument-detalji&id={$dokumentId}");
    exit;
}
