<?php
/**
 * actions/dokumenti-actions.php — POST obrada za modul Dokumentacija
 *
 * Obrađuje kreiranje, izmenu i arhiviranje dokumenata.
 */

// ─── Kreiranje novog dokumenta ─────────────────────────────────────────────
if ($page === 'dokument-novi') {
    $naziv              = trim($_POST['naziv'] ?? '');
    $predmetId          = (int)($_POST['predmet_id'] ?? 0);
    $tipDokumenta       = trim($_POST['tip_dokumenta'] ?? '');
    $opis               = trim($_POST['opis'] ?? '');
    $nivoPoverljivosti  = $_POST['nivo_poverljivosti'] ?? 'INTERNO';
    $dozvoljeniNivoi    = ['JAVNO', 'INTERNO', 'POVERLJIVO', 'STROGO_POVERLJIVO'];
    if (!in_array($nivoPoverljivosti, $dozvoljeniNivoi, true)) {
        $nivoPoverljivosti = 'INTERNO';
    }

    // Validacija
    if (empty($naziv) || $predmetId < 1) {
        flashError('Popunite obavezna polja: naziv i predmet.');
        header('Location: ?page=dokument-novi');
        exit;
    }

    $autorId = $_SESSION['user_id'];

    // ─── Obrada upload-a fajla ────────────────────────────────────────────
    $putanja = 'nema-fajla';
    if (!empty($_FILES['fajl']['name']) && $_FILES['fajl']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fajl = $_FILES['fajl'];

        // Provera greske pri uploadu
        if ($fajl['error'] !== UPLOAD_ERR_OK) {
            flashError('Greška pri uploadu fajla (kod: ' . $fajl['error'] . ').');
            header('Location: ?page=dokument-novi');
            exit;
        }

        // Provera velicine
        if ($fajl['size'] > MAX_VELICINA_FAJLA) {
            flashError('Fajl je prevelik. Maksimalna dozvoljena veličina je 10MB.');
            header('Location: ?page=dokument-novi');
            exit;
        }

        // Provera tipa fajla po ekstenziji
        $ext = strtolower(pathinfo($fajl['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, DOZVOLJENI_TIPOVI, true)) {
            flashError('Nedozvoljen tip fajla. Dozvoljeni: ' . implode(', ', DOZVOLJENI_TIPOVI) . '.');
            header('Location: ?page=dokument-novi');
            exit;
        }

        // Generisanje unikatnog naziva fajla
        $unikatnoIme = sprintf('DOK-%d-%d-%s.%s', $predmetId, time(), bin2hex(random_bytes(4)), $ext);
        $odrediste = UPLOAD_DIR . $unikatnoIme;

        if (!move_uploaded_file($fajl['tmp_name'], $odrediste)) {
            flashError('Greška pri čuvanju fajla na server.');
            header('Location: ?page=dokument-novi');
            exit;
        }

        $putanja = $unikatnoIme;
    }

    $conn->begin_transaction();
    try {
        // INSERT dokument
        $stmt = $conn->prepare("INSERT INTO dokument (naziv, putanja, verzija, status, nivo_poverljivosti, datum_kreiranja, predmet_id, autor_id) VALUES (?, ?, 1, 'AKTIVAN', ?, NOW(), ?, ?)");
        $stmt->bind_param('sssii', $naziv, $putanja, $nivoPoverljivosti, $predmetId, $autorId);
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

        // Ekstrakcija teksta iz PDF-a
        $sadrzajTekst = '';
        if ($putanja !== 'nema-fajla') {
            $sadrzajTekst = ekstrahujTekstIzPdf(UPLOAD_DIR . $putanja);
        }

        if ($sadrzajTekst !== '') {
            $stmtTekst = $conn->prepare("UPDATE dokument SET sadrzaj_tekst = ? WHERE id = ?");
            $stmtTekst->bind_param('si', $sadrzajTekst, $dokumentId);
            $stmtTekst->execute();
            $stmtTekst->close();
        }

        // Dodavanje predloženih tagova koje je korisnik odobrio
        $predlozeniTagovi = $_POST['predlozeni_tagovi'] ?? [];
        if (!empty($predlozeniTagovi)) {
            $stmtTag = $conn->prepare("INSERT IGNORE INTO dokument_tag (dokument_id, tag_id) VALUES (?, ?)");
            foreach ($predlozeniTagovi as $tagId) {
                $tagId = (int)$tagId;
                if ($tagId > 0) {
                    $stmtTag->bind_param('ii', $dokumentId, $tagId);
                    $stmtTag->execute();
                }
            }
            $stmtTag->close();
        }

        // Poluautomatsko dodavanje tagova na osnovu sadržaja PDF-a
        if ($sadrzajTekst !== '') {
            $tagNaziviIzPdf = predloziTagovePoOpisu($sadrzajTekst);

            if (!empty($tagNaziviIzPdf)) {
                $placeholders = implode(',', array_fill(0, count($tagNaziviIzPdf), '?'));
                $stmtTagFind = $conn->prepare("SELECT id FROM tag WHERE naziv IN ($placeholders)");
                $stmtTagFind->bind_param(str_repeat('s', count($tagNaziviIzPdf)), ...$tagNaziviIzPdf);
                $stmtTagFind->execute();
                $tagResult = $stmtTagFind->get_result();

                $stmtTagInsert = $conn->prepare("INSERT IGNORE INTO dokument_tag (dokument_id, tag_id) VALUES (?, ?)");
                while ($tagRow = $tagResult->fetch_assoc()) {
                    $stmtTagInsert->bind_param('ii', $dokumentId, $tagRow['id']);
                    $stmtTagInsert->execute();
                }
                $stmtTagInsert->close();
                $stmtTagFind->close();
            }
        }

        $conn->commit();

        if (!empty($sadrzajTekst) && !empty($tagNaziviIzPdf)) {
            flashSuccess('Dokument uspešno kreiran. Sistem je automatski predložio tagove na osnovu sadržaja dokumenta — proverite ih na stranici detalja.');
        } else {
            flashSuccess('Dokument uspešno kreiran.');
        }
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
    $dokumentId         = (int)($_GET['id'] ?? 0);
    $naziv              = trim($_POST['naziv'] ?? '');
    $tipDokumenta       = trim($_POST['tip_dokumenta'] ?? '');
    $opis               = trim($_POST['opis'] ?? '');
    $razlogIzmene       = trim($_POST['razlog_izmene'] ?? '');
    $nivoPoverljivosti  = $_POST['nivo_poverljivosti'] ?? 'INTERNO';
    $dozvoljeniNivoi    = ['JAVNO', 'INTERNO', 'POVERLJIVO', 'STROGO_POVERLJIVO'];
    if (!in_array($nivoPoverljivosti, $dozvoljeniNivoi, true)) {
        $nivoPoverljivosti = 'INTERNO';
    }

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

    // Provera prava za izmenu: admin, autor, ili korisnik sa nivoom IZMENA
    if ($_SESSION['uloga'] !== 'ADMINISTRATOR' && $dokument['autor_id'] !== $_SESSION['user_id']) {
        $stmtPravo = $conn->prepare("SELECT nivo_pristupa FROM pravo_pristupa WHERE dokument_id = ? AND korisnik_id = ?");
        $stmtPravo->bind_param('ii', $dokumentId, $_SESSION['user_id']);
        $stmtPravo->execute();
        $pravo = $stmtPravo->get_result()->fetch_assoc();
        $stmtPravo->close();

        if (!$pravo || $pravo['nivo_pristupa'] !== 'IZMENA') {
            flashError('Nemate pravo izmene ovog dokumenta.');
            header("Location: ?page=dokument-detalji&id={$dokumentId}");
            exit;
        }
    }

    if (empty($naziv)) {
        flashError('Naziv je obavezan.');
        header("Location: ?page=dokument-izmeni&id={$dokumentId}");
        exit;
    }

    // ─── Obrada upload-a novog fajla (opciono) ───────────────────────────
    $novaPutanja = $dokument['putanja'];
    if (!empty($_FILES['fajl']['name']) && $_FILES['fajl']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fajl = $_FILES['fajl'];

        if ($fajl['error'] !== UPLOAD_ERR_OK) {
            flashError('Greška pri uploadu fajla (kod: ' . $fajl['error'] . ').');
            header("Location: ?page=dokument-izmeni&id={$dokumentId}");
            exit;
        }

        if ($fajl['size'] > MAX_VELICINA_FAJLA) {
            flashError('Fajl je prevelik. Maksimalna dozvoljena veličina je 10MB.');
            header("Location: ?page=dokument-izmeni&id={$dokumentId}");
            exit;
        }

        $ext = strtolower(pathinfo($fajl['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, DOZVOLJENI_TIPOVI, true)) {
            flashError('Nedozvoljen tip fajla. Dozvoljeni: ' . implode(', ', DOZVOLJENI_TIPOVI) . '.');
            header("Location: ?page=dokument-izmeni&id={$dokumentId}");
            exit;
        }

        $unikatnoIme = sprintf('DOK-%d-%d-%s.%s', $dokument['predmet_id'], time(), bin2hex(random_bytes(4)), $ext);
        $odrediste = UPLOAD_DIR . $unikatnoIme;

        if (!move_uploaded_file($fajl['tmp_name'], $odrediste)) {
            flashError('Greška pri čuvanju fajla na server.');
            header("Location: ?page=dokument-izmeni&id={$dokumentId}");
            exit;
        }

        $novaPutanja = $unikatnoIme;
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

        // UPDATE dokument (inkrementiraj verziju, azuriraj putanju)
        $novaVerzija = $dokument['verzija'] + 1;
        $stmt = $conn->prepare("UPDATE dokument SET naziv = ?, putanja = ?, verzija = ?, nivo_poverljivosti = ? WHERE id = ?");
        $stmt->bind_param('ssisi', $naziv, $novaPutanja, $novaVerzija, $nivoPoverljivosti, $dokumentId);
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

        // Ako je uploadovan novi fajl, reekstrahuj tekst i dodaj tagove
        if ($novaPutanja !== $dokument['putanja']) {
            $sadrzajTekst = ekstrahujTekstIzPdf(UPLOAD_DIR . $novaPutanja);
            $stmtTekst = $conn->prepare("UPDATE dokument SET sadrzaj_tekst = ? WHERE id = ?");
            $stmtTekst->bind_param('si', $sadrzajTekst, $dokumentId);
            $stmtTekst->execute();
            $stmtTekst->close();

            if ($sadrzajTekst !== '') {
                $tagNaziviIzPdf = predloziTagovePoOpisu($sadrzajTekst);

                if (!empty($tagNaziviIzPdf)) {
                    $placeholders = implode(',', array_fill(0, count($tagNaziviIzPdf), '?'));
                    $stmtTagFind = $conn->prepare("SELECT id FROM tag WHERE naziv IN ($placeholders)");
                    $stmtTagFind->bind_param(str_repeat('s', count($tagNaziviIzPdf)), ...$tagNaziviIzPdf);
                    $stmtTagFind->execute();
                    $tagResult = $stmtTagFind->get_result();

                    $stmtTagInsert = $conn->prepare("INSERT IGNORE INTO dokument_tag (dokument_id, tag_id) VALUES (?, ?)");
                    while ($tagRow = $tagResult->fetch_assoc()) {
                        $stmtTagInsert->bind_param('ii', $dokumentId, $tagRow['id']);
                        $stmtTagInsert->execute();
                    }
                    $stmtTagInsert->close();
                    $stmtTagFind->close();
                }
            }
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

// ─── Dodavanje prava pristupa ──────────────────────────────────────────────
if ($page === 'dokument-detalji' && $action === 'dodaj-pristup') {
    $dokumentId  = (int)($_GET['id'] ?? 0);
    $korisnikId  = (int)($_POST['korisnik_id'] ?? 0);
    $nivoPristupa = $_POST['nivo_pristupa'] ?? 'CITANJE';

    if (!in_array($nivoPristupa, ['CITANJE', 'IZMENA'], true)) {
        $nivoPristupa = 'CITANJE';
    }

    // Provera da dokument postoji i da korisnik ima pravo da upravlja pristupom
    $stmt = $conn->prepare("SELECT autor_id FROM dokument WHERE id = ?");
    $stmt->bind_param('i', $dokumentId);
    $stmt->execute();
    $dok = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dok || ($dok['autor_id'] !== $_SESSION['user_id'] && $_SESSION['uloga'] !== 'ADMINISTRATOR')) {
        flashError('Nemate pravo da upravljate pristupom ovog dokumenta.');
        header("Location: ?page=dokument-detalji&id={$dokumentId}");
        exit;
    }

    if ($korisnikId < 1) {
        flashError('Izaberite korisnika.');
        header("Location: ?page=dokument-detalji&id={$dokumentId}");
        exit;
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO pravo_pristupa (nivo_pristupa, korisnik_id, dokument_id) VALUES (?, ?, ?)");
    $stmt->bind_param('sii', $nivoPristupa, $korisnikId, $dokumentId);
    $stmt->execute();
    $stmt->close();

    // Log dodele pristupa
    $napomena = 'Dodeljen nivo: ' . $nivoPristupa;
    $stmt = $conn->prepare("INSERT INTO log_pristupa (akcija, dokument_id, korisnik_id, izvrsio_id, napomena) VALUES ('DODELA', ?, ?, ?, ?)");
    $stmt->bind_param('iiis', $dokumentId, $korisnikId, $_SESSION['user_id'], $napomena);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Pristup uspešno dodeljen.');
    header("Location: ?page=dokument-detalji&id={$dokumentId}");
    exit;
}

// ─── Uklanjanje prava pristupa ────────────────────────────────────────────
if ($page === 'dokument-detalji' && $action === 'ukloni-pristup') {
    $dokumentId = (int)($_GET['id'] ?? 0);
    $pravoId    = (int)($_POST['pravo_id'] ?? 0);

    $stmt = $conn->prepare("SELECT autor_id FROM dokument WHERE id = ?");
    $stmt->bind_param('i', $dokumentId);
    $stmt->execute();
    $dok = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dok || ($dok['autor_id'] !== $_SESSION['user_id'] && $_SESSION['uloga'] !== 'ADMINISTRATOR')) {
        flashError('Nemate pravo da upravljate pristupom ovog dokumenta.');
        header("Location: ?page=dokument-detalji&id={$dokumentId}");
        exit;
    }

    // Pronađi korisnika pre brisanja (za log)
    $stmt = $conn->prepare("SELECT korisnik_id FROM pravo_pristupa WHERE id = ? AND dokument_id = ?");
    $stmt->bind_param('ii', $pravoId, $dokumentId);
    $stmt->execute();
    $pravo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($pravo) {
        $stmt = $conn->prepare("DELETE FROM pravo_pristupa WHERE id = ? AND dokument_id = ?");
        $stmt->bind_param('ii', $pravoId, $dokumentId);
        $stmt->execute();
        $stmt->close();

        // Log uklanjanja pristupa
        $stmt = $conn->prepare("INSERT INTO log_pristupa (akcija, dokument_id, korisnik_id, izvrsio_id) VALUES ('UKLANJANJE', ?, ?, ?)");
        $stmt->bind_param('iii', $dokumentId, $pravo['korisnik_id'], $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }

    flashSuccess('Pristup uspešno uklonjen.');
    header("Location: ?page=dokument-detalji&id={$dokumentId}");
    exit;
}

// ─── Preuzimanje (download) fajla dokumenta ───────────────────────────────
if ($page === 'dokument-detalji' && $action === 'download') {
    $dokumentId = (int)($_GET['id'] ?? 0);

    $stmt = $conn->prepare("SELECT naziv, putanja, autor_id, nivo_poverljivosti FROM dokument WHERE id = ?");
    $stmt->bind_param('i', $dokumentId);
    $stmt->execute();
    $dokument = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dokument) {
        flashError('Dokument nije pronađen.');
        header('Location: ?page=dokumentacija');
        exit;
    }

    // Provera prava pristupa za download
    if ($_SESSION['uloga'] !== 'ADMINISTRATOR'
        && $dokument['nivo_poverljivosti'] !== 'JAVNO'
        && $dokument['autor_id'] !== $_SESSION['user_id']
    ) {
        $stmtPristup = $conn->prepare("SELECT 1 FROM pravo_pristupa WHERE dokument_id = ? AND korisnik_id = ?");
        $stmtPristup->bind_param('ii', $dokumentId, $_SESSION['user_id']);
        $stmtPristup->execute();
        $imaPristup = $stmtPristup->get_result()->num_rows > 0;
        $stmtPristup->close();

        if (!$imaPristup) {
            flashError('Nemate pravo da preuzmete ovaj dokument.');
            header("Location: ?page=dokument-detalji&id={$dokumentId}");
            exit;
        }
    }

    $putanjaFajla = $dokument['putanja'];
    $fizickaPutanja = UPLOAD_DIR . $putanjaFajla;

    if (in_array($putanjaFajla, ['simulirano', 'nema-fajla'], true) || !file_exists($fizickaPutanja)) {
        flashError('Fajl nije dostupan za preuzimanje.');
        header("Location: ?page=dokument-detalji&id={$dokumentId}");
        exit;
    }

    // Odredi MIME tip na osnovu ekstenzije
    $ext = strtolower(pathinfo($putanjaFajla, PATHINFO_EXTENSION));
    $mimeTypovi = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];
    $mime = $mimeTypovi[$ext] ?? 'application/octet-stream';

    // Ime fajla za preuzimanje: naziv dokumenta + originalna ekstenzija
    $downloadIme = preg_replace('/[^a-zA-Z0-9_\-\.\x{0400}-\x{04FF}]/u', '_', $dokument['naziv']) . '.' . $ext;

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $downloadIme . '"');
    header('Content-Length: ' . filesize($fizickaPutanja));
    readfile($fizickaPutanja);
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
