<?php
/**
 * actions/dokazi-actions.php — POST obrada za modul Dokazi
 *
 * Obrađuje kreiranje, izmenu, arhiviranje dokaza
 * i obradu zahteva za dokaze.
 */

// ─── Kreiranje novog dokaza ─────────────────────────────────────────────────
if ($page === 'dokaz-novi') {
    requireRole('TEHNICAR', 'ADMINISTRATOR');

    // Provera da korisnik ima tehnicar_za_dokaze profil
    $stmt = $conn->prepare("SELECT id_korisnik FROM tehnicar_za_dokaze WHERE id_korisnik = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        flashError('Nemate tehničar profil za rad sa dokazima.');
        header('Location: ?page=dokazi');
        exit;
    }
    $stmt->close();

    // Čitanje podataka iz forme
    $naziv               = trim($_POST['naziv'] ?? '');
    $tipDokaza           = $_POST['tip_dokaza'] ?? '';
    $predmetId           = (int)($_POST['predmet_id'] ?? 0);
    $opis                = trim($_POST['opis'] ?? '');
    $datumPrijema        = $_POST['datum_prijema'] ?? date('Y-m-d H:i:s');
    $datumPronalaska     = $_POST['datum_pronalaska'] ?? null;
    $lokacijaPronalaska  = trim($_POST['lokacija_pronalaska'] ?? '');
    $lokacijaSkladistenja = trim($_POST['lokacija_skladistenja'] ?? '');

    // Validacija obaveznih polja
    if (empty($naziv) || empty($tipDokaza) || $predmetId < 1) {
        flashError('Popunite obavezna polja: naziv, tip dokaza i predmet.');
        header('Location: ?page=dokaz-novi');
        exit;
    }

    // Provera da je predmet u fazi prikupljanja dokaza
    $stmt = $conn->prepare("SELECT faza FROM predmet WHERE id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $predmet = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$predmet || $predmet['faza'] !== 'PRIKUPLJANJE_DOKAZA') {
        flashError('Unos dokaza je moguć samo kada je predmet u fazi „Prikupljanje dokaza".');
        header('Location: ?page=dokaz-novi');
        exit;
    }

    // Generisanje šifre: DOK-YYYY-NNN
    $godina = date('Y');
    $res = $conn->query("SELECT COUNT(*) as cnt FROM dokaz WHERE sifra_dokaza LIKE 'DOK-{$godina}-%'");
    $redniBroj = $res->fetch_assoc()['cnt'] + 1;
    $sifraDokaza = sprintf('DOK-%s-%03d', $godina, $redniBroj);

    $tehnicarId = $_SESSION['user_id'];

    // ─── Transakcija: INSERT dokaz + ISA tabela + lanac čuvanja ─────────────
    $conn->begin_transaction();
    try {
        // INSERT dokaz
        $stmt = $conn->prepare("
            INSERT INTO dokaz (sifra_dokaza, naziv, opis, tip_dokaza, datum_prijema, datum_pronalaska, lokacija_pronalaska, lokacija_skladistenja, status, predmet_id, tehnicar_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'U_SKLADISTU', ?, ?)
        ");
        $opisDb = $opis ?: null;
        $datumPronalaskaDb = $datumPronalaska ?: null;
        $lokPronDb = $lokacijaPronalaska ?: null;
        $lokSklDb  = $lokacijaSkladistenja ?: null;
        $stmt->bind_param('ssssssssii', $sifraDokaza, $naziv, $opisDb, $tipDokaza, $datumPrijema, $datumPronalaskaDb, $lokPronDb, $lokSklDb, $predmetId, $tehnicarId);
        $stmt->execute();
        $dokazId = $conn->insert_id;
        $stmt->close();

        // INSERT u ISA tabelu na osnovu tipa dokaza
        switch ($tipDokaza) {
            case 'BIOLOSKI_TRAG':
                $stmt = $conn->prepare("INSERT INTO bioloski_trag (id_dokaz, vrsta_traga, nacin_uzorkovanja, uslovi_cuvanja, kolicina) VALUES (?, ?, ?, ?, ?)");
                $v1 = $_POST['vrsta_traga'] ?? null;
                $v2 = $_POST['nacin_uzorkovanja'] ?? null;
                $v3 = $_POST['uslovi_cuvanja'] ?? null;
                $v4 = $_POST['kolicina'] ?? null;
                $stmt->bind_param('issss', $dokazId, $v1, $v2, $v3, $v4);
                $stmt->execute();
                $stmt->close();
                break;
            case 'ORUZJE':
                $stmt = $conn->prepare("INSERT INTO oruzje (id_dokaz, vrsta_oruzja, marka, model, kalibar, serijski_br) VALUES (?, ?, ?, ?, ?, ?)");
                $v1 = $_POST['vrsta_oruzja'] ?? null;
                $v2 = $_POST['marka'] ?? null;
                $v3 = $_POST['model_oruzja'] ?? null;
                $v4 = $_POST['kalibar'] ?? null;
                $v5 = $_POST['serijski_br'] ?? null;
                $stmt->bind_param('isssss', $dokazId, $v1, $v2, $v3, $v4, $v5);
                $stmt->execute();
                $stmt->close();
                break;
            case 'DOKUMENT':
                $stmt = $conn->prepare("INSERT INTO dokument_dokaz (id_dokaz, vrsta_dokumenta, jezik, broj_stranica) VALUES (?, ?, ?, ?)");
                $v1 = $_POST['vrsta_dokumenta'] ?? null;
                $v2 = $_POST['jezik'] ?? null;
                $v3 = !empty($_POST['broj_stranica']) ? (int)$_POST['broj_stranica'] : null;
                $stmt->bind_param('issi', $dokazId, $v1, $v2, $v3);
                $stmt->execute();
                $stmt->close();
                break;
            case 'ODECA':
                $stmt = $conn->prepare("INSERT INTO odeca (id_dokaz, velicina, vrsta_odevnog_predmeta, boja, stanje) VALUES (?, ?, ?, ?, ?)");
                $v1 = $_POST['velicina'] ?? null;
                $v2 = $_POST['vrsta_odevnog_predmeta'] ?? null;
                $v3 = $_POST['boja'] ?? null;
                $v4 = $_POST['stanje'] ?? null;
                $stmt->bind_param('issss', $dokazId, $v1, $v2, $v3, $v4);
                $stmt->execute();
                $stmt->close();
                break;
            case 'UZORAK':
                $stmt = $conn->prepare("INSERT INTO uzorak (id_dokaz, vrsta_uzorka, kolicina, jedinica_mere, nacin_uzorkovanja, uslovi_cuvanja) VALUES (?, ?, ?, ?, ?, ?)");
                $v1 = $_POST['vrsta_uzorka'] ?? null;
                $v2 = $_POST['kolicina_uzorka'] ?? null;
                $v3 = $_POST['jedinica_mere'] ?? null;
                $v4 = $_POST['nacin_uzorkovanja'] ?? null;
                $v5 = $_POST['uslovi_cuvanja'] ?? null;
                $stmt->bind_param('isssss', $dokazId, $v1, $v2, $v3, $v4, $v5);
                $stmt->execute();
                $stmt->close();
                break;
        }

        // INSERT u lanac čuvanja
        $stmt = $conn->prepare("INSERT INTO lanac_cuvanja (akcija, datum_vreme, napomena, dokaz_id, tehnicar_id) VALUES ('Prijem dokaza', NOW(), 'Dokaz evidentiran u sistemu', ?, ?)");
        $stmt->bind_param('ii', $dokazId, $tehnicarId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        flashSuccess('Dokaz uspešno kreiran: ' . $sifraDokaza);
        header("Location: ?page=dokaz-detalji&id={$dokazId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška pri kreiranju dokaza: ' . $e->getMessage());
        header('Location: ?page=dokaz-novi');
        exit;
    }
}

// ─── Izmena dokaza ──────────────────────────────────────────────────────────
if ($page === 'dokaz-izmeni') {
    requireRole('TEHNICAR', 'ADMINISTRATOR');

    $dokazId = (int)($_GET['id'] ?? 0);

    // Provera da dokaz postoji i nije arhiviran
    $stmt = $conn->prepare("SELECT status, tehnicar_id FROM dokaz WHERE id = ?");
    $stmt->bind_param('i', $dokazId);
    $stmt->execute();
    $dokaz = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dokaz) {
        flashError('Dokaz nije pronađen.');
        header('Location: ?page=dokazi');
        exit;
    }
    if ($dokaz['status'] === 'ARHIVIRANO') {
        flashError('Arhivirani dokaz se ne može menjati.');
        header("Location: ?page=dokaz-detalji&id={$dokazId}");
        exit;
    }

    $naziv               = trim($_POST['naziv'] ?? '');
    $opis                = trim($_POST['opis'] ?? '');
    $datumPronalaska     = $_POST['datum_pronalaska'] ?? null;
    $lokacijaPronalaska  = trim($_POST['lokacija_pronalaska'] ?? '');
    $lokacijaSkladistenja = trim($_POST['lokacija_skladistenja'] ?? '');
    $tipDokaza           = $_POST['tip_dokaza'] ?? '';

    if (empty($naziv)) {
        flashError('Naziv je obavezan.');
        header("Location: ?page=dokaz-izmeni&id={$dokazId}");
        exit;
    }

    $conn->begin_transaction();
    try {
        // UPDATE dokaz
        $stmt = $conn->prepare("UPDATE dokaz SET naziv = ?, opis = ?, datum_pronalaska = ?, lokacija_pronalaska = ?, lokacija_skladistenja = ? WHERE id = ?");
        $opisDb = $opis ?: null;
        $dpDb = $datumPronalaska ?: null;
        $lpDb = $lokacijaPronalaska ?: null;
        $lsDb = $lokacijaSkladistenja ?: null;
        $stmt->bind_param('sssssi', $naziv, $opisDb, $dpDb, $lpDb, $lsDb, $dokazId);
        $stmt->execute();
        $stmt->close();

        // Upsert ISA tabela (INSERT ... ON DUPLICATE KEY UPDATE)
        switch ($tipDokaza) {
            case 'BIOLOSKI_TRAG':
                $stmt = $conn->prepare("INSERT INTO bioloski_trag (id_dokaz, vrsta_traga, nacin_uzorkovanja, uslovi_cuvanja, kolicina) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE vrsta_traga=VALUES(vrsta_traga), nacin_uzorkovanja=VALUES(nacin_uzorkovanja), uslovi_cuvanja=VALUES(uslovi_cuvanja), kolicina=VALUES(kolicina)");
                $v1 = $_POST['vrsta_traga'] ?? null;
                $v2 = $_POST['nacin_uzorkovanja'] ?? null;
                $v3 = $_POST['uslovi_cuvanja'] ?? null;
                $v4 = $_POST['kolicina'] ?? null;
                $stmt->bind_param('issss', $dokazId, $v1, $v2, $v3, $v4);
                $stmt->execute();
                $stmt->close();
                break;
            case 'ORUZJE':
                $stmt = $conn->prepare("INSERT INTO oruzje (id_dokaz, vrsta_oruzja, marka, model, kalibar, serijski_br) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE vrsta_oruzja=VALUES(vrsta_oruzja), marka=VALUES(marka), model=VALUES(model), kalibar=VALUES(kalibar), serijski_br=VALUES(serijski_br)");
                $v1 = $_POST['vrsta_oruzja'] ?? null;
                $v2 = $_POST['marka'] ?? null;
                $v3 = $_POST['model_oruzja'] ?? null;
                $v4 = $_POST['kalibar'] ?? null;
                $v5 = $_POST['serijski_br'] ?? null;
                $stmt->bind_param('isssss', $dokazId, $v1, $v2, $v3, $v4, $v5);
                $stmt->execute();
                $stmt->close();
                break;
            case 'DOKUMENT':
                $stmt = $conn->prepare("INSERT INTO dokument_dokaz (id_dokaz, vrsta_dokumenta, jezik, broj_stranica) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE vrsta_dokumenta=VALUES(vrsta_dokumenta), jezik=VALUES(jezik), broj_stranica=VALUES(broj_stranica)");
                $v1 = $_POST['vrsta_dokumenta'] ?? null;
                $v2 = $_POST['jezik'] ?? null;
                $v3 = !empty($_POST['broj_stranica']) ? (int)$_POST['broj_stranica'] : null;
                $stmt->bind_param('issi', $dokazId, $v1, $v2, $v3);
                $stmt->execute();
                $stmt->close();
                break;
            case 'ODECA':
                $stmt = $conn->prepare("INSERT INTO odeca (id_dokaz, velicina, vrsta_odevnog_predmeta, boja, stanje) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE velicina=VALUES(velicina), vrsta_odevnog_predmeta=VALUES(vrsta_odevnog_predmeta), boja=VALUES(boja), stanje=VALUES(stanje)");
                $v1 = $_POST['velicina'] ?? null;
                $v2 = $_POST['vrsta_odevnog_predmeta'] ?? null;
                $v3 = $_POST['boja'] ?? null;
                $v4 = $_POST['stanje'] ?? null;
                $stmt->bind_param('issss', $dokazId, $v1, $v2, $v3, $v4);
                $stmt->execute();
                $stmt->close();
                break;
            case 'UZORAK':
                $stmt = $conn->prepare("INSERT INTO uzorak (id_dokaz, vrsta_uzorka, kolicina, jedinica_mere, nacin_uzorkovanja, uslovi_cuvanja) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE vrsta_uzorka=VALUES(vrsta_uzorka), kolicina=VALUES(kolicina), jedinica_mere=VALUES(jedinica_mere), nacin_uzorkovanja=VALUES(nacin_uzorkovanja), uslovi_cuvanja=VALUES(uslovi_cuvanja)");
                $v1 = $_POST['vrsta_uzorka'] ?? null;
                $v2 = $_POST['kolicina_uzorka'] ?? null;
                $v3 = $_POST['jedinica_mere'] ?? null;
                $v4 = $_POST['nacin_uzorkovanja'] ?? null;
                $v5 = $_POST['uslovi_cuvanja'] ?? null;
                $stmt->bind_param('isssss', $dokazId, $v1, $v2, $v3, $v4, $v5);
                $stmt->execute();
                $stmt->close();
                break;
        }

        $conn->commit();
        flashSuccess('Dokaz uspešno izmenjen.');
        header("Location: ?page=dokaz-detalji&id={$dokazId}");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška pri izmeni: ' . $e->getMessage());
        header("Location: ?page=dokaz-izmeni&id={$dokazId}");
        exit;
    }
}

// ─── Arhiviranje dokaza ─────────────────────────────────────────────────────
if ($page === 'dokaz-detalji' && $action === 'arhiviraj') {
    requireRole('ADMINISTRATOR');

    $dokazId = (int)($_GET['id'] ?? 0);

    // Provera statusa
    $stmt = $conn->prepare("SELECT status, tehnicar_id FROM dokaz WHERE id = ?");
    $stmt->bind_param('i', $dokazId);
    $stmt->execute();
    $dokaz = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dokaz || $dokaz['status'] === 'ARHIVIRANO') {
        flashError('Dokaz je već arhiviran ili ne postoji.');
        header("Location: ?page=dokaz-detalji&id={$dokazId}");
        exit;
    }

    // UPDATE status
    $stmt = $conn->prepare("UPDATE dokaz SET status = 'ARHIVIRANO' WHERE id = ?");
    $stmt->bind_param('i', $dokazId);
    $stmt->execute();
    $stmt->close();

    // Lanac čuvanja
    $tehnicarId = $dokaz['tehnicar_id'];
    $stmt = $conn->prepare("INSERT INTO lanac_cuvanja (akcija, datum_vreme, napomena, dokaz_id, tehnicar_id) VALUES ('Arhiviranje', NOW(), 'Dokaz arhiviran', ?, ?)");
    $stmt->bind_param('ii', $dokazId, $tehnicarId);
    $stmt->execute();
    $stmt->close();

    flashSuccess('Dokaz uspešno arhiviran.');
    header("Location: ?page=dokaz-detalji&id={$dokazId}");
    exit;
}

// ─── Verifikacija lanca čuvanja ────────────────────────────────────────────
if ($page === 'dokaz-detalji' && $action === 'verifikuj') {
    requireRole('TEHNICAR', 'ISTRAZITELJ', 'ADMINISTRATOR');

    $dokazId = (int)($_GET['id'] ?? 0);

    // Učitaj dokaz
    $stmt = $conn->prepare("SELECT sifra_dokaza, status, tehnicar_id FROM dokaz WHERE id = ?");
    $stmt->bind_param('i', $dokazId);
    $stmt->execute();
    $dokaz = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dokaz) {
        flashError('Dokaz nije pronađen.');
        header('Location: ?page=dokazi');
        exit;
    }

    // Učitaj sve zapise lanca hronološki
    $stmt = $conn->prepare("SELECT akcija, datum_vreme FROM lanac_cuvanja WHERE dokaz_id = ? ORDER BY datum_vreme ASC, id ASC");
    $stmt->bind_param('i', $dokazId);
    $stmt->execute();
    $zapisi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $validan = true;
    $razlog  = '';

    // Provera 1: mora postojati bar jedan zapis sa akcijom "Prijem dokaza"
    if (empty($zapisi)) {
        $validan = false;
        $razlog  = 'Lanac čuvanja nema nijedan zapis.';
    } elseif ($zapisi[0]['akcija'] !== 'Prijem dokaza') {
        $validan = false;
        $razlog  = 'Prvi zapis u lancu nije „Prijem dokaza".';
    }

    // Provera 2: hronološki redosled (svaki sledeći datum >= prethodnog)
    if ($validan) {
        for ($i = 1; $i < count($zapisi); $i++) {
            if ($zapisi[$i]['datum_vreme'] < $zapisi[$i - 1]['datum_vreme']) {
                $validan = false;
                $razlog  = 'Narušen hronološki redosled između zapisa #' . $i . ' i #' . ($i + 1) . '.';
                break;
            }
        }
    }

    // Provera 3: validnost toka statusa
    // Dozvoljeni prelazi na osnovu akcija koje menjaju status
    if ($validan) {
        $trenutniStatus = 'U_SKLADISTU';
        $akcijaNaStatus = [
            'Prijem dokaza'    => 'U_SKLADISTU',
            'Izdavanje dokaza' => 'IZDATO_ZA_ANALIZU',
            'Povraćaj dokaza'  => 'VRACENO',
            'Arhiviranje'      => 'ARHIVIRANO',
        ];
        // Dozvoljeni prelazi: iz kog statusa → u koji status
        $dozvoljenoPrelazi = [
            'U_SKLADISTU'       => ['IZDATO_ZA_ANALIZU', 'ARHIVIRANO'],
            'IZDATO_ZA_ANALIZU' => ['VRACENO'],
            'VRACENO'           => ['ARHIVIRANO'],
        ];

        for ($i = 1; $i < count($zapisi); $i++) {
            $akcija = $zapisi[$i]['akcija'];

            // Akcije koje ne menjaju status — preskoči proveru prelaza
            if (!isset($akcijaNaStatus[$akcija])) {
                continue;
            }

            $noviStatus = $akcijaNaStatus[$akcija];
            $dozvoljen  = $dozvoljenoPrelazi[$trenutniStatus] ?? [];

            if (!in_array($noviStatus, $dozvoljen)) {
                $validan = false;
                $razlog  = 'Nedozvoljen prelaz statusa: ' . badgeLabel($trenutniStatus) . ' → ' . badgeLabel($noviStatus) . '.';
                break;
            }
            $trenutniStatus = $noviStatus;
        }
    }

    // Ako lanac nije validan — označi dokaz kao kompromitovan
    if (!$validan && $dokaz['status'] !== 'KOMPROMITOVAN') {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE dokaz SET status = 'KOMPROMITOVAN' WHERE id = ?");
            $stmt->bind_param('i', $dokazId);
            $stmt->execute();
            $stmt->close();

            $tehnicarId = $dokaz['tehnicar_id'];
            $stmt = $conn->prepare("INSERT INTO lanac_cuvanja (akcija, datum_vreme, napomena, dokaz_id, tehnicar_id) VALUES ('Kompromitovanje', NOW(), ?, ?, ?)");
            $napomenaKomp = 'Verifikacija lanca neuspešna: ' . $razlog;
            $stmt->bind_param('sii', $napomenaKomp, $dokazId, $tehnicarId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
        }
    }

    // Sačuvaj rezultat u sesiju za prikaz u modalu
    $_SESSION['verifikacija_rezultat'] = [
        'sifra'   => $dokaz['sifra_dokaza'],
        'validan' => $validan,
        'razlog'  => $validan
            ? 'Lanac čuvanja je kompletan i hronološki konzistentan. Svi zapisi su ispravno evidentirani. Nema prekida u lancu.'
            : $razlog,
        'datum'   => date('d.m.Y \u H:i'),
        'korisnik' => $_SESSION['ime'][0] . '. ' . $_SESSION['prezime'],
    ];

    header("Location: ?page=dokaz-detalji&id={$dokazId}");
    exit;
}

