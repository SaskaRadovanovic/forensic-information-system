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

    $opisDb    = $opis ?: null;
    $kreiraoId = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO predmet (naziv, opis, status, faza, datum_otvaranja, istrazitelj_id) VALUES (?, ?, 'AKTIVAN', 'OTVOREN_SLUCAJ', NOW(), ?)");
    $stmt->bind_param('ssi', $naziv, $opisDb, $kreiraoId);
    $stmt->execute();
    $predmetId = $conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO istorija_faze_predmeta (faza, predmet_id, korisnik_id) VALUES ('OTVOREN_SLUCAJ', ?, ?)");
    $stmt->bind_param('ii', $predmetId, $kreiraoId);
    $stmt->execute();
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
    $userId    = $_SESSION['user_id'];
    $uloga     = $_SESSION['uloga'];

    $stmt = $conn->prepare("SELECT faza, status, istrazitelj_id FROM predmet WHERE id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $predmet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$predmet || $predmet['status'] === 'ZATVOREN') {
        flashError('Predmet ne postoji ili je zatvoren.');
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    // Samo kreator predmeta (ili ADMINISTRATOR) može menjati fazu
    if ($uloga === 'ISTRAZITELJ' && (int)$predmet['istrazitelj_id'] !== $userId) {
        $poruka = 'Samo istražitelja koji je kreirao predmet može menjati fazu.';
        flashError($poruka);
        $stmt = $conn->prepare("INSERT INTO obavestenje (sadrzaj, tip, korisnik_id) VALUES (?, 'UPOZORENJE', ?)");
        $stmt->bind_param('si', $poruka, $userId);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    $faze          = ['OTVOREN_SLUCAJ', 'PRIKUPLJANJE_DOKAZA', 'ANALIZA_DOKAZA', 'DONOSENJE_ZAKLJUCKA', 'ZATVOREN_SLUCAJ'];
    $trenutniIndex = array_search($predmet['faza'], $faze);

    if ($trenutniIndex === false || $trenutniIndex >= count($faze) - 1) {
        flashError('Predmet je već u poslednjoj fazi.');
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    $sledecaFaza = $faze[$trenutniIndex + 1];
    $greske      = [];

    // ── PRIKUPLJANJE_DOKAZA → ANALIZA_DOKAZA ──────────────────────────────
    if ($sledecaFaza === 'ANALIZA_DOKAZA') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM dokaz WHERE predmet_id = ?");
        $stmt->bind_param('i', $predmetId);
        $stmt->execute();
        $brDokaza = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($brDokaza === 0) {
            $greske[] = 'Predmet mora imati bar jedan dokaz.';
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM dokaz WHERE predmet_id = ? AND status != 'IZDATO_ZA_ANALIZU'");
        $stmt->bind_param('i', $predmetId);
        $stmt->execute();
        $nevalidni = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($nevalidni > 0) {
            $greske[] = "Svi dokazi moraju imati status 'Izdato za analizu' ({$nevalidni} " . ($nevalidni === 1 ? 'nema' : 'nemaju') . " taj status).";
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM zahtev_za_analizu WHERE predmet_id = ?");
        $stmt->bind_param('i', $predmetId);
        $stmt->execute();
        $brAnaliza = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($brAnaliza === 0) {
            $greske[] = 'Mora postojati bar jedan zahtev za analizu.';
        }
    }

    // ── ANALIZA_DOKAZA → DONOSENJE_ZAKLJUCKA ─────────────────────────────
    if ($sledecaFaza === 'DONOSENJE_ZAKLJUCKA') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM zahtev_za_analizu WHERE predmet_id = ? AND status != 'ZAVRSEN'");
        $stmt->bind_param('i', $predmetId);
        $stmt->execute();
        $nezavrsene = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($nezavrsene > 0) {
            $greske[] = "Sve analize moraju biti završene ({$nezavrsene} " . ($nezavrsene === 1 ? 'nije' : 'nisu') . " završena).";
        }
    }

    // ── DONOSENJE_ZAKLJUCKA → ZATVOREN_SLUCAJ ────────────────────────────
    if ($sledecaFaza === 'ZATVOREN_SLUCAJ') {
        $stmt = $conn->prepare("SELECT id FROM zavrsni_izvestaj WHERE predmet_id = ?");
        $stmt->bind_param('i', $predmetId);
        $stmt->execute();
        $izvestaj = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$izvestaj) {
            $greske[] = 'Kreiranje završnog izveštaja je obavezno pre zatvaranja predmeta.';
        }
    }

    if (!empty($greske)) {
        $poruka = 'Prelaz u fazu "' . fazaLabel($sledecaFaza) . '" nije moguć: ' . implode(' ', $greske);
        flashError($poruka);
        $stmt = $conn->prepare("INSERT INTO obavestenje (sadrzaj, tip, korisnik_id) VALUES (?, 'UPOZORENJE', ?)");
        $stmt->bind_param('si', $poruka, $userId);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    $noviStatus = ($sledecaFaza === 'ZATVOREN_SLUCAJ') ? 'ZATVOREN' : 'AKTIVAN';
    $stmt = $conn->prepare("UPDATE predmet SET faza = ?, status = ? WHERE id = ?");
    $stmt->bind_param('ssi', $sledecaFaza, $noviStatus, $predmetId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO istorija_faze_predmeta (faza, predmet_id, korisnik_id) VALUES (?, ?, ?)");
    $stmt->bind_param('sii', $sledecaFaza, $predmetId, $userId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Faza promenjena u: ' . fazaLabel($sledecaFaza));
    header("Location: ?page=predmet-detalji&id={$predmetId}");
    exit;
}

// ─── Kreiranje završnog izveštaja ─────────────────────────────────────────
if ($page === 'predmet-detalji' && $action === 'kreiraj-izvestaj') {
    requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

    $predmetId = (int)($_GET['id'] ?? 0);
    $userId    = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT faza, status, istrazitelj_id FROM predmet WHERE id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $predmet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$predmet) {
        flashError('Predmet nije pronađen.');
        header('Location: ?page=predmeti');
        exit;
    }

    if ($_SESSION['uloga'] === 'ISTRAZITELJ' && (int)$predmet['istrazitelj_id'] !== $userId) {
        flashError('Samo kreator predmeta može kreirati završni izveštaj.');
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    if ($predmet['faza'] !== 'DONOSENJE_ZAKLJUCKA') {
        flashError('Završni izveštaj se može kreirati samo u fazi Donošenje zaključka.');
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    $predlogOdluke = trim($_POST['predlog_odluke'] ?? '');
    $pregledNalaza = trim($_POST['pregled_nalaza'] ?? '');
    $zakljucak     = trim($_POST['zakljucak_istrage'] ?? '');

    if (!$predlogOdluke || !$pregledNalaza || !$zakljucak) {
        flashError('Sva polja završnog izveštaja su obavezna.');
        header("Location: ?page=predmet-detalji&id={$predmetId}");
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO zavrsni_izvestaj (predlog_odluke, pregled_nalaza, zakljucak_istrage, predmet_id, kreirao_id)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            predlog_odluke    = VALUES(predlog_odluke),
            pregled_nalaza    = VALUES(pregled_nalaza),
            zakljucak_istrage = VALUES(zakljucak_istrage)
    ");
    $stmt->bind_param('sssii', $predlogOdluke, $pregledNalaza, $zakljucak, $predmetId, $userId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Završni izveštaj sačuvan. Možete preći u fazu Zatvoren slučaj.');
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
    requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

    $predmetId = (int)($_GET['id'] ?? 0);

    // ISTRAZITELJ sme brisati samo predmete koje je sam kreirao
    if ($_SESSION['uloga'] === 'ISTRAZITELJ') {
        $stmt = $conn->prepare("SELECT istrazitelj_id FROM predmet WHERE id = ?");
        $stmt->bind_param('i', $predmetId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || (int)$row['istrazitelj_id'] !== $_SESSION['user_id']) {
            flashError('Možete brisati samo predmete koje ste vi kreirali.');
            header("Location: ?page=predmet-detalji&id={$predmetId}");
            exit;
        }
    }

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
        // 5. Završni izveštaj i istorija faza
        $cascadeDelete("DELETE FROM zavrsni_izvestaj WHERE predmet_id = ?");
        $cascadeDelete("DELETE FROM istorija_faze_predmeta WHERE predmet_id = ?");
        // 6. Dokumenti
        $cascadeDelete("DELETE dt FROM dokument_tag dt JOIN dokument dok ON dt.dokument_id = dok.id WHERE dok.predmet_id = ?");
        $cascadeDelete("DELETE m FROM metapodatak m JOIN dokument dok ON m.dokument_id = dok.id WHERE dok.predmet_id = ?");
        $cascadeDelete("DELETE pp FROM pravo_pristupa pp JOIN dokument dok ON pp.dokument_id = dok.id WHERE dok.predmet_id = ?");
        $cascadeDelete("DELETE da FROM dokument_arhiva da JOIN dokument dok ON da.dokument_id = dok.id WHERE dok.predmet_id = ?");
        $cascadeDelete("DELETE FROM dokument WHERE predmet_id = ?");
        // 7. Predmet
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
