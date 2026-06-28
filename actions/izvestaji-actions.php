<?php
/**
 * actions/izvestaji-actions.php — Generisanje PDF izveštaja
 *
 * Dva tipa izveštaja:
 * 1. Izveštaj o lancu čuvanja za pojedinačni dokaz
 * 2. Zbirni izveštaj o integritetu dokaza za predmet
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ─── Izveštaj o lancu čuvanja za pojedinačni dokaz ─────────────────────────
if ($action === 'izvestaj-dokaz') {
    requireRole('TEHNICAR', 'ISTRAZITELJ', 'ADMINISTRATOR');

    $dokazId = (int)($_GET['id'] ?? 0);

    // Učitaj dokaz sa relacijama
    $stmt = $conn->prepare("
        SELECT d.*, p.naziv AS predmet_naziv,
               k.ime AS tehnicar_ime, k.prezime AS tehnicar_prezime
        FROM dokaz d
        JOIN predmet p ON p.id = d.predmet_id
        JOIN tehnicar_za_dokaze t ON t.id_korisnik = d.tehnicar_id
        JOIN korisnik k ON k.id = t.id_korisnik
        WHERE d.id = ?
    ");
    $stmt->bind_param('i', $dokazId);
    $stmt->execute();
    $dokaz = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$dokaz) {
        flashError('Dokaz nije pronađen.');
        header('Location: ?page=dokazi');
        exit;
    }

    // Učitaj ISA podatke
    $isaData = null;
    switch ($dokaz['tip_dokaza']) {
        case 'BIOLOSKI_TRAG':
            $stmt = $conn->prepare("SELECT * FROM bioloski_trag WHERE id_dokaz = ?"); break;
        case 'ORUZJE':
            $stmt = $conn->prepare("SELECT * FROM oruzje WHERE id_dokaz = ?"); break;
        case 'DOKUMENT':
            $stmt = $conn->prepare("SELECT * FROM dokument_dokaz WHERE id_dokaz = ?"); break;
        case 'ODECA':
            $stmt = $conn->prepare("SELECT * FROM odeca WHERE id_dokaz = ?"); break;
        case 'UZORAK':
            $stmt = $conn->prepare("SELECT * FROM uzorak WHERE id_dokaz = ?"); break;
    }
    if (isset($stmt)) {
        $stmt->bind_param('i', $dokazId);
        $stmt->execute();
        $isaData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    // Učitaj lanac čuvanja hronološki
    $stmt = $conn->prepare("
        SELECT lc.*, k.ime, k.prezime
        FROM lanac_cuvanja lc
        JOIN korisnik k ON k.id = lc.tehnicar_id
        WHERE lc.dokaz_id = ?
        ORDER BY lc.datum_vreme ASC, lc.id ASC
    ");
    $stmt->bind_param('i', $dokazId);
    $stmt->execute();
    $lanacRes = $stmt->get_result();
    $zapisi = [];
    while ($row = $lanacRes->fetch_assoc()) {
        $zapisi[] = $row;
    }
    $stmt->close();

    // ─── Verifikacija integriteta lanca ─────────────────────────────────────
    $validan = true;
    $razlog  = 'Lanac čuvanja je kompletan i hronološki konzistentan.';

    if (empty($zapisi)) {
        $validan = false;
        $razlog  = 'Lanac čuvanja nema nijedan zapis.';
    } elseif ($zapisi[0]['akcija'] !== 'Prijem dokaza') {
        $validan = false;
        $razlog  = 'Prvi zapis u lancu nije „Prijem dokaza".';
    }

    if ($validan) {
        for ($i = 1; $i < count($zapisi); $i++) {
            if ($zapisi[$i]['datum_vreme'] < $zapisi[$i - 1]['datum_vreme']) {
                $validan = false;
                $razlog  = 'Narušen hronološki redosled između zapisa #' . $i . ' i #' . ($i + 1) . '.';
                break;
            }
        }
    }

    if ($validan) {
        $trenutniStatus = 'U_SKLADISTU';
        $akcijaNaStatus = [
            'Prijem dokaza'    => 'U_SKLADISTU',
            'Izdavanje dokaza' => 'IZDATO_ZA_ANALIZU',
            'Povraćaj dokaza'  => 'VRACENO',
            'Arhiviranje'      => 'ARHIVIRANO',
        ];
        $dozvoljenoPrelazi = [
            'U_SKLADISTU'       => ['IZDATO_ZA_ANALIZU', 'ARHIVIRANO'],
            'IZDATO_ZA_ANALIZU' => ['VRACENO'],
            'VRACENO'           => ['ARHIVIRANO'],
        ];

        for ($i = 1; $i < count($zapisi); $i++) {
            $akcija = $zapisi[$i]['akcija'];
            if (!isset($akcijaNaStatus[$akcija])) continue;
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

    // Ako je dokaz već označen kao kompromitovan
    if ($dokaz['status'] === 'KOMPROMITOVAN') {
        $validan = false;
        $razlog  = 'Dokaz je označen kao kompromitovan.';
    }

    // ─── Mapiranje ISA kolona na labele ─────────────────────────────────────
    $labelMap = [
        'vrsta_traga' => 'Vrsta traga', 'nacin_uzorkovanja' => 'Način uzorkovanja',
        'uslovi_cuvanja' => 'Uslovi čuvanja', 'kolicina' => 'Količina',
        'vrsta_oruzja' => 'Vrsta oružja', 'marka' => 'Marka', 'model' => 'Model',
        'kalibar' => 'Kalibar', 'serijski_br' => 'Serijski broj',
        'vrsta_dokumenta' => 'Vrsta dokumenta', 'jezik' => 'Jezik', 'broj_stranica' => 'Broj stranica',
        'velicina' => 'Veličina', 'vrsta_odevnog_predmeta' => 'Vrsta odevnog predmeta',
        'boja' => 'Boja', 'stanje' => 'Stanje',
        'vrsta_uzorka' => 'Vrsta uzorka', 'jedinica_mere' => 'Jedinica mere',
    ];

    // ─── Generisanje PDF-a ──────────────────────────────────────────────────
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->setFontSubsetting(true);
    $pdf->SetCreator('ForenzIIS');
    $pdf->SetAuthor('ForenzIIS — Forenzički informacioni sistem');
    $pdf->SetTitle('Izveštaj o lancu čuvanja — ' . $dokaz['sifra_dokaza']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // Zaglavlje izveštaja
    $pdf->SetFont('freeserif', 'B', 16);
    $pdf->Cell(0, 10, 'IZVEŠTAJ O LANCU ČUVANJA', 0, 1, 'C');
    $pdf->SetFont('freeserif', '', 10);
    $pdf->Cell(0, 6, 'ForenzIIS — Forenzički informacioni sistem', 0, 1, 'C');
    $pdf->Ln(4);
    $pdf->SetDrawColor(100, 100, 100);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(6);

    // Opšti podaci o dokazu
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, '1. Opšti podaci o dokazu', 0, 1);
    $pdf->Ln(2);

    $pdf->SetFont('freeserif', '', 10);
    $opsti = [
        'Šifra dokaza'        => $dokaz['sifra_dokaza'],
        'Naziv'               => $dokaz['naziv'],
        'Tip dokaza'          => tipDokazaLabel($dokaz['tip_dokaza']),
        'Status'              => badgeLabel($dokaz['status']),
        'Predmet'             => $dokaz['predmet_naziv'],
        'Datum prijema'       => formatDatumVreme($dokaz['datum_prijema']),
        'Datum pronalaska'    => formatDatumVreme($dokaz['datum_pronalaska']),
        'Lokacija pronalaska' => $dokaz['lokacija_pronalaska'] ?: '—',
        'Lokacija skladištenja' => $dokaz['lokacija_skladistenja'] ?: '—',
        'Tehničar'            => $dokaz['tehnicar_ime'] . ' ' . $dokaz['tehnicar_prezime'],
        'Opis'                => $dokaz['opis'] ?: '—',
    ];

    foreach ($opsti as $label => $value) {
        $pdf->SetFont('freeserif', 'B', 9);
        $pdf->Cell(50, 6, $label . ':', 0, 0);
        $pdf->SetFont('freeserif', '', 9);
        $pdf->MultiCell(0, 6, $value, 0, 'L');
    }

    // Specifična obeležja
    if ($isaData) {
        $pdf->Ln(4);
        $pdf->SetFont('freeserif', 'B', 12);
        $pdf->Cell(0, 8, '2. Specifična obeležja — ' . tipDokazaLabel($dokaz['tip_dokaza']), 0, 1);
        $pdf->Ln(2);

        foreach ($isaData as $key => $val) {
            if ($key === 'id_dokaz') continue;
            $label = $labelMap[$key] ?? ucfirst(str_replace('_', ' ', $key));
            $pdf->SetFont('freeserif', 'B', 9);
            $pdf->Cell(50, 6, $label . ':', 0, 0);
            $pdf->SetFont('freeserif', '', 9);
            $pdf->Cell(0, 6, $val ?: '—', 0, 1);
        }
    }

    // Lanac čuvanja
    $pdf->Ln(4);
    $pdf->SetFont('freeserif', 'B', 12);
    $sekcijaBroj = $isaData ? '3' : '2';
    $pdf->Cell(0, 8, $sekcijaBroj . '. Lanac čuvanja', 0, 1);
    $pdf->Ln(2);

    if (!empty($zapisi)) {
        // Zaglavlje tabele
        $pdf->SetFont('freeserif', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Datum i vreme', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Akcija', 1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Korisnik', 1, 0, 'C', true);
        $pdf->Cell(55, 7, 'Napomena', 1, 1, 'C', true);

        $pdf->SetFont('freeserif', '', 8);
        foreach ($zapisi as $i => $z) {
            $pdf->Cell(10, 6, $i + 1, 1, 0, 'C');
            $pdf->Cell(40, 6, formatDatumVreme($z['datum_vreme']), 1, 0, 'C');
            $pdf->Cell(40, 6, $z['akcija'], 1, 0, 'L');
            $pdf->Cell(35, 6, $z['ime'] . ' ' . $z['prezime'], 1, 0, 'L');
            $pdf->Cell(55, 6, mb_strimwidth($z['napomena'] ?? '—', 0, 45, '...'), 1, 1, 'L');
        }
    } else {
        $pdf->SetFont('freeserif', 'I', 9);
        $pdf->Cell(0, 6, 'Nema zapisa u lancu čuvanja.', 0, 1);
    }

    // Rezultat verifikacije
    $pdf->Ln(4);
    $sledecaSekcija = $isaData ? '4' : '3';
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, $sledecaSekcija . '. Rezultat verifikacije integriteta', 0, 1);
    $pdf->Ln(2);

    if ($validan) {
        $pdf->SetTextColor(0, 128, 0);
        $pdf->SetFont('freeserif', 'B', 11);
        $pdf->Cell(0, 8, 'VALIDAN', 0, 1);
    } else {
        $pdf->SetTextColor(200, 0, 0);
        $pdf->SetFont('freeserif', 'B', 11);
        $pdf->Cell(0, 8, 'KOMPROMITOVAN', 0, 1);
    }

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->MultiCell(0, 6, $razlog, 0, 'L');

    // Datum generisanja
    $pdf->Ln(8);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(4);
    $pdf->SetFont('freeserif', 'I', 8);
    $pdf->Cell(0, 5, 'Izveštaj generisan: ' . date('d.m.Y \u H:i') . ' | Generisao: ' . $_SESSION['ime'] . ' ' . $_SESSION['prezime'], 0, 1, 'C');

    // Preuzimanje PDF-a
    $imeFajla = 'Izvestaj_lanac_cuvanja_' . $dokaz['sifra_dokaza'] . '_' . date('Ymd') . '.pdf';
    $pdf->Output($imeFajla, 'D');
    exit;
}

// ─── Zbirni izveštaj o integritetu dokaza za predmet ────────────────────────
if ($action === 'zbirni-izvestaj') {
    requireRole('TEHNICAR', 'ISTRAZITELJ', 'ADMINISTRATOR');

    $predmetId = (int)($_GET['id'] ?? 0);

    // Učitaj predmet
    $stmt = $conn->prepare("SELECT * FROM predmet WHERE id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $predmet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$predmet) {
        flashError('Predmet nije pronađen.');
        header('Location: ?page=predmeti');
        exit;
    }

    // Učitaj sve dokaze za predmet
    $stmt = $conn->prepare("
        SELECT d.id, d.sifra_dokaza, d.naziv, d.tip_dokaza, d.status, d.datum_prijema,
               d.tehnicar_id,
               k.ime AS tehnicar_ime, k.prezime AS tehnicar_prezime
        FROM dokaz d
        JOIN tehnicar_za_dokaze t ON t.id_korisnik = d.tehnicar_id
        JOIN korisnik k ON k.id = t.id_korisnik
        WHERE d.predmet_id = ?
        ORDER BY d.datum_prijema ASC
    ");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $dokaziRes = $stmt->get_result();
    $dokaziLista = [];
    while ($row = $dokaziRes->fetch_assoc()) {
        $dokaziLista[] = $row;
    }
    $stmt->close();

    // Za svaki dokaz — broj zapisa u lancu i verifikacija
    $statistika = ['ukupno' => count($dokaziLista), 'validni' => 0, 'kompromitovani' => 0];

    foreach ($dokaziLista as &$d) {
        // Broj zapisa u lancu čuvanja
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM lanac_cuvanja WHERE dokaz_id = ?");
        $stmt->bind_param('i', $d['id']);
        $stmt->execute();
        $d['br_zapisa_lanca'] = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Verifikacija integriteta
        $stmt = $conn->prepare("SELECT akcija, datum_vreme FROM lanac_cuvanja WHERE dokaz_id = ? ORDER BY datum_vreme ASC, id ASC");
        $stmt->bind_param('i', $d['id']);
        $stmt->execute();
        $zapisiD = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $d['validan'] = true;

        // Ako je status već kompromitovan
        if ($d['status'] === 'KOMPROMITOVAN') {
            $d['validan'] = false;
        } else {
            // Ista logika verifikacije
            if (empty($zapisiD)) {
                $d['validan'] = false;
            } elseif ($zapisiD[0]['akcija'] !== 'Prijem dokaza') {
                $d['validan'] = false;
            }

            if ($d['validan']) {
                for ($i = 1; $i < count($zapisiD); $i++) {
                    if ($zapisiD[$i]['datum_vreme'] < $zapisiD[$i - 1]['datum_vreme']) {
                        $d['validan'] = false;
                        break;
                    }
                }
            }

            if ($d['validan']) {
                $trenutniStatus = 'U_SKLADISTU';
                $akcijaNaStatus = [
                    'Prijem dokaza'    => 'U_SKLADISTU',
                    'Izdavanje dokaza' => 'IZDATO_ZA_ANALIZU',
                    'Povraćaj dokaza'  => 'VRACENO',
                    'Arhiviranje'      => 'ARHIVIRANO',
                ];
                $dozvoljenoPrelazi = [
                    'U_SKLADISTU'       => ['IZDATO_ZA_ANALIZU', 'ARHIVIRANO'],
                    'IZDATO_ZA_ANALIZU' => ['VRACENO'],
                    'VRACENO'           => ['ARHIVIRANO'],
                ];
                for ($i = 1; $i < count($zapisiD); $i++) {
                    $akcija = $zapisiD[$i]['akcija'];
                    if (!isset($akcijaNaStatus[$akcija])) continue;
                    $noviStatus = $akcijaNaStatus[$akcija];
                    $dozvoljen  = $dozvoljenoPrelazi[$trenutniStatus] ?? [];
                    if (!in_array($noviStatus, $dozvoljen)) {
                        $d['validan'] = false;
                        break;
                    }
                    $trenutniStatus = $noviStatus;
                }
            }
        }

        if ($d['validan']) {
            $statistika['validni']++;
        } else {
            $statistika['kompromitovani']++;
        }
    }
    unset($d);

    // ─── Generisanje PDF-a ──────────────────────────────────────────────────
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->setFontSubsetting(true);
    $pdf->SetCreator('ForenzIIS');
    $pdf->SetAuthor('ForenzIIS — Forenzički informacioni sistem');
    $pdf->SetTitle('Zbirni izveštaj — ' . $predmet['naziv']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // Zaglavlje
    $pdf->SetFont('freeserif', 'B', 16);
    $pdf->Cell(0, 10, 'ZBIRNI IZVEŠTAJ O INTEGRITETU DOKAZA', 0, 1, 'C');
    $pdf->SetFont('freeserif', '', 10);
    $pdf->Cell(0, 6, 'ForenzIIS — Forenzički informacioni sistem', 0, 1, 'C');
    $pdf->Ln(4);
    $pdf->SetDrawColor(100, 100, 100);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(6);

    // Podaci o predmetu
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, '1. Podaci o predmetu', 0, 1);
    $pdf->Ln(2);

    $pdf->SetFont('freeserif', '', 10);
    $predmetInfo = [
        'Naziv predmeta'  => $predmet['naziv'],
        'Status'          => badgeLabel($predmet['status']),
        'Faza'            => fazaLabel($predmet['faza']),
        'Datum otvaranja' => formatDatumVreme($predmet['datum_otvaranja']),
    ];

    foreach ($predmetInfo as $label => $value) {
        $pdf->SetFont('freeserif', 'B', 9);
        $pdf->Cell(45, 6, $label . ':', 0, 0);
        $pdf->SetFont('freeserif', '', 9);
        $pdf->Cell(0, 6, $value, 0, 1);
    }

    // Sumarna statistika
    $pdf->Ln(4);
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, '2. Sumarna statistika', 0, 1);
    $pdf->Ln(2);

    $pdf->SetFont('freeserif', '', 10);
    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->Cell(45, 6, 'Ukupno dokaza:', 0, 0);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->Cell(0, 6, $statistika['ukupno'], 0, 1);

    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->Cell(45, 6, 'Validnih:', 0, 0);
    $pdf->SetTextColor(0, 128, 0);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->Cell(0, 6, $statistika['validni'], 0, 1);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->Cell(45, 6, 'Kompromitovanih:', 0, 0);
    $pdf->SetTextColor(200, 0, 0);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->Cell(0, 6, $statistika['kompromitovani'], 0, 1);
    $pdf->SetTextColor(0, 0, 0);

    // Lista dokaza
    $pdf->Ln(4);
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, '3. Lista dokaza', 0, 1);
    $pdf->Ln(2);

    if (!empty($dokaziLista)) {
        $pdf->SetFont('freeserif', 'B', 8);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Šifra', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Naziv', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Tip', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Status', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Zapisi', 1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Integritet', 1, 1, 'C', true);

        $pdf->SetFont('freeserif', '', 8);
        foreach ($dokaziLista as $i => $d) {
            $pdf->Cell(10, 6, $i + 1, 1, 0, 'C');
            $pdf->Cell(25, 6, $d['sifra_dokaza'], 1, 0, 'L');
            $pdf->Cell(40, 6, mb_strimwidth($d['naziv'], 0, 30, '...'), 1, 0, 'L');
            $pdf->Cell(25, 6, tipDokazaLabel($d['tip_dokaza']), 1, 0, 'C');
            $pdf->Cell(25, 6, badgeLabel($d['status']), 1, 0, 'C');
            $pdf->Cell(20, 6, $d['br_zapisa_lanca'], 1, 0, 'C');

            if ($d['validan']) {
                $pdf->SetTextColor(0, 128, 0);
                $pdf->Cell(35, 6, 'VALIDAN', 1, 1, 'C');
            } else {
                $pdf->SetTextColor(200, 0, 0);
                $pdf->Cell(35, 6, 'KOMPROMITOVAN', 1, 1, 'C');
            }
            $pdf->SetTextColor(0, 0, 0);
        }
    } else {
        $pdf->SetFont('freeserif', 'I', 9);
        $pdf->Cell(0, 6, 'Nema dokaza za ovaj predmet.', 0, 1);
    }

    // Datum generisanja
    $pdf->Ln(8);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(4);
    $pdf->SetFont('freeserif', 'I', 8);
    $pdf->Cell(0, 5, 'Izveštaj generisan: ' . date('d.m.Y \u H:i') . ' | Generisao: ' . $_SESSION['ime'] . ' ' . $_SESSION['prezime'], 0, 1, 'C');

    $imeFajla = 'Zbirni_izvestaj_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $predmet['naziv']) . '_' . date('Ymd') . '.pdf';
    $pdf->Output($imeFajla, 'D');
    exit;
}