// ─── Obrada zahteva za dokaze ───────────────────────────────────────────────
if ($page === 'dokazi-zahtevi') {
    requireRole('TEHNICAR', 'ADMINISTRATOR');

    $zahtevId = (int)($_POST['zahtev_id'] ?? 0);
    $odluka   = $_POST['odluka'] ?? '';
    $napomena = trim($_POST['napomena'] ?? '');

    if ($zahtevId < 1 || !in_array($odluka, ['ODOBREN', 'ODBIJEN'])) {
        flashError('Nevalidni podaci.');
        header('Location: ?page=dokazi-zahtevi');
        exit;
    }

    // Proveri da je zahtev NA_CEKANJU
    $stmt = $conn->prepare("SELECT z.status, z.tip, z.dokaz_id, d.tehnicar_id FROM zahtev_za_dokaz z JOIN dokaz d ON d.id = z.dokaz_id WHERE z.id = ?");
    $stmt->bind_param('i', $zahtevId);
    $stmt->execute();
    $zahtev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$zahtev || $zahtev['status'] !== 'NA_CEKANJU') {
        flashError('Zahtev nije na čekanju ili ne postoji.');
        header('Location: ?page=dokazi-zahtevi');
        exit;
    }

    $pushVestakId      = null;
    $pushSadrzajVestak = null;
    $pushObavestenjeId = null;

    $conn->begin_transaction();
    try {
        // UPDATE zahtev
        $tehnicarIdZahtev = $_SESSION['user_id'];
        $napomenaDb = $napomena ?: null;
        $stmt = $conn->prepare("UPDATE zahtev_za_dokaz SET status = ?, datum_obrade = NOW(), tehnicar_id = ?, napomena = ? WHERE id = ?");
        $stmt->bind_param('sisi', $odluka, $tehnicarIdZahtev, $napomenaDb, $zahtevId);
        $stmt->execute();
        $stmt->close();

        // Ako je ODOBREN — ažuriraj status dokaza i dodaj u lanac čuvanja
        if ($odluka === 'ODOBREN') {
            $dokazId = $zahtev['dokaz_id'];
            $tehnicarIdDokaz = $zahtev['tehnicar_id'];

            if ($zahtev['tip'] === 'PREDAJA') {
                // Predaja → izdavanje za analizu
                $stmt = $conn->prepare("UPDATE dokaz SET status = 'IZDATO_ZA_ANALIZU' WHERE id = ?");
                $stmt->bind_param('i', $dokazId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO lanac_cuvanja (akcija, datum_vreme, napomena, dokaz_id, tehnicar_id) VALUES ('Izdavanje dokaza', NOW(), 'Dokaz izdat za analizu na osnovu odobrenog zahteva', ?, ?)");
                $stmt->bind_param('ii', $dokazId, $tehnicarIdDokaz);
                $stmt->execute();
                $stmt->close();

                // Obavesti veštaka da je dokaz predat i analiza može da počne
                $stmtA = $conn->prepare("SELECT id, vestak_id FROM zahtev_za_analizu WHERE dokaz_id = ? AND vestak_id IS NOT NULL AND status IN ('KREIRAN','DODELJEN') ORDER BY id DESC LIMIT 1");
                $stmtA->bind_param('i', $dokazId);
                $stmtA->execute();
                $analiza = $stmtA->get_result()->fetch_assoc();
                $stmtA->close();

                if ($analiza) {
                    $sadrzajVestak = 'Dokaz je predat — dodeljena vam je analiza #' . $analiza['id'];
                    $stmt = $conn->prepare("INSERT INTO obavestenje (sadrzaj, tip, datum_vreme, korisnik_id, zahtev_id) VALUES (?, 'DODELA', NOW(), ?, ?)");
                    $stmt->bind_param('sii', $sadrzajVestak, $analiza['vestak_id'], $analiza['id']);
                    $stmt->execute();
                    $pushVestakId      = (int)$analiza['vestak_id'];
                    $pushSadrzajVestak = $sadrzajVestak;
                    $pushObavestenjeId = $conn->insert_id;
                    $stmt->close();
                }
            } elseif ($zahtev['tip'] === 'POVRACAJ') {
                // Povraćaj → vraćen u skladište
                $stmt = $conn->prepare("UPDATE dokaz SET status = 'VRACENO' WHERE id = ?");
                $stmt->bind_param('i', $dokazId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO lanac_cuvanja (akcija, datum_vreme, napomena, dokaz_id, tehnicar_id) VALUES ('Povraćaj dokaza', NOW(), 'Dokaz vraćen na osnovu odobrenog zahteva', ?, ?)");
                $stmt->bind_param('ii', $dokazId, $tehnicarIdDokaz);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
        if ($pushObavestenjeId !== null) {
            pushNotifikacija($pushVestakId, $pushSadrzajVestak, $pushObavestenjeId);
        }
        flashSuccess('Zahtev uspešno obrađen.');
    } catch (Exception $e) {
        $conn->rollback();
        flashError('Greška pri obradi zahteva: ' . $e->getMessage());
    }
    header('Location: ?page=dokazi-zahtevi');
    exit;
}
