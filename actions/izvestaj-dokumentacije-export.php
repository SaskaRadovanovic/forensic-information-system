<?php
requireLogin();
requireRole('ISTRAZITELJ', 'ADMINISTRATOR');

$predmetId = (int)($_GET['predmet_id'] ?? 0);

if ($predmetId > 0) {
    $stmt = $conn->prepare("SELECT * FROM predmet WHERE id = ?");
    $stmt->bind_param('i', $predmetId);
    $stmt->execute();
    $predmet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$predmet) {
        flashError('Predmet nije pronađen.');
        header('Location: ?page=izvestaj-dokumentacije');
        exit;
    }

    $predmeti = [$predmet];
} else {
    $res = $conn->query("SELECT * FROM predmet ORDER BY naziv");
    $predmeti = $res->fetch_all(MYSQLI_ASSOC);
}

$ukupnoDokumenata = 0;
$poTipu = [];
$poStatusu = [];

foreach ($predmeti as &$p) {
    $stmt = $conn->prepare("
        SELECT dok.id, dok.naziv, dok.verzija, dok.status, dok.nivo_poverljivosti, dok.datum_kreiranja,
               k.ime AS autor_ime, k.prezime AS autor_prezime,
               (SELECT m.vrednost FROM metapodatak m WHERE m.dokument_id = dok.id AND m.kljuc = 'tipDokumenta' LIMIT 1) AS tip_dokumenta,
               (SELECT m.vrednost FROM metapodatak m WHERE m.dokument_id = dok.id AND m.kljuc = 'opis' LIMIT 1) AS opis
        FROM dokument dok
        JOIN korisnik k ON k.id = dok.autor_id
        WHERE dok.predmet_id = ?
        ORDER BY dok.datum_kreiranja ASC
    ");
    $stmt->bind_param('i', $p['id']);
    $stmt->execute();
    $p['dokumenti'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($p['dokumenti'] as &$dok) {
        $stmtTag = $conn->prepare("SELECT t.naziv FROM dokument_tag dt JOIN tag t ON t.id = dt.tag_id WHERE dt.dokument_id = ?");
        $stmtTag->bind_param('i', $dok['id']);
        $stmtTag->execute();
        $tagovi = $stmtTag->get_result()->fetch_all(MYSQLI_ASSOC);
        $dok['tagovi_str'] = implode(', ', array_column($tagovi, 'naziv'));
        $stmtTag->close();

        $ukupnoDokumenata++;

        $tip = $dok['tip_dokumenta'] ?: 'Neodređen';
        $poTipu[$tip] = ($poTipu[$tip] ?? 0) + 1;

        $poStatusu[$dok['status']] = ($poStatusu[$dok['status']] ?? 0) + 1;
    }
    unset($dok);
}
unset($p);

// ─── Generisanje PDF-a ──────────────────────────────────────────────────────
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->setFontSubsetting(true);
$pdf->SetCreator('ForenzIIS');
$pdf->SetAuthor('ForenzIIS — Forenzički informacioni sistem');
$pdf->SetTitle('Izveštaj o stanju dokumentacije');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// Zaglavlje
$pdf->SetFont('freeserif', 'B', 16);
$pdf->Cell(0, 10, 'IZVEŠTAJ O STANJU DOKUMENTACIJE', 0, 1, 'C');
$pdf->SetFont('freeserif', '', 10);
$pdf->Cell(0, 6, 'ForenzIIS — Forenzički informacioni sistem', 0, 1, 'C');
$pdf->Ln(4);
$pdf->SetDrawColor(100, 100, 100);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(6);

// Za svaki predmet
foreach ($predmeti as $p) {
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 8, 'Predmet: ' . $p['naziv'], 0, 1);
    $pdf->Ln(2);

    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->Cell(40, 5, 'Status:', 0, 0);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->Cell(50, 5, badgeLabel($p['status']), 0, 0);
    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->Cell(20, 5, 'Faza:', 0, 0);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->Cell(0, 5, fazaLabel($p['faza']), 0, 1);

    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->Cell(40, 5, 'Datum otvaranja:', 0, 0);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->Cell(50, 5, formatDatumVreme($p['datum_otvaranja']), 0, 0);
    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->Cell(40, 5, 'Ukupno dokumenata:', 0, 0);
    $pdf->SetFont('freeserif', '', 9);
    $pdf->Cell(0, 5, count($p['dokumenti']), 0, 1);
    $pdf->Ln(3);

    if (!empty($p['dokumenti'])) {
        // Zaglavlje tabele
        $pdf->SetFont('freeserif', 'B', 8);
        $pdf->SetFillColor(230, 230, 230);
        $w = [8, 30, 20, 25, 22, 10, 22, 18, 25];
        $headers = ['#', 'Naziv', 'Tip', 'Autor', 'Datum', 'Ver.', 'Poverljivost', 'Status', 'Tagovi'];
        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($w[$i], 7, $headers[$i], 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('freeserif', '', 7);
        foreach ($p['dokumenti'] as $idx => $dok) {
            $pdf->Cell($w[0], 6, $idx + 1, 1, 0, 'C');
            $pdf->Cell($w[1], 6, mb_strimwidth($dok['naziv'], 0, 22, '...'), 1, 0, 'L');
            $pdf->Cell($w[2], 6, mb_strimwidth($dok['tip_dokumenta'] ?: '—', 0, 14, '...'), 1, 0, 'L');
            $pdf->Cell($w[3], 6, mb_strimwidth($dok['autor_ime'] . ' ' . $dok['autor_prezime'], 0, 18, '...'), 1, 0, 'L');
            $pdf->Cell($w[4], 6, formatDatum($dok['datum_kreiranja']), 1, 0, 'C');
            $pdf->Cell($w[5], 6, 'v' . $dok['verzija'], 1, 0, 'C');
            $pdf->Cell($w[6], 6, nivoPoverljivostiLabel($dok['nivo_poverljivosti']), 1, 0, 'C');
            $pdf->Cell($w[7], 6, badgeLabel($dok['status']), 1, 0, 'C');
            $pdf->Cell($w[8], 6, mb_strimwidth($dok['tagovi_str'] ?: '—', 0, 18, '...'), 1, 1, 'L');
        }
    } else {
        $pdf->SetFont('freeserif', 'I', 9);
        $pdf->Cell(0, 6, 'Nema dokumenata za ovaj predmet.', 0, 1);
    }

    $pdf->Ln(6);
}

// Sumarna statistika
$pdf->SetFont('freeserif', 'B', 12);
$pdf->Cell(0, 8, 'Sumarna statistika', 0, 1);
$pdf->Ln(2);

$pdf->SetFont('freeserif', 'B', 9);
$pdf->Cell(45, 6, 'Ukupno predmeta:', 0, 0);
$pdf->SetFont('freeserif', '', 9);
$pdf->Cell(0, 6, count($predmeti), 0, 1);

$pdf->SetFont('freeserif', 'B', 9);
$pdf->Cell(45, 6, 'Ukupno dokumenata:', 0, 0);
$pdf->SetFont('freeserif', '', 9);
$pdf->Cell(0, 6, $ukupnoDokumenata, 0, 1);

if (!empty($poTipu)) {
    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->Cell(45, 6, 'Po tipu dokumenta:', 0, 0);
    $pdf->SetFont('freeserif', '', 9);
    $tipovi = [];
    foreach ($poTipu as $tip => $cnt) {
        $tipovi[] = "$tip ($cnt)";
    }
    $pdf->MultiCell(0, 6, implode(', ', $tipovi), 0, 'L');
}

if (!empty($poStatusu)) {
    $pdf->SetFont('freeserif', 'B', 9);
    $pdf->Cell(45, 6, 'Po statusu:', 0, 0);
    $pdf->SetFont('freeserif', '', 9);
    $statusi = [];
    foreach ($poStatusu as $status => $cnt) {
        $statusi[] = badgeLabel($status) . " ($cnt)";
    }
    $pdf->MultiCell(0, 6, implode(', ', $statusi), 0, 'L');
}

// Footer
$pdf->Ln(8);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(4);
$pdf->SetFont('freeserif', 'I', 8);
$pdf->Cell(0, 5, 'Izveštaj generisan: ' . date('d.m.Y \u H:i') . ' | Generisao: ' . $_SESSION['ime'] . ' ' . $_SESSION['prezime'], 0, 1, 'C');

// Download
if ($predmetId > 0) {
    $imeFajla = 'Izvestaj_dokumentacije_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $predmeti[0]['naziv']) . '_' . date('Ymd') . '.pdf';
} else {
    $imeFajla = 'Izvestaj_dokumentacije_svi_predmeti_' . date('Ymd') . '.pdf';
}

$pdf->Output($imeFajla, 'D');
exit;
