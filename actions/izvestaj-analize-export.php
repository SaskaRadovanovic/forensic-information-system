<?php
/**
 * actions/izvestaj-analize-export.php — PDF export izveštaja o analizama
 *
 * GET parametri:
 *   sekcija    — status | vreme | kasnjenja | opterecenje | sve
 *   datum_od, datum_do, predmet_id, tip_analize — isti filteri kao na stranici
 *
 * Obrazac: isti TCPDF stil kao actions/izvestaji-actions.php (kolege).
 * Font: freeserif, margine: 15mm, tabele: sivi header (230,230,230), Output 'D'.
 */
requireLogin();
requireRole('ISTRAZITELJ', 'ADMINISTRATOR');

$uloga  = $_SESSION['uloga'];
$userId = $_SESSION['user_id'];

$sekcija         = $_GET['sekcija']     ?? 'sve';
$filterDatumOd   = $_GET['datum_od']    ?? '';
$filterDatumDo   = $_GET['datum_do']    ?? '';
$filterPredmetId = (int)($_GET['predmet_id'] ?? 0);
$filterTip       = $_GET['tip_analize'] ?? '';

$dozvoljeneSekcije = ['status', 'vreme', 'kasnjenja', 'opterecenje', 'sve'];
if (!in_array($sekcija, $dozvoljeneSekcije, true)) {
    $sekcija = 'sve';
}

// ─── Naziv predmeta za label filtera ──────────────────────────────────────────
$filterPredmetNaziv = '';
if ($filterPredmetId > 0) {
    $sp = $conn->prepare("SELECT naziv FROM predmet WHERE id = ?");
    $sp->bind_param('i', $filterPredmetId);
    $sp->execute();
    $spRow = $sp->get_result()->fetch_assoc();
    $sp->close();
    $filterPredmetNaziv = $spRow['naziv'] ?? '';
}

// ─── Zajednički WHERE (isti kao pages/izvestaj-analize.php) ───────────────────
$bWhere  = "WHERE 1=1";
$bParams = [];
$bTypes  = '';

if ($uloga === 'ISTRAZITELJ') {
    $bWhere   .= " AND z.istrazitelj_id = ?";
    $bParams[] = $userId;
    $bTypes   .= 'i';
}
if ($filterDatumOd !== '') {
    $bWhere   .= " AND DATE(z.datum_kreiranja) >= ?";
    $bParams[] = $filterDatumOd;
    $bTypes   .= 's';
}
if ($filterDatumDo !== '') {
    $bWhere   .= " AND DATE(z.datum_kreiranja) <= ?";
    $bParams[] = $filterDatumDo;
    $bTypes   .= 's';
}
if ($filterPredmetId > 0) {
    $bWhere   .= " AND z.predmet_id = ?";
    $bParams[] = $filterPredmetId;
    $bTypes   .= 'i';
}
if ($filterTip !== '') {
    $bWhere   .= " AND z.tip_analize = ?";
    $bParams[] = $filterTip;
    $bTypes   .= 's';
}

