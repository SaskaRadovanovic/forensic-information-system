<?php
/**
 * actions/analize-actions.php — POST obrada za modul Analize
 *
 * Obrađuje kreiranje, izmenu, brisanje, dodelu veštaka,
 * prihvatanje/odbijanje, unos rezultata i verifikaciju.
 */

// ─── Kreiranje zahteva za analizu ──────────────────────────────────────────
if ($page === 'analiza-nova') {
    requireRole('ISTRAZITELJ');

    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT id_korisnik FROM istrazitelj WHERE id_korisnik = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        flashError('Nemate istražitelj profil.');
        header('Location: ?page=analize');
        exit;
    }
    $stmt->close();

    $predmetId    = (int)($_POST['predmet_id'] ?? 0);
    $dokazId      = (int)($_POST['dokaz_id'] ?? 0);
    $tipAnalize   = $_POST['tip_analize'] ?? '';
    $vestakId     = (int)($_POST['vestak_id'] ?? 0);
    $opis         = trim($_POST['opis'] ?? '');
    $rok          = $_POST['rok'] ?? '';
    $datumPocetka = $_POST['datum_pocetka'] ?? '';
    $prag         = max(1, (int)($_POST['prag_upozorenja_dana'] ?? 3));

    if ($predmetId < 1 || $dokazId < 1 || empty($tipAnalize) || empty($opis) || empty($rok)) {
        flashError('Popunite sva obavezna polja.');
        header('Location: ?page=analiza-nova');
        exit;
    }

    if ($rok < date('Y-m-d')) {
        flashError('Rok mora biti danas ili u budućnosti.');
        header('Location: ?page=analiza-nova');
        exit;
    }

    // Predmet mora biti u fazi ANALIZA_DOKAZA i mora biti istražiteljev
    $stmtP = $conn->prepare("SELECT id FROM predmet WHERE id=? AND istrazitelj_id=? AND faza='ANALIZA_DOKAZA' AND status='AKTIVAN'");
    $stmtP->bind_param('ii', $predmetId, $userId);
    $stmtP->execute();
    if ($stmtP->get_result()->num_rows === 0) {
        flashError('Izabrani predmet nije u fazi Analiza dokaza ili vam nije dodeljen.');
        header('Location: ?page=analiza-nova');
        exit;
    }
    $stmtP->close();

    $opisDb         = $opis ?: null;
    $rokDb          = $rok;
    $datumPocetkaDb = $datumPocetka ?: null;
    $statusPocetak  = $vestakId > 0 ? 'DODELJEN' : 'KREIRAN';
    $vestakIdDb     = $vestakId > 0 ? $vestakId : null;

    $conn->begin_transaction();
    try {
        if ($vestakIdDb !== null) {
            $stmt = $conn->prepare("
                INSERT INTO zahtev_za_analizu
                  (opis, tip_analize, datum_kreiranja, datum_pocetka, rok, status,
                   prag_upozorenja_dana, istrazitelj_id, dokaz_id, predmet_id, vestak_id)
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssssiiiii',
                $opisDb, $tipAnalize, $datumPocetkaDb, $rokDb, $statusPocetak,
                $prag, $userId, $dokazId, $predmetId, $vestakIdDb
            );
        } else {
            $stmt = $conn->prepare("
                INSERT INTO zahtev_za_analizu
                  (opis, tip_analize, datum_kreiranja, datum_pocetka, rok, status,
                   prag_upozorenja_dana, istrazitelj_id, dokaz_id, predmet_id)
                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssssiiii',
                $opisDb, $tipAnalize, $datumPocetkaDb, $rokDb, $statusPocetak,
                $prag, $userId, $dokazId, $predmetId
            );
        }
        $stmt->execute();
        $zahtevId = $conn->insert_id;
        $stmt->close();

        // Istorija — KREIRAN
        $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES (NULL, 'KREIRAN', NOW(), 'Zahtev kreiran', ?, ?)");
        $stmt->bind_param('ii', $zahtevId, $userId);
        $stmt->execute();
        $stmt->close();

        // Ako je veštak odmah dodeljen
        if ($vestakId > 0) {
            // Istorija — DODELJEN
            $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES ('KREIRAN', 'DODELJEN', NOW(), 'Veštak dodeljen pri kreiranju', ?, ?)");
            $stmt->bind_param('ii', $zahtevId, $userId);
            $stmt->execute();
            $stmt->close();

            // Istorija dodele
            $napomenaDodele = 'Inicijalna dodela';
            $stmt = $conn->prepare("INSERT INTO istorija_dodele (datum_dodele, razlog_promene, zahtev_id, vestak_id, dodelio_id) VALUES (NOW(), ?, ?, ?, ?)");
            $stmt->bind_param('siii', $napomenaDodele, $zahtevId, $vestakId, $userId);
            $stmt->execute();
            $stmt->close();

            // Obaveštenje veštaku se šalje tek kada tehničar odobri predaju dokaza
        }

        // Zahtev za izdavanje dokaza tehničaru
        $stmtD = $conn->prepare("SELECT tehnicar_id FROM dokaz WHERE id = ?");
        $stmtD->bind_param('i', $dokazId);
        $stmtD->execute();
        $tehnicarId = $stmtD->get_result()->fetch_assoc()['tehnicar_id'];
        $stmtD->close();

        $razlogPredaje = 'Automatski zahtev — kreiran zahtev za analizu #' . $zahtevId;
        $stmt = $conn->prepare("INSERT INTO zahtev_za_dokaz (tip, razlog, status, dokaz_id, podnosilac_id) VALUES ('PREDAJA', ?, 'NA_CEKANJU', ?, ?)");
        $stmt->bind_param('sii', $razlogPredaje, $dokazId, $userId);
        $stmt->execute();
        $stmt->close();

        // Obaveštenje tehničaru
        $sadrzajTeh = 'Novi zahtev za izdavanje dokaza radi analize #' . $zahtevId;
        $stmt = $conn->prepare("INSERT INTO obavestenje (sadrzaj, tip, datum_vreme, korisnik_id, zahtev_id) VALUES (?, 'ZAHTEV_DOKAZ', NOW(), ?, ?)");
        $stmt->bind_param('sii', $sadrzajTeh, $tehnicarId, $zahtevId);
        $stmt->execute();
        $obavestenjeIdTeh = $conn->insert_id;
        $stmt->close();

        $conn->commit();
        pushNotifikacija((int)$tehnicarId, $sadrzajTeh, $obavestenjeIdTeh);
        flashSuccess('Zahtev za analizu uspešno kreiran.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška pri kreiranju: ' . $e->getMessage());
        header('Location: ?page=analiza-nova');
        exit;
    }
}

