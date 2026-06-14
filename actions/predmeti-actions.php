<?php
/**
 * actions/predmeti-actions.php — POST obrada za modul Predmeti
 *
 * Obrađuje kreiranje, izmenu, brisanje, zatvaranje predmeta i promenu faze.
 */

// ─── Kreiranje novog predmeta ──────────────────────────────────────────────
if ($page === 'predmet-novi') {
    requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

    $naziv = trim($_POST['naziv'] ?? '');
    $opis  = trim($_POST['opis'] ?? '');

    if (empty($naziv)) {
        flashError('Naziv predmeta je obavezan.');
        header('Location: ?page=predmet-novi');
        exit;
    }

    $opisDb = $opis ?: null;
    $stmt = $conn->prepare("INSERT INTO predmet (naziv, opis, status, faza, datum_otvaranja) VALUES (?, ?, 'AKTIVAN', 'OTVOREN_SLUCAJ', NOW())");
    $stmt->bind_param('ss', $naziv, $opisDb);
    $stmt->execute();
    $predmetId = $conn->insert_id;
    $stmt->close();

    flashSuccess('Predmet uspešno kreiran.');
    header("Location: ?page=predmet-detalji&id={$predmetId}");
    exit;
}

// ─── Izmena predmeta ───────────────────────────────────────────────────────
if ($page === 'predmet-izmeni') {
    requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

    $predmetId = (int)($_GET['id'] ?? 0);
    $naziv     = trim($_POST['naziv'] ?? '');
    $opis      = trim($_POST['opis'] ?? '');

    // Provera da predmet postoji
    $stmt = $conn->prepare("SELECT status FROM predmet WHERE id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $predmet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$predmet) {
        flashError('Predmet nije pronađen.');
        header('Location: ?page=predmeti');
        exit;
    }

    // ZATVOREN — samo ADMINISTRATOR može menjati
    if ($predmet['status'] === 'ZATVOREN' && $_SESSION['uloga'] !== 'ADMINISTRATOR') {
        flashError('Samo administrator može menjati zatvoren predmet.');
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    if (empty($naziv)) {
        flashError('Naziv je obavezan.');
        header("Location: ?page=predmet-izmeni&id={$predmetId}");
        exit;
    }

    $opisDb = $opis ?: null;
    $stmt = $conn->prepare("UPDATE predmet SET naziv = ?, opis = ? WHERE id = ?");
    $stmt->bind_param('ssi', $naziv, $opisDb, $predmetId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Predmet uspešno izmenjen.');
    header("Location: ?page=predmet-detalji&id={$predmetId}");
    exit;
}

// ─── Zatvaranje predmeta ───────────────────────────────────────────────────
if ($page === 'predmet-detalji' && $action === 'zatvori') {
    requireRole('ADMINISTRATOR');

    $predmetId = (int)($_GET['id'] ?? 0);

    $stmt = $conn->prepare("UPDATE predmet SET status = 'ZATVOREN' WHERE id = ? AND status = 'AKTIVAN'");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Predmet zatvoren.');
    header("Location: ?page=predmet-detalji&id={$predmetId}");
    exit;
}

// ─── Promena faze ──────────────────────────────────────────────────────────
if ($page === 'predmet-detalji' && $action === 'sledeca-faza') {
    requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

    $predmetId = (int)($_GET['id'] ?? 0);

    // Učitaj trenutnu fazu
    $stmt = $conn->prepare("SELECT faza, status FROM predmet WHERE id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $predmet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$predmet || $predmet['status'] === 'ZATVOREN') {
        flashError('Predmet ne postoji ili je zatvoren.');
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    // Redosled faza
    $faze = ['OTVOREN_SLUCAJ', 'PRIKUPLJANJE_DOKAZA', 'ANALIZA_DOKAZA', 'DONOSENJE_ZAKLJUCKA', 'ZATVOREN_SLUCAJ'];
    $trenutniIndex = array_search($predmet['faza'], $faze);

    if ($trenutniIndex === false || $trenutniIndex >= count($faze) - 1) {
        flashError('Predmet je već u poslednjoj fazi.');
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    $sledecaFaza = $faze[$trenutniIndex + 1];

    // Pre DONOSENJE_ZAKLJUCKA proveri da nema aktivnih analiza
    if ($sledecaFaza === 'DONOSENJE_ZAKLJUCKA') {
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM zahtev_za_analizu WHERE predmet_id = ? AND status IN ('KREIRAN','DODELJEN','U_TOKU')");
        $stmt->bind_param('i', $predmetId);
        $stmt->execute();
        $aktivneAnalize = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($aktivneAnalize > 0) {
            flashError("Ne može se preći u fazu 'Donošenje zaključka' dok ima {$aktivneAnalize} aktivnih analiza.");
            header("Location: ?page=predmet-detalji&id={$predmetId}");
            exit;
        }
    }

    $stmt = $conn->prepare("UPDATE predmet SET faza = ? WHERE id = ?");
    $stmt->bind_param('si', $sledecaFaza, $predmetId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Faza promenjena u: ' . fazaLabel($sledecaFaza));
    header("Location: ?page=predmet-detalji&id={$predmetId}");
    exit;
}

// ─── Brisanje predmeta ─────────────────────────────────────────────────────
if ($page === 'predmet-detalji' && $action === 'obrisi') {
    requireRole('ADMINISTRATOR');

    $predmetId = (int)($_GET['id'] ?? 0);

    // Provera da li ima povezane dokaze, dokumente ili analize
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM dokaz WHERE predmet_id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $brDokaza = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM dokument WHERE predmet_id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $brDokumenata = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM zahtev_za_analizu WHERE predmet_id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $brAnaliza = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($brDokaza > 0 || $brDokumenata > 0 || $brAnaliza > 0) {
        flashError("Ne može se obrisati predmet sa povezanim podacima ({$brDokaza} dokaza, {$brDokumenata} dokumenata, {$brAnaliza} analiza).");
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM predmet WHERE id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Predmet uspešno obrisan.');
    header('Location: ?page=predmeti');
    exit;
}

// ─── Potpuno brisanje (kaskadno) ───────────────────────────────────────────
if ($page === 'predmet-detalji' && $action === 'obrisi-sve') {
    requireRole('ADMINISTRATOR');

    $predmetId = (int)($_GET['id'] ?? 0);

    // Pomoćna funkcija za kaskadno brisanje sa prepared statements
    $cascadeDelete = function(string $sql) use ($conn, $predmetId) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $predmetId);
        $stmt->execute();
        $stmt->close();
    };

    $conn->begin_transaction();
    try {
        // Brisanje u redosledu FK zavisnosti
        // 1. Obaveštenja vezana za analize ovog predmeta
        $cascadeDelete("DELETE o FROM obavestenje o JOIN zahtev_za_analizu z ON o.zahtev_id = z.id WHERE z.predmet_id = ?");
        // 2. Istorija i logovi analiza
        $cascadeDelete("DELETE h FROM istorija_statusa_analize h JOIN zahtev_za_analizu z ON h.zahtev_id = z.id WHERE z.predmet_id = ?");
        $cascadeDelete("DELETE h FROM istorija_dodele h JOIN zahtev_za_analizu z ON h.zahtev_id = z.id WHERE z.predmet_id = ?");
        $cascadeDelete("DELETE h FROM istorija_izmene_zahteva h JOIN zahtev_za_analizu z ON h.zahtev_id = z.id WHERE z.predmet_id = ?");
        $cascadeDelete("DELETE r FROM rezultat_analize r JOIN zahtev_za_analizu z ON r.zahtev_id = z.id WHERE z.predmet_id = ?");
        // 3. Analize
        $cascadeDelete("DELETE FROM zahtev_za_analizu WHERE predmet_id = ?");
        // 4. Zahtevi za dokaze, lanac čuvanja, ISA tabele, dokazi
        $cascadeDelete("DELETE zd FROM zahtev_za_dokaz zd JOIN dokaz d ON zd.dokaz_id = d.id WHERE d.predmet_id = ?");
        $cascadeDelete("DELETE lc FROM lanac_cuvanja lc JOIN dokaz d ON lc.dokaz_id = d.id WHERE d.predmet_id = ?");
        $cascadeDelete("DELETE bt FROM bioloski_trag bt JOIN dokaz d ON bt.id_dokaz = d.id WHERE d.predmet_id = ?");
        $cascadeDelete("DELETE o FROM oruzje o JOIN dokaz d ON o.id_dokaz = d.id WHERE d.predmet_id = ?");
        $cascadeDelete("DELETE dd FROM dokument_dokaz dd JOIN dokaz d ON dd.id_dokaz = d.id WHERE d.predmet_id = ?");
        $cascadeDelete("DELETE od FROM odeca od JOIN dokaz d ON od.id_dokaz = d.id WHERE d.predmet_id = ?");
        $cascadeDelete("DELETE u FROM uzorak u JOIN dokaz d ON u.id_dokaz = d.id WHERE d.predmet_id = ?");
        $cascadeDelete("DELETE FROM dokaz WHERE predmet_id = ?");
        // 5. Dokumenti
        $cascadeDelete("DELETE dt FROM dokument_tag dt JOIN dokument dok ON dt.dokument_id = dok.id WHERE dok.predmet_id = ?");
        $cascadeDelete("DELETE m FROM metapodatak m JOIN dokument dok ON m.dokument_id = dok.id WHERE dok.predmet_id = ?");
        $cascadeDelete("DELETE pp FROM pravo_pristupa pp JOIN dokument dok ON pp.dokument_id = dok.id WHERE dok.predmet_id = ?");
        $cascadeDelete("DELETE da FROM dokument_arhiva da JOIN dokument dok ON da.dokument_id = dok.id WHERE dok.predmet_id = ?");
        $cascadeDelete("DELETE FROM dokument WHERE predmet_id = ?");
        // 6. Predmet
        $cascadeDelete("DELETE FROM predmet WHERE id = ?");

        $conn->commit();
        flashSuccess('Predmet i svi povezani podaci uspešno obrisani.');
        header('Location: ?page=predmeti');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška pri brisanju: ' . $e->getMessage());
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }
}