$exec = function (string $sql, array $extraParams = [], string $extraTypes = '') use ($conn, $bParams, $bTypes): array {
    $allParams = array_merge($bParams, $extraParams);
    $allTypes  = $bTypes . $extraTypes;
    $stmt = $conn->prepare($sql);
    if (!empty($allParams)) {
        $stmt->bind_param($allTypes, ...$allParams);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
};

// ─── Učitavanje podataka po zahtevanoj sekciji ─────────────────────────────────
$needsA = in_array($sekcija, ['status',     'sve']);
$needsB = in_array($sekcija, ['vreme',      'sve']);
$needsC = in_array($sekcija, ['kasnjenja',  'sve']);
$needsD = in_array($sekcija, ['opterecenje','sve']);

$dataA = $dataA2 = $dataB1 = $dataB2 = $dataB3 = $dataC = $dataC2 = $dataD = null;

if ($needsA) {
    $rows     = $exec("SELECT z.status, COUNT(*) AS broj FROM zahtev_za_analizu z $bWhere GROUP BY z.status");
    $byStatus = [];
    $ukupnoA  = 0;
    foreach ($rows as $r) {
        $byStatus[$r['status']] = (int)$r['broj'];
        $ukupnoA += (int)$r['broj'];
    }
    $dataA = ['byStatus' => $byStatus, 'ukupno' => $ukupnoA];

    if ($filterPredmetId > 0) {
        $dataA2 = $exec("
            SELECT z.id, z.tip_analize, z.status, z.rok,
                   k.ime AS vestak_ime, k.prezime AS vestak_prezime
            FROM zahtev_za_analizu z
            LEFT JOIN vestak v   ON v.id_korisnik = z.vestak_id
            LEFT JOIN korisnik k ON k.id = v.id_korisnik
            $bWhere
            ORDER BY z.rok ASC
        ");
    }
}

if ($needsB) {
    $whereB = "$bWhere AND z.status = 'ZAVRSEN'";
    $r1     = $exec("
        SELECT COUNT(*) AS ukupno,
               AVG(DATEDIFF(r.datum_unosa, z.datum_kreiranja))                                        AS prosek,
               MIN(DATEDIFF(r.datum_unosa, z.datum_kreiranja))                                        AS najbrze,
               MAX(DATEDIFF(r.datum_unosa, z.datum_kreiranja))                                        AS najsporije,
               SUM(CASE WHEN DATEDIFF(r.datum_unosa, z.datum_kreiranja) BETWEEN 0  AND 3  THEN 1 ELSE 0 END) AS d0_3,
               SUM(CASE WHEN DATEDIFF(r.datum_unosa, z.datum_kreiranja) BETWEEN 4  AND 7  THEN 1 ELSE 0 END) AS d4_7,
               SUM(CASE WHEN DATEDIFF(r.datum_unosa, z.datum_kreiranja) BETWEEN 8  AND 14 THEN 1 ELSE 0 END) AS d8_14,
               SUM(CASE WHEN DATEDIFF(r.datum_unosa, z.datum_kreiranja) >= 15              THEN 1 ELSE 0 END) AS d15p
        FROM zahtev_za_analizu z
        JOIN rezultat_analize r ON r.zahtev_id = z.id
        $whereB
    ");
    $dataB1 = $r1[0] ?? [];
    $dataB2 = $exec("
        SELECT z.tip_analize,
               COUNT(*) AS broj,
               AVG(DATEDIFF(r.datum_unosa, z.datum_kreiranja)) AS prosek,
               MIN(DATEDIFF(r.datum_unosa, z.datum_kreiranja)) AS najbrze,
               MAX(DATEDIFF(r.datum_unosa, z.datum_kreiranja)) AS najsporije
        FROM zahtev_za_analizu z
        JOIN rezultat_analize r ON r.zahtev_id = z.id
        $whereB
        GROUP BY z.tip_analize
        ORDER BY prosek DESC
    ");
    $dataB3 = $exec("
        SELECT v.id_korisnik, k.ime, k.prezime,
               COUNT(*) AS broj_analiza,
               AVG(DATEDIFF(r.datum_unosa, z.datum_kreiranja)) AS prosek,
               MIN(DATEDIFF(r.datum_unosa, z.datum_kreiranja)) AS najbrze,
               MAX(DATEDIFF(r.datum_unosa, z.datum_kreiranja)) AS najsporije
        FROM zahtev_za_analizu z
        JOIN rezultat_analize r ON r.zahtev_id = z.id
        JOIN vestak v            ON v.id_korisnik = z.vestak_id
        JOIN korisnik k          ON k.id = v.id_korisnik
        $whereB
        GROUP BY v.id_korisnik, k.ime, k.prezime
        ORDER BY prosek ASC
    ");
}

if ($needsC) {
    $whereC = "$bWhere AND (
        z.status = 'PREKORACEN'
        OR (z.status IN ('DODELJEN','U_TOKU') AND z.rok IS NOT NULL AND z.rok < CURDATE())
    )";
    $r1    = $exec("SELECT COUNT(*) AS ukupno, AVG(DATEDIFF(CURDATE(), z.rok)) AS prosek_kasnjenja FROM zahtev_za_analizu z $whereC");
    $lista = $exec("
        SELECT z.id, z.tip_analize, z.status, z.rok,
               DATEDIFF(CURDATE(), z.rok) AS dana_kasnjenja,
               p.naziv AS predmet_naziv,
               k.ime AS vestak_ime, k.prezime AS vestak_prezime
        FROM zahtev_za_analizu z
        JOIN predmet p ON p.id = z.predmet_id
        LEFT JOIN korisnik k ON k.id = z.vestak_id
        $whereC
        ORDER BY dana_kasnjenja DESC
    ");
    $dataC = ['agg' => $r1[0] ?? [], 'lista' => $lista];

    $dataC2 = $exec("
        SELECT DISTINCT
            z.id, z.tip_analize, z.status AS trenutni_status, z.rok,
            k.ime AS vestak_ime, k.prezime AS vestak_prezime,
            (SELECT MIN(isa2.datum_vreme) FROM istorija_statusa_analize isa2
             WHERE isa2.zahtev_id = z.id AND isa2.novi_status = 'PREKORACEN')  AS datum_kad_je_kasnio
        FROM zahtev_za_analizu z
        JOIN istorija_statusa_analize isa ON isa.zahtev_id = z.id AND isa.novi_status = 'PREKORACEN'
        LEFT JOIN vestak v   ON v.id_korisnik = z.vestak_id
        LEFT JOIN korisnik k ON k.id = v.id_korisnik
        $bWhere AND z.status != 'PREKORACEN'
        ORDER BY datum_kad_je_kasnio DESC
    ");
}

if ($needsD) {
    $dOnExtra = "";
    $dParams  = [];
    $dTypes   = '';
    if ($uloga === 'ISTRAZITELJ') { $dOnExtra .= " AND z.istrazitelj_id = ?"; $dParams[] = $userId;         $dTypes .= 'i'; }
    if ($filterDatumOd !== '')    { $dOnExtra .= " AND DATE(z.datum_kreiranja) >= ?"; $dParams[] = $filterDatumOd; $dTypes .= 's'; }
    if ($filterDatumDo !== '')    { $dOnExtra .= " AND DATE(z.datum_kreiranja) <= ?"; $dParams[] = $filterDatumDo; $dTypes .= 's'; }
    if ($filterPredmetId > 0)     { $dOnExtra .= " AND z.predmet_id = ?"; $dParams[] = $filterPredmetId;    $dTypes .= 'i'; }
    if ($filterTip !== '')         { $dOnExtra .= " AND z.tip_analize = ?"; $dParams[] = $filterTip;         $dTypes .= 's'; }

    $stmtD = $conn->prepare("
        SELECT v.id_korisnik, k.ime, k.prezime, v.specijalnost,
               COUNT(z.id)                                                                             AS ukupno_dodeljeno,
               COALESCE(SUM(CASE WHEN z.status IN ('DODELJEN','U_TOKU') THEN 1 ELSE 0 END), 0)       AS trenutno_aktivno,
               COALESCE(SUM(CASE WHEN z.status = 'ZAVRSEN'              THEN 1 ELSE 0 END), 0)       AS zavrseno,
               COALESCE(SUM(CASE WHEN z.status = 'PREKORACEN'           THEN 1 ELSE 0 END), 0)       AS prekoraceno
        FROM vestak v
        JOIN korisnik k ON k.id = v.id_korisnik
        LEFT JOIN zahtev_za_analizu z ON z.vestak_id = v.id_korisnik{$dOnExtra}
        GROUP BY v.id_korisnik, k.ime, k.prezime, v.specijalnost
        ORDER BY trenutno_aktivno DESC, ukupno_dodeljeno DESC
    ");
    if (!empty($dParams)) {
        $stmtD->bind_param($dTypes, ...$dParams);
    }
    $stmtD->execute();
    $dataD = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtD->close();
}

// ═══════════════════════════════════════════════════════════════════════════════
// TCPDF — isti obrazac kao actions/izvestaji-actions.php
// ═══════════════════════════════════════════════════════════════════════════════
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->setFontSubsetting(true);
$pdf->SetCreator('ForenzIIS');
$pdf->SetAuthor('ForenzIIS — Forenzički informacioni sistem');
$pdf->SetTitle('Izveštaj o statusu analiza');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// ─── Zaglavlje (isti stil kao kolege) ─────────────────────────────────────────
$pdf->SetFont('freeserif', 'B', 16);
$pdf->Cell(0, 10, 'IZVEŠTAJ O STATUSU ANALIZA', 0, 1, 'C');
$pdf->SetFont('freeserif', '', 10);
$pdf->Cell(0, 6, 'ForenzIIS — Forenzički informacioni sistem', 0, 1, 'C');
$pdf->Ln(4);
$pdf->SetDrawColor(100, 100, 100);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(6);

// Datum generisanja i primenjeni filteri
$pdf->SetFont('freeserif', 'I', 9);
$pdf->Cell(0, 5, 'Datum generisanja: ' . date('d.m.Y H:i'), 0, 1, 'C');

$filteri = [];
if ($filterDatumOd !== '' || $filterDatumDo !== '') {
    $od       = $filterDatumOd !== '' ? formatDatum($filterDatumOd) : '—';
    $do       = $filterDatumDo !== '' ? formatDatum($filterDatumDo) : '—';
    $filteri[] = 'Period: ' . $od . ' – ' . $do;
}
if ($filterPredmetNaziv !== '') {
    $filteri[] = 'Predmet: ' . $filterPredmetNaziv;
}
if ($filterTip !== '') {
    $filteri[] = 'Tip analize: ' . tipAnalizeLabel($filterTip);
}
if (!empty($filteri)) {
    $pdf->Cell(0, 5, 'Filteri: ' . implode(' | ', $filteri), 0, 1, 'C');
}
$pdf->Ln(4);

// ─── Helper: zaglavlje tabele (sivi fill, bold 8pt) ───────────────────────────
$thRow = function (array $kolone) use ($pdf): void {
    $pdf->SetFont('freeserif', 'B', 8);
    $pdf->SetFillColor(230, 230, 230);
    foreach ($kolone as $k) {
        $pdf->Cell($k[0], 7, $k[1], 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('freeserif', '', 8);
};

// ─── Sekcija A — Pregled po statusu ───────────────────────────────────────────
if ($needsA && $dataA !== null) {
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, 'A — Pregled po statusu analiza', 0, 1);
    $pdf->Ln(2);

    $aktivneA = ($dataA['byStatus']['KREIRAN'] ?? 0)
              + ($dataA['byStatus']['DODELJEN'] ?? 0)
              + ($dataA['byStatus']['U_TOKU']   ?? 0);

    foreach ([
        'Ukupno analiza'                    => $dataA['ukupno'],
        'Aktivne (kreiran/dodeljen/u toku)' => $aktivneA,
        'Završene'                          => $dataA['byStatus']['ZAVRSEN']    ?? 0,
        'Prekoračene'                       => $dataA['byStatus']['PREKORACEN'] ?? 0,
        'Odbijene'                          => $dataA['byStatus']['ODBIJEN']    ?? 0,
    ] as $lbl => $val) {
        $pdf->SetFont('freeserif', 'B', 9);
        $pdf->Cell(70, 6, $lbl . ':', 0, 0);
        $pdf->SetFont('freeserif', '', 9);
        $pdf->Cell(0, 6, (string)$val, 0, 1);
    }
    $pdf->Ln(3);

    $thRow([[70,'Status'], [50,'Broj analiza'], [60,'Udeo (%)']]);
    foreach (['KREIRAN','DODELJEN','U_TOKU','ZAVRSEN','PREKORACEN','ODBIJEN'] as $s) {
        $broj = $dataA['byStatus'][$s] ?? 0;
        $pct  = $dataA['ukupno'] > 0 ? round($broj / $dataA['ukupno'] * 100, 1) : 0;
        $pdf->Cell(70, 6, badgeLabel($s), 1, 0, 'L');
        $pdf->Cell(50, 6, (string)$broj, 1, 0, 'C');
        $pdf->Cell(60, 6, $pct . '%', 1, 1, 'C');
    }
    $pdf->Ln(8);

    if ($dataA2 !== null) {
        $pdf->SetFont('freeserif', 'B', 10);
        $pdf->Cell(0, 6, 'Analize predmeta — pojedinačni pregled (' . $filterPredmetNaziv . '):', 0, 1);
        $pdf->Ln(1);
        if (!empty($dataA2)) {
            $thRow([[15,'ID'],[35,'Tip'],[40,'Veštak'],[35,'Status'],[55,'Rok']]);
            foreach ($dataA2 as $row) {
                $vestak = $row['vestak_ime'] ? ($row['vestak_ime'] . ' ' . $row['vestak_prezime']) : 'Nedodeljen';
                $pdf->Cell(15, 6, '#' . $row['id'], 1, 0, 'C');
                $pdf->Cell(35, 6, tipAnalizeLabel($row['tip_analize']), 1, 0, 'L');
                $pdf->Cell(40, 6, mb_strimwidth($vestak, 0, 26, '...'), 1, 0, 'L');
                $pdf->Cell(35, 6, badgeLabel($row['status']), 1, 0, 'C');
                $pdf->Cell(55, 6, $row['rok'] ? formatDatum($row['rok']) : '—', 1, 1, 'C');
            }
        } else {
            $pdf->SetFont('freeserif', 'I', 9);
            $pdf->Cell(0, 6, 'Nema analiza za izabrani predmet u okviru ostalih filtera.', 0, 1);
        }
        $pdf->Ln(8);
    }
}

// ─── Sekcija B — Vreme realizacije ────────────────────────────────────────────
if ($needsB && $dataB1 !== null) {
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, 'B — Vreme realizacije završenih analiza', 0, 1);
    $pdf->Ln(2);

    $bUkupno = (int)($dataB1['ukupno'] ?? 0);

    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->Cell(60, 6, 'Broj završenih analiza:', 0, 0);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->Cell(0, 6, (string)$bUkupno, 0, 1);

    if ($bUkupno > 0) {
        foreach ([
            'Prosečno vreme realizacije' => round((float)($dataB1['prosek'] ?? 0), 1) . ' dana',
            'Najbrže'                    => ((int)($dataB1['najbrze']    ?? 0)) . ' dana',
            'Najsporije'                 => ((int)($dataB1['najsporije'] ?? 0)) . ' dana',
        ] as $lbl => $val) {
            $pdf->SetFont('freeserif', 'B', 9);
            $pdf->Cell(60, 6, $lbl . ':', 0, 0);
            $pdf->SetFont('freeserif', '', 9);
            $pdf->Cell(0, 6, $val, 0, 1);
        }
        $pdf->Ln(4);

        // Tabela po tipu
        if (!empty($dataB2)) {
            $pdf->SetFont('freeserif', 'B', 10);
            $pdf->Cell(0, 6, 'Realizacija po tipu analize:', 0, 1);
            $pdf->Ln(1);
            $thRow([[50,'Tip analize'],[26,'Br. završenih'],[35,'Prosek (dana)'],[35,'Najbrže (dana)'],[34,'Najsporije (d.)']]);
            foreach ($dataB2 as $row) {
                $pdf->Cell(50, 6, tipAnalizeLabel($row['tip_analize']), 1, 0, 'L');
                $pdf->Cell(26, 6, (string)(int)$row['broj'], 1, 0, 'C');
                $pdf->Cell(35, 6, $row['prosek']    !== null ? round((float)$row['prosek'],    1) : '—', 1, 0, 'C');
                $pdf->Cell(35, 6, $row['najbrze']   !== null ? (string)(int)$row['najbrze']        : '—', 1, 0, 'C');
                $pdf->Cell(34, 6, $row['najsporije'] !== null ? (string)(int)$row['najsporije']     : '—', 1, 1, 'C');
            }
            $pdf->Ln(4);
        }

        // Tabela po veštaku
        if (!empty($dataB3)) {
            $pdf->SetFont('freeserif', 'B', 10);
            $pdf->Cell(0, 6, 'Vreme realizacije po veštaku:', 0, 1);
            $pdf->Ln(1);
            $thRow([[50,'Veštak'],[26,'Br. završenih'],[35,'Prosek (dana)'],[35,'Najbrže (dana)'],[34,'Najsporije (d.)']]);
            foreach ($dataB3 as $row) {
                $pdf->Cell(50, 6, mb_strimwidth($row['ime'] . ' ' . $row['prezime'], 0, 33, '...'), 1, 0, 'L');
                $pdf->Cell(26, 6, (string)(int)$row['broj_analiza'], 1, 0, 'C');
                $pdf->Cell(35, 6, $row['prosek']    !== null ? round((float)$row['prosek'],    1) : '—', 1, 0, 'C');
                $pdf->Cell(35, 6, $row['najbrze']   !== null ? (string)(int)$row['najbrze']        : '—', 1, 0, 'C');
                $pdf->Cell(34, 6, $row['najsporije'] !== null ? (string)(int)$row['najsporije']     : '—', 1, 1, 'C');
            }
            $pdf->Ln(4);
        }

        // Distribucija
        $pdf->SetFont('freeserif', 'B', 10);
        $pdf->Cell(0, 6, 'Raspodela trajanja analiza:', 0, 1);
        $pdf->Ln(1);
        $thRow([[70,'Opseg trajanja'],[50,'Broj analiza'],[60,'Udeo (%)']]);
        foreach ([
            '0 – 3 dana'  => (int)($dataB1['d0_3']  ?? 0),
            '4 – 7 dana'  => (int)($dataB1['d4_7']  ?? 0),
            '8 – 14 dana' => (int)($dataB1['d8_14'] ?? 0),
            '15+ dana'    => (int)($dataB1['d15p']  ?? 0),
        ] as $lbl => $broj) {
            $pct = $bUkupno > 0 ? round($broj / $bUkupno * 100, 1) : 0;
            $pdf->Cell(70, 6, $lbl, 1, 0, 'L');
            $pdf->Cell(50, 6, (string)$broj, 1, 0, 'C');
            $pdf->Cell(60, 6, $pct . '%', 1, 1, 'C');
        }
    } else {
        $pdf->SetFont('freeserif', 'I', 9);
        $pdf->Cell(0, 6, 'Nema završenih analiza za odabrane filtere.', 0, 1);
    }
    $pdf->Ln(8);
}

// ─── Sekcija C — Kašnjenja ────────────────────────────────────────────────────
if ($needsC && $dataC !== null) {
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, 'C — Kašnjenja', 0, 1);
    $pdf->Ln(2);

    $cUkupno     = (int)($dataC['agg']['ukupno'] ?? 0);
    $cProsek     = ($dataC['agg']['prosek_kasnjenja'] !== null && $cUkupno > 0)
                     ? round((float)$dataC['agg']['prosek_kasnjenja'], 1) . ' dana'
                     : '—';

    foreach ([
        'Ukupno kasnih analiza' => $cUkupno,
        'Prosečno kašnjenje'    => $cProsek,
    ] as $lbl => $val) {
        $pdf->SetFont('freeserif', 'B', 9);
        $pdf->Cell(60, 6, $lbl . ':', 0, 0);
        $pdf->SetFont('freeserif', '', 9);
        $pdf->Cell(0, 6, (string)$val, 0, 1);
    }
    $pdf->Ln(3);

    if (!empty($dataC['lista'])) {
        $thRow([[13,'ID'],[42,'Predmet'],[35,'Veštak'],[30,'Tip'],[25,'Status'],[20,'Rok'],[15,'Dana']]);
        foreach ($dataC['lista'] as $row) {
            $vestak = $row['vestak_ime'] ? ($row['vestak_ime'] . ' ' . $row['vestak_prezime']) : '—';
            $dana   = (int)$row['dana_kasnjenja'];
            $pdf->Cell(13, 6, '#' . $row['id'], 1, 0, 'C');
            $pdf->Cell(42, 6, mb_strimwidth($row['predmet_naziv'], 0, 27, '...'), 1, 0, 'L');
            $pdf->Cell(35, 6, mb_strimwidth($vestak, 0, 22, '...'), 1, 0, 'L');
            $pdf->Cell(30, 6, tipAnalizeLabel($row['tip_analize']), 1, 0, 'L');
            $pdf->Cell(25, 6, badgeLabel($row['status']), 1, 0, 'C');
            $pdf->Cell(20, 6, formatDatum($row['rok']), 1, 0, 'C');
            $pdf->SetTextColor(200, 0, 0);
            $pdf->Cell(15, 6, (string)$dana, 1, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }
    } else {
        $pdf->SetFont('freeserif', 'I', 9);
        $pdf->Cell(0, 6, 'Nema kasnih analiza za odabrane filtere.', 0, 1);
    }
    $pdf->Ln(8);

    if ($dataC2 !== null) {
        $pdf->SetFont('freeserif', 'B', 10);
        $pdf->Cell(0, 6, 'Analize koje su kasnile (istorijski — bile PREKORACEN, trenutno nisu):', 0, 1);
        $pdf->Ln(1);
        if (!empty($dataC2)) {
            $thRow([[13,'ID'],[37,'Veštak'],[30,'Tip'],[30,'Tren. status'],[20,'Rok'],[50,'Prvi put prekoračena']]);
            foreach ($dataC2 as $row) {
                $vestak = $row['vestak_ime'] ? ($row['vestak_ime'] . ' ' . $row['vestak_prezime']) : '—';
                $pdf->Cell(13, 6, '#' . $row['id'], 1, 0, 'C');
                $pdf->Cell(37, 6, mb_strimwidth($vestak, 0, 24, '...'), 1, 0, 'L');
                $pdf->Cell(30, 6, tipAnalizeLabel($row['tip_analize']), 1, 0, 'L');
                $pdf->Cell(30, 6, badgeLabel($row['trenutni_status']), 1, 0, 'C');
                $pdf->Cell(20, 6, $row['rok'] ? formatDatum($row['rok']) : '—', 1, 0, 'C');
                $pdf->Cell(50, 6, $row['datum_kad_je_kasnio'] ? formatDatumVreme($row['datum_kad_je_kasnio']) : '—', 1, 1, 'C');
            }
        } else {
            $pdf->SetFont('freeserif', 'I', 9);
            $pdf->Cell(0, 6, 'Nema analiza koje su ranije kasnile.', 0, 1);
        }
        $pdf->Ln(8);
    }
}

// ─── Sekcija D — Opterećenje veštaka ──────────────────────────────────────────
if ($needsD && $dataD !== null) {
    $PRAG_AKTIVNIH = 5;
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, 'D — Opterećenje veštaka', 0, 1);
    $pdf->Ln(2);

    if (!empty($dataD)) {
        $thRow([[50,'Veštak'],[40,'Specijalnost'],[22,'Ukupno'],[22,'Aktivno'],[23,'Završeno'],[23,'Prekoračeno']]);
        foreach ($dataD as $row) {
            $aktivno    = (int)$row['trenutno_aktivno'];
            $prekoracen = (int)$row['prekoraceno'];

            $pdf->Cell(50, 6, mb_strimwidth($row['ime'] . ' ' . $row['prezime'], 0, 33, '...'), 1, 0, 'L');
            $pdf->Cell(40, 6, mb_strimwidth($row['specijalnost'] ?: '—', 0, 26, '...'), 1, 0, 'L');
            $pdf->Cell(22, 6, (string)(int)$row['ukupno_dodeljeno'], 1, 0, 'C');

            $pdf->SetTextColor($aktivno >= $PRAG_AKTIVNIH ? 200 : ($aktivno > 0 ? 160 : 0),
                               $aktivno >= $PRAG_AKTIVNIH ? 0   : ($aktivno > 0 ? 120 : 0),
                               0);
            $pdf->Cell(22, 6, (string)$aktivno, 1, 0, 'C');

            $pdf->SetTextColor(0, 128, 0);
            $pdf->Cell(23, 6, (string)(int)$row['zavrseno'], 1, 0, 'C');

            $pdf->SetTextColor($prekoracen > 0 ? 200 : 0, 0, 0);
            $pdf->Cell(23, 6, (string)$prekoracen, 1, 1, 'C');

            $pdf->SetTextColor(0, 0, 0);
        }
    } else {
        $pdf->SetFont('freeserif', 'I', 9);
        $pdf->Cell(0, 6, 'Nema veštaka u sistemu.', 0, 1);
    }
}

// ─── Footer (isti stil kao kolege) ────────────────────────────────────────────
$pdf->Ln(6);
$pdf->SetDrawColor(100, 100, 100);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(4);
$pdf->SetFont('freeserif', 'I', 8);
$generisao = ($_SESSION['ime'] ?? '') . ' ' . ($_SESSION['prezime'] ?? '');
$pdf->Cell(0, 5, 'Izveštaj generisan: ' . date('d.m.Y \u H:i') . ' | Generisao: ' . $generisao, 0, 1, 'C');

// ─── Download ─────────────────────────────────────────────────────────────────
$sufiksMap = [
    'status'      => 'status',
    'vreme'       => 'vreme-realizacije',
    'kasnjenja'   => 'kasnjenja',
    'opterecenje' => 'opterecenje-vestaka',
    'sve'         => 'kompletan',
];
$imeFajla = 'izvestaj-analize-' . ($sufiksMap[$sekcija] ?? $sekcija) . '-' . date('d-m-Y') . '.pdf';
$pdf->Output($imeFajla, 'D');
exit;