// ─── Izmena zahteva ────────────────────────────────────────────────────────
if ($page === 'analiza-izmeni') {
    requireRole('ISTRAZITELJ', 'ADMINISTRATOR');

    $zahtevId   = (int)($_GET['id'] ?? 0);
    $tipAnalize = $_POST['tip_analize'] ?? '';
    $opis       = trim($_POST['opis'] ?? '');
    $rok        = $_POST['rok'] ?? null;
    $prag       = max(1, (int)($_POST['prag_upozorenja_dana'] ?? 3));

    $stmt = $conn->prepare("SELECT * FROM zahtev_za_analizu WHERE id = ?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev || !in_array($zahtev['status'], ['KREIRAN', 'DODELJEN'])) {
        flashError('Zahtev ne postoji ili se ne može menjati u tekućem statusu.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }

    $conn->begin_transaction();
    try {
        $opisDb = $opis ?: null;
        $rokDb  = $rok ?: null;
        $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET tip_analize=?, opis=?, rok=?, prag_upozorenja_dana=? WHERE id=?");
        $stmt->bind_param('sssii', $tipAnalize, $opisDb, $rokDb, $prag, $zahtevId);
        $stmt->execute();
        $stmt->close();

        // Loguj promene
        $userId = $_SESSION['user_id'];
        $polja = ['tip_analize', 'opis', 'rok', 'prag_upozorenja_dana'];
        $stareVrednosti = [$zahtev['tip_analize'], $zahtev['opis'], $zahtev['rok'], $zahtev['prag_upozorenja_dana']];
        $noveVrednosti  = [$tipAnalize, $opisDb, $rokDb, $prag];

        for ($i = 0; $i < count($polja); $i++) {
            if ((string)($stareVrednosti[$i] ?? '') !== (string)($noveVrednosti[$i] ?? '')) {
                $staraStr = (string)($stareVrednosti[$i] ?? '');
                $novaStr  = (string)($noveVrednosti[$i] ?? '');
                $stmt = $conn->prepare("INSERT INTO istorija_izmene_zahteva (polje, stara_vrednost, nova_vrednost, datum_vreme, zahtev_id, korisnik_id) VALUES (?, ?, ?, NOW(), ?, ?)");
                $stmt->bind_param('sssii', $polja[$i], $staraStr, $novaStr, $zahtevId, $userId);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
        flashSuccess('Zahtev uspešno izmenjen.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška: ' . $e->getMessage());
        header("Location: ?page=analiza-izmeni&id={$zahtevId}");
        exit;
    }
}

// ─── Brisanje zahteva ──────────────────────────────────────────────────────
if ($page === 'analiza-detalji' && $action === 'obrisi') {
    requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

    $zahtevId = (int)($_GET['id'] ?? 0);
    $userId   = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT status, istrazitelj_id FROM zahtev_za_analizu WHERE id = ?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev) {
        flashError('Zahtev ne postoji.');
        header('Location: ?page=analize');
        exit;
    }

    $ulogaKorisnika = $_SESSION['uloga'];

    // ISTRAZITELJ može brisati samo KREIRAN; ADMINISTRATOR može sve osim U_TOKU i ZAVRSEN
    if ($ulogaKorisnika === 'ISTRAZITELJ') {
        if ($zahtev['status'] !== 'KREIRAN' || $zahtev['istrazitelj_id'] != $userId) {
            flashError('Možete brisati samo zahteve u statusu Kreiran koji su vaši.');
            header("Location: ?page=analiza-detalji&id={$zahtevId}");
            exit;
        }
    } elseif ($ulogaKorisnika === 'ADMINISTRATOR') {
        if (in_array($zahtev['status'], ['U_TOKU', 'ZAVRSEN'])) {
            flashError('Ne može se obrisati zahtev u statusu U_TOKU ili ZAVRSEN.');
            header("Location: ?page=analiza-detalji&id={$zahtevId}");
            exit;
        }
    }

    $razlog = trim($_POST['razlog'] ?? 'Obrisan od strane korisnika');

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO log_brisanja_zahteva (zahtev_id, datum_brisanja, razlog, obrisao_id) VALUES (?, NOW(), ?, ?)");
        $stmt->bind_param('isi', $zahtevId, $razlog, $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM zahtev_za_analizu WHERE id = ?");
        $stmt->bind_param('i', $zahtevId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        flashSuccess('Zahtev uspešno obrisan.');
        header('Location: ?page=analize');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška: ' . $e->getMessage());
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }
}

// ─── Dodela veštaka ────────────────────────────────────────────────────────
if ($page === 'analiza-dodela') {
    requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

    $zahtevId = (int)($_GET['id'] ?? 0);
    $vestakId = (int)($_POST['vestak_id'] ?? 0);
    $razlog   = trim($_POST['razlog'] ?? '');
    $userId   = $_SESSION['user_id'];

    if ($zahtevId < 1 || $vestakId < 1) {
        flashError('Izaberite veštaka.');
        header("Location: ?page=analiza-dodela&id={$zahtevId}");
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM zahtev_za_analizu WHERE id = ?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev) {
        flashError('Zahtev ne postoji.');
        header('Location: ?page=analize');
        exit;
    }

    $prvaDodela = is_null($zahtev['vestak_id']);

    if (!$prvaDodela && empty($razlog)) {
        flashError('Razlog preraspodele je obavezan.');
        header("Location: ?page=analiza-dodela&id={$zahtevId}");
        exit;
    }

    $conn->begin_transaction();
    try {
        $razlogDb = $razlog ?: null;
        $stmt = $conn->prepare("INSERT INTO istorija_dodele (datum_dodele, razlog_promene, zahtev_id, vestak_id, dodelio_id) VALUES (NOW(), ?, ?, ?, ?)");
        $stmt->bind_param('siii', $razlogDb, $zahtevId, $vestakId, $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET vestak_id=? WHERE id=?");
        $stmt->bind_param('ii', $vestakId, $zahtevId);
        $stmt->execute();
        $stmt->close();

        if ($prvaDodela) {
            $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET status='DODELJEN' WHERE id=?");
            $stmt->bind_param('i', $zahtevId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES ('KREIRAN', 'DODELJEN', NOW(), 'Veštak dodeljen', ?, ?)");
            $stmt->bind_param('ii', $zahtevId, $userId);
            $stmt->execute();
            $stmt->close();
        }

        // Obaveštenje veštaku se šalje tek kada tehničar odobri predaju dokaza

        // Pristup dokumentima predmeta
        $stmtDok = $conn->prepare("SELECT id FROM dokument WHERE predmet_id=?");
        $stmtDok->bind_param('i', $zahtev['predmet_id']);
        $stmtDok->execute();
        $dokumenti = $stmtDok->get_result();
        $stmtDok->close();

        $stmtIns = $conn->prepare("INSERT IGNORE INTO pravo_pristupa (nivo_pristupa, korisnik_id, dokument_id) VALUES ('CITANJE', ?, ?)");
        while ($dok = $dokumenti->fetch_assoc()) {
            $stmtIns->bind_param('ii', $vestakId, $dok['id']);
            $stmtIns->execute();
        }
        $stmtIns->close();

        $conn->commit();
        flashSuccess('Veštak uspešno dodeljen.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška: ' . $e->getMessage());
        header("Location: ?page=analiza-dodela&id={$zahtevId}");
        exit;
    }
}

// ─── Prihvatanje analize (DODELJEN → U_TOKU) ──────────────────────────────
if ($page === 'analiza-detalji' && $action === 'zapocni') {
    requireRole('VESTAK');

    $zahtevId = (int)($_GET['id'] ?? 0);
    $userId   = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT * FROM zahtev_za_analizu WHERE id=? AND vestak_id=? AND status='DODELJEN'");
    $stmt->bind_param('ii', $zahtevId, $userId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev) {
        flashError('Zahtev ne postoji, nije vam dodeljen, ili nije u statusu DODELJEN.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET status='U_TOKU', datum_pocetka=NOW() WHERE id=?");
        $stmt->bind_param('i', $zahtevId);
        $stmt->execute();
        $stmt->close();

        $napomena = 'Veštak prihvatio i započeo analizu';
        $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES ('DODELJEN', 'U_TOKU', NOW(), ?, ?, ?)");
        $stmt->bind_param('sii', $napomena, $zahtevId, $userId);
        $stmt->execute();
        $stmt->close();

        // Obaveštenje istražitelju
        $sadrzaj = "Analiza #{$zahtevId} je prihvaćena i u toku je";
        $stmt = $conn->prepare("INSERT INTO obavestenje (sadrzaj, tip, datum_vreme, korisnik_id, zahtev_id) VALUES (?, 'PROMENA_STATUSA', NOW(), ?, ?)");
        $stmt->bind_param('sii', $sadrzaj, $zahtev['istrazitelj_id'], $zahtevId);
        $stmt->execute();
        $obavestenjeId = $conn->insert_id;
        $stmt->close();

        $conn->commit();
        pushNotifikacija((int)$zahtev['istrazitelj_id'], $sadrzaj, $obavestenjeId);
        flashSuccess('Analiza prihvaćena. Status: U toku.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška: ' . $e->getMessage());
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }
}

// ─── Odbijanje analize od strane VESTAKA (DODELJEN → ODBIJEN) ─────────────
if ($page === 'analiza-detalji' && $action === 'odbij-vestak') {
    requireRole('VESTAK');

    $zahtevId = (int)($_GET['id'] ?? 0);
    $razlog   = trim($_POST['razlog'] ?? '');
    $userId   = $_SESSION['user_id'];

    if (empty($razlog)) {
        flashError('Razlog odbijanja je obavezan.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM zahtev_za_analizu WHERE id=? AND vestak_id=? AND status='DODELJEN'");
    $stmt->bind_param('ii', $zahtevId, $userId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev) {
        flashError('Zahtev ne postoji, nije vam dodeljen, ili nije u statusu DODELJEN.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET status='ODBIJEN' WHERE id=?");
        $stmt->bind_param('i', $zahtevId);
        $stmt->execute();
        $stmt->close();

        $napomena = 'Veštak odbio analizu: ' . $razlog;
        $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES ('DODELJEN', 'ODBIJEN', NOW(), ?, ?, ?)");
        $stmt->bind_param('sii', $napomena, $zahtevId, $userId);
        $stmt->execute();
        $stmt->close();

        // Obaveštenje istražitelju
        $sadrzajNotif = "Veštak je odbio analizu #{$zahtevId}";
        $stmt = $conn->prepare("INSERT INTO obavestenje (sadrzaj, tip, datum_vreme, korisnik_id, zahtev_id) VALUES (?, 'PROMENA_STATUSA', NOW(), ?, ?)");
        $stmt->bind_param('sii', $sadrzajNotif, $zahtev['istrazitelj_id'], $zahtevId);
        $stmt->execute();
        $obavestenjeId = $conn->insert_id;
        $stmt->close();

        $conn->commit();
        pushNotifikacija((int)$zahtev['istrazitelj_id'], $sadrzajNotif, $obavestenjeId);
        flashSuccess('Analiza odbijena.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška: ' . $e->getMessage());
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }
}

// ─── Odbijanje od strane ISTRAZITELJA/ADMINISTRATORA ──────────────────────
if ($page === 'analiza-detalji' && $action === 'odbij') {
    requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

    $zahtevId = (int)($_GET['id'] ?? 0);
    $userId   = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT status FROM zahtev_za_analizu WHERE id=?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev || in_array($zahtev['status'], ['ZAVRSEN', 'ODBIJEN'])) {
        flashError('Zahtev ne postoji ili je već u finalnom statusu.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }

    $stariStatus = $zahtev['status'];

    $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET status='ODBIJEN' WHERE id=?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $stmt->close();

    $napomena = 'Zahtev odbijen';
    $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES (?, 'ODBIJEN', NOW(), ?, ?, ?)");
    $stmt->bind_param('ssii', $stariStatus, $napomena, $zahtevId, $userId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Zahtev odbijen.');
    header("Location: ?page=analiza-detalji&id={$zahtevId}");
    exit;
}

// ─── Unos rezultata (VESTAK, U_TOKU → ZAVRSEN) ────────────────────────────
if ($page === 'analiza-rezultat') {
    requireRole('VESTAK');

    $zahtevId = (int)($_GET['id'] ?? 0);
    $sadrzaj  = trim($_POST['sadrzaj'] ?? '');
    $userId   = $_SESSION['user_id'];

    if (empty($sadrzaj)) {
        flashError('Sadržaj rezultata je obavezan.');
        header("Location: ?page=analiza-rezultat&id={$zahtevId}");
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM zahtev_za_analizu WHERE id=? AND vestak_id=? AND status='U_TOKU'");
    $stmt->bind_param('ii', $zahtevId, $userId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev) {
        flashError('Zahtev ne postoji, nije vam dodeljen, ili nije U_TOKU.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }

    $conn->begin_transaction();
    try {
        // Upiši rezultat
        $stmt = $conn->prepare("INSERT INTO rezultat_analize (sadrzaj, datum_unosa, verifikovan, zahtev_id, uneao_id) VALUES (?, NOW(), 0, ?, ?)");
        $stmt->bind_param('sii', $sadrzaj, $zahtevId, $userId);
        $stmt->execute();
        $stmt->close();

        // Promeni status u ZAVRSEN
        $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET status='ZAVRSEN' WHERE id=?");
        $stmt->bind_param('i', $zahtevId);
        $stmt->execute();
        $stmt->close();

        // Istorija statusa
        $napomena = 'Rezultat unet, analiza završena';
        $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES ('U_TOKU', 'ZAVRSEN', NOW(), ?, ?, ?)");
        $stmt->bind_param('sii', $napomena, $zahtevId, $userId);
        $stmt->execute();
        $stmt->close();

        // Obaveštenje istražitelju
        $sadrzajNotif = "Rezultati analize #{$zahtevId} su uneseni i čekaju verifikaciju";
        $stmt = $conn->prepare("INSERT INTO obavestenje (sadrzaj, tip, datum_vreme, korisnik_id, zahtev_id) VALUES (?, 'REZULTAT', NOW(), ?, ?)");
        $stmt->bind_param('sii', $sadrzajNotif, $zahtev['istrazitelj_id'], $zahtevId);
        $stmt->execute();
        $obavestenjeIdIstrazitelj = $conn->insert_id;
        $stmt->close();

        // Zahtev za povraćaj dokaza tehničaru
        $stmtD = $conn->prepare("SELECT tehnicar_id FROM dokaz WHERE id = ?");
        $stmtD->bind_param('i', $zahtev['dokaz_id']);
        $stmtD->execute();
        $tehnicarId = $stmtD->get_result()->fetch_assoc()['tehnicar_id'];
        $stmtD->close();

        $razlogPovracaj = 'Automatski zahtev — analiza #' . $zahtevId . ' završena, dokaz za povraćaj';
        $stmt = $conn->prepare("INSERT INTO zahtev_za_dokaz (tip, razlog, status, dokaz_id, podnosilac_id) VALUES ('POVRACAJ', ?, 'NA_CEKANJU', ?, ?)");
        $stmt->bind_param('sii', $razlogPovracaj, $zahtev['dokaz_id'], $userId);
        $stmt->execute();
        $stmt->close();

        // Obaveštenje tehničaru o povraćaju
        $sadrzajTeh = 'Zahtev za povraćaj dokaza — analiza #' . $zahtevId . ' završena';
        $stmt = $conn->prepare("INSERT INTO obavestenje (sadrzaj, tip, datum_vreme, korisnik_id, zahtev_id) VALUES (?, 'ZAHTEV_DOKAZ', NOW(), ?, ?)");
        $stmt->bind_param('sii', $sadrzajTeh, $tehnicarId, $zahtevId);
        $stmt->execute();
        $obavestenjeIdTehnicar = $conn->insert_id;
        $stmt->close();

        $conn->commit();
        pushNotifikacija((int)$zahtev['istrazitelj_id'], $sadrzajNotif, $obavestenjeIdIstrazitelj);
        pushNotifikacija((int)$tehnicarId, $sadrzajTeh, $obavestenjeIdTehnicar);
        flashSuccess('Rezultat uspešno unet. Analiza je završena i čeka verifikaciju.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška: ' . $e->getMessage());
        header("Location: ?page=analiza-rezultat&id={$zahtevId}");
        exit;
    }
}

// ─── Verifikacija rezultata (ISTRAZITELJ/ADMINISTRATOR) ───────────────────
if ($page === 'analiza-detalji' && $action === 'verifikuj') {
    requireRole('ISTRAZITELJ', 'ADMINISTRATOR');

    $zahtevId = (int)($_GET['id'] ?? 0);
    $userId   = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT id FROM rezultat_analize WHERE zahtev_id=? AND verifikovan=0");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        flashError('Nema rezultata za verifikaciju ili je već verifikovan.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE rezultat_analize SET verifikovan=1 WHERE zahtev_id=?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Rezultat verifikovan.');
    header("Location: ?page=analiza-detalji&id={$zahtevId}");
    exit;
}
