<?php
/**
 * actions/analize-actions.php — POST obrada za modul Analize
 *
 * Obrađuje kreiranje, izmenu, brisanje, dodelu veštaka,
 * započinjanje, odbijanje, unos rezultata i verifikaciju.
 */

// ─── Kreiranje zahteva za analizu ──────────────────────────────────────────
if ($page === 'analiza-nova') {
    requireRole('ISTRAZITELJ');

    // Provera da korisnik ima istrazitelj profil
    $stmt = $conn->prepare("SELECT id_korisnik FROM istrazitelj WHERE id_korisnik = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        flashError('Nemate istražitelj profil.');
        header('Location: ?page=analize');
        exit;
    }
    $stmt->close();

    $predmetId  = (int)($_POST['predmet_id'] ?? 0);
    $dokazId    = (int)($_POST['dokaz_id'] ?? 0);
    $tipAnalize = $_POST['tip_analize'] ?? '';
    $opis       = trim($_POST['opis'] ?? '');
    $rok        = $_POST['rok'] ?? null;
    $prag       = (int)($_POST['prag_upozorenja_dana'] ?? 3);

    if ($predmetId < 1 || $dokazId < 1 || empty($tipAnalize)) {
        flashError('Popunite obavezna polja: predmet, dokaz i tip analize.');
        header('Location: ?page=analiza-nova');
        exit;
    }

    $conn->begin_transaction();
    try {
        $opisDb = $opis ?: null;
        $rokDb  = $rok ?: null;
        $userId = $_SESSION['user_id'];
        $stmt = $conn->prepare("
            INSERT INTO zahtev_za_analizu (opis, tip_analize, datum_kreiranja, rok, status, prag_upozorenja_dana, istrazitelj_id, dokaz_id, predmet_id)
            VALUES (?, ?, NOW(), ?, 'KREIRAN', ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssiiii', $opisDb, $tipAnalize, $rokDb, $prag, $userId, $dokazId, $predmetId);
        $stmt->execute();
        $zahtevId = $conn->insert_id;
        $stmt->close();

        // Istorija statusa
        $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES (NULL, 'KREIRAN', NOW(), 'Zahtev za analizu kreiran', ?, ?)");
        $stmt->bind_param('ii', $zahtevId, $userId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
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
    $prag       = (int)($_POST['prag_upozorenja_dana'] ?? 3);

    // Provera statusa
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
        $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET tip_analize = ?, opis = ?, rok = ?, prag_upozorenja_dana = ? WHERE id = ?");
        $stmt->bind_param('sssii', $tipAnalize, $opisDb, $rokDb, $prag, $zahtevId);
        $stmt->execute();
        $stmt->close();

        // Loguj promene u istoriju izmena
        $polja = ['tip_analize', 'opis', 'rok', 'prag_upozorenja_dana'];
        $stareVrednosti = [$zahtev['tip_analize'], $zahtev['opis'], $zahtev['rok'], $zahtev['prag_upozorenja_dana']];
        $noveVrednosti = [$tipAnalize, $opis ?: null, $rok ?: null, $prag];

        for ($i = 0; $i < count($polja); $i++) {
            if ((string)$stareVrednosti[$i] !== (string)$noveVrednosti[$i]) {
                $stmt = $conn->prepare("INSERT INTO istorija_izmene_zahteva (polje, stara_vrednost, nova_vrednost, datum_vreme, zahtev_id, korisnik_id) VALUES (?, ?, ?, NOW(), ?, ?)");
                $staraStr = (string)($stareVrednosti[$i] ?? '');
                $novaStr = (string)($noveVrednosti[$i] ?? '');
                $userId = $_SESSION['user_id'];
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
    requireRole('ADMINISTRATOR');

    $zahtevId = (int)($_GET['id'] ?? 0);

    $stmt = $conn->prepare("SELECT status FROM zahtev_za_analizu WHERE id = ?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev || in_array($zahtev['status'], ['U_TOKU', 'ZAVRSEN'])) {
        flashError('Ne može se obrisati zahtev u statusu U_TOKU ili ZAVRSEN.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }

    $razlog = trim($_POST['razlog'] ?? 'Obrisan od strane administratora');

    $conn->begin_transaction();
    try {
        // Log brisanja
        $stmt = $conn->prepare("INSERT INTO log_brisanja_zahteva (zahtev_id, datum_brisanja, razlog, obrisao_id) VALUES (?, NOW(), ?, ?)");
        $userId = $_SESSION['user_id'];
        $stmt->bind_param('isi', $zahtevId, $razlog, $userId);
        $stmt->execute();
        $stmt->close();

        // Brisanje zahteva (CASCADE briše istorije)
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

    $zahtevId  = (int)($_GET['id'] ?? 0);
    $vestakId  = (int)($_POST['vestak_id'] ?? 0);
    $razlog    = trim($_POST['razlog'] ?? '');

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

    // Ako je preraspodela, razlog je obavezan
    if (!$prvaDodela && empty($razlog)) {
        flashError('Razlog preraspodele je obavezan.');
        header("Location: ?page=analiza-dodela&id={$zahtevId}");
        exit;
    }

    $conn->begin_transaction();
    try {
        // Istorija dodele
        $razlogDb = $razlog ?: null;
        $userId = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO istorija_dodele (datum_dodele, razlog_promene, zahtev_id, vestak_id, dodelio_id) VALUES (NOW(), ?, ?, ?, ?)");
        $stmt->bind_param('siii', $razlogDb, $zahtevId, $vestakId, $userId);
        $stmt->execute();
        $stmt->close();

        // UPDATE vestak_id na zahtevu
        $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET vestak_id = ? WHERE id = ?");
        $stmt->bind_param('ii', $vestakId, $zahtevId);
        $stmt->execute();
        $stmt->close();

        // Ako je prva dodela → promeni status u DODELJEN
        if ($prvaDodela) {
            $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET status = 'DODELJEN' WHERE id = ?");
            $stmt->bind_param('i', $zahtevId);
            $stmt->execute();
            $stmt->close();

            // Istorija statusa
            $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES ('KREIRAN', 'DODELJEN', NOW(), 'Veštak dodeljen', ?, ?)");
            $stmt->bind_param('ii', $zahtevId, $userId);
            $stmt->execute();
            $stmt->close();

            // Obaveštenje veštaku
            $stmt = $conn->prepare("INSERT INTO obavestenje (sadrzaj, tip, datum_vreme, korisnik_id, zahtev_id) VALUES ('Nova analiza vam je dodeljena', 'DODELA', NOW(), ?, ?)");
            $stmt->bind_param('ii', $vestakId, $zahtevId);
            $stmt->execute();
            $stmt->close();
        }

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

// ─── Započni analizu ───────────────────────────────────────────────────────
if ($page === 'analiza-detalji' && $action === 'zapocni') {
    requireRole('VESTAK');

    $zahtevId = (int)($_GET['id'] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM zahtev_za_analizu WHERE id = ? AND vestak_id = ? AND status = 'DODELJEN'");
    $userId = $_SESSION['user_id'];
    $stmt->bind_param('ii', $zahtevId, $userId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev) {
        flashError('Zahtev ne postoji, nije vam dodeljen, ili nije u statusu DODELJEN.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }

    $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET status = 'U_TOKU', datum_pocetka = NOW() WHERE id = ?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES ('DODELJEN', 'U_TOKU', NOW(), 'Analiza započeta', ?, ?)");
    $stmt->bind_param('ii', $zahtevId, $userId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Analiza započeta.');
    header("Location: ?page=analiza-detalji&id={$zahtevId}");
    exit;
}

// ─── Odbij zahtev ──────────────────────────────────────────────────────────
if ($page === 'analiza-detalji' && $action === 'odbij') {
    requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

    $zahtevId = (int)($_GET['id'] ?? 0);

    $stmt = $conn->prepare("SELECT status FROM zahtev_za_analizu WHERE id = ?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev) {
        flashError('Zahtev ne postoji.');
        header('Location: ?page=analize');
        exit;
    }

    $stariStatus = $zahtev['status'];
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET status = 'ODBIJEN' WHERE id = ?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES (?, 'ODBIJEN', NOW(), 'Zahtev odbijen', ?, ?)");
    $stmt->bind_param('sii', $stariStatus, $zahtevId, $userId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Zahtev odbijen.');
    header("Location: ?page=analiza-detalji&id={$zahtevId}");
    exit;
}

// ─── Unos rezultata ────────────────────────────────────────────────────────
if ($page === 'analiza-rezultat') {
    requireRole('VESTAK');

    $zahtevId = (int)($_GET['id'] ?? 0);
    $sadrzaj  = trim($_POST['sadrzaj'] ?? '');

    if (empty($sadrzaj)) {
        flashError('Sadržaj rezultata je obavezan.');
        header("Location: ?page=analiza-rezultat&id={$zahtevId}");
        exit;
    }

    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT * FROM zahtev_za_analizu WHERE id = ? AND vestak_id = ? AND status = 'U_TOKU'");
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
        $stmt = $conn->prepare("INSERT INTO rezultat_analize (sadrzaj, datum_unosa, verifikovan, zahtev_id, uneao_id) VALUES (?, NOW(), 0, ?, ?)");
        $stmt->bind_param('sii', $sadrzaj, $zahtevId, $userId);
        $stmt->execute();
        $stmt->close();

        // Obaveštenje istražitelju
        $stmt = $conn->prepare("INSERT INTO obavestenje (sadrzaj, tip, datum_vreme, korisnik_id, zahtev_id) VALUES ('Rezultat analize je unet i čeka verifikaciju', 'REZULTAT', NOW(), ?, ?)");
        $stmt->bind_param('ii', $zahtev['istrazitelj_id'], $zahtevId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        flashSuccess('Rezultat uspešno unet.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška: ' . $e->getMessage());
        header("Location: ?page=analiza-rezultat&id={$zahtevId}");
        exit;
    }
}

// ─── Verifikacija rezultata ────────────────────────────────────────────────
if ($page === 'analiza-detalji' && $action === 'verifikuj') {
    requireRole('ISTRAZITELJ', 'ADMINISTRATOR');

    $zahtevId = (int)($_GET['id'] ?? 0);

    $conn->begin_transaction();
    try {
        // Verifikuj rezultat
        $stmt = $conn->prepare("UPDATE rezultat_analize SET verifikovan = 1 WHERE zahtev_id = ?");
        $stmt->bind_param('i', $zahtevId);
        $stmt->execute();
        $stmt->close();

        // Promeni status u ZAVRSEN
        $stmt = $conn->prepare("SELECT status FROM zahtev_za_analizu WHERE id = ?");
        $stmt->bind_param('i', $zahtevId);
        $stmt->execute();
        $zahtev = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stariStatus = $zahtev['status'];
        $stmt = $conn->prepare("UPDATE zahtev_za_analizu SET status = 'ZAVRSEN' WHERE id = ?");
        $stmt->bind_param('i', $zahtevId);
        $stmt->execute();
        $stmt->close();

        $userId = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES (?, 'ZAVRSEN', NOW(), 'Rezultat verifikovan, analiza završena', ?, ?)");
        $stmt->bind_param('sii', $stariStatus, $zahtevId, $userId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        flashSuccess('Rezultat verifikovan. Analiza završena.');
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška: ' . $e->getMessage());
        header("Location: ?page=analiza-detalji&id={$zahtevId}");
        exit;
    }
}
