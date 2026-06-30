<?php
/**
 * pages/izvestaj-analize.php — Izveštaj o statusu analiza, vremenu realizacije,
 * kašnjenjima i opterećenju veštaka
 *
 * Pristup: ISTRAZITELJ (vidi samo svoje predmete) i ADMINISTRATOR (vidi sve).
 * Filteri: datum od/do, predmet, tip analize — primenjuju se na sve sekcije.
 */
requireRole('ISTRAZITELJ', 'ADMINISTRATOR');

$uloga  = $_SESSION['uloga'];
$userId = $_SESSION['user_id'];

// ─── Filteri ───────────────────────────────────────────────────────────────────
$filterDatumOd   = $_GET['datum_od']    ?? '';
$filterDatumDo   = $_GET['datum_do']    ?? '';
$filterPredmetId = (int)($_GET['predmet_id'] ?? 0);
$filterTip       = $_GET['tip_analize'] ?? '';

// ─── Predmeti za dropdown (zavisno od uloge) ──────────────────────────────────
if ($uloga === 'ISTRAZITELJ') {
    $sp = $conn->prepare("SELECT id, naziv FROM predmet WHERE istrazitelj_id = ? ORDER BY naziv");
    $sp->bind_param('i', $userId);
} else {
    $sp = $conn->prepare("SELECT id, naziv FROM predmet ORDER BY naziv");
}
$sp->execute();
$predmetiOpcije = $sp->get_result()->fetch_all(MYSQLI_ASSOC);
$sp->close();

// ─── Zajednički WHERE (alias z = zahtev_za_analizu) ───────────────────────────
// Sve vrednosti dolaze iz sesije ili validovanih GET parametara — nema string concat.
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

// ─── Helper: pripremi + izvrši upit koji u sebi sadrži $bWhere ────────────────
// SQL string mora sadržavati alias z za zahtev_za_analizu.
// extraParams/extraTypes se dodaju NA KRAJ bParams (za parametre koji nisu u $bWhere).
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

// ═══════════════════════════════════════════════════════════════════════════════
// SEKCIJA A — Broj analiza po statusu
// Upit: GROUP BY status uz zajednički WHERE (uloga + filteri)
// ═══════════════════════════════════════════════════════════════════════════════
$rowsA = $exec("
    SELECT z.status, COUNT(*) AS broj
    FROM zahtev_za_analizu z
    $bWhere
    GROUP BY z.status
");
$byStatus = [];
$ukupnoA  = 0;
foreach ($rowsA as $r) {
    $byStatus[$r['status']] = (int)$r['broj'];
    $ukupnoA += (int)$r['broj'];
}
$aktivneA  = ($byStatus['KREIRAN'] ?? 0) + ($byStatus['DODELJEN'] ?? 0) + ($byStatus['U_TOKU'] ?? 0);
$zavrseneA = $byStatus['ZAVRSEN']    ?? 0;
$prekoraA  = $byStatus['PREKORACEN'] ?? 0;
$odbijeneA = $byStatus['ODBIJEN']    ?? 0;

// ─── Sekcija A2 — Pojedinačni pregled analiza predmeta (samo kad je predmet_id filter postavljen) ──
$rowsA2 = [];
if ($filterPredmetId > 0) {
    $rowsA2 = $exec("
        SELECT
            z.id, z.tip_analize, z.status, z.rok, z.datum_kreiranja, z.prag_upozorenja_dana,
            DATEDIFF(DATE(z.rok), CURDATE())  AS dani_do_roka,
            k.ime                              AS vestak_ime,
            k.prezime                          AS vestak_prezime
        FROM zahtev_za_analizu z
        LEFT JOIN vestak v   ON v.id_korisnik = z.vestak_id
        LEFT JOIN korisnik k ON k.id = v.id_korisnik
        $bWhere
        ORDER BY z.rok ASC
    ");
}

// ═══════════════════════════════════════════════════════════════════════════════
// SEKCIJA B — Vreme realizacije (samo ZAVRSEN analize)
// B1: agregatna statistika + distribucija po opsezima
// B2: prosek, min, max po tipu analize
// Vreme realizacije = DATEDIFF(datum_unosa_rezultata, datum_kreiranja_zahteva)
// ═══════════════════════════════════════════════════════════════════════════════
$whereB = "$bWhere AND z.status = 'ZAVRSEN'";

$rowsB1 = $exec("
    SELECT
        COUNT(*)                                                                                AS ukupno,
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
$b1      = $rowsB1[0] ?? [];
$bUkupno = (int)($b1['ukupno'] ?? 0);
$bProsek = ($b1['prosek'] !== null && $bUkupno > 0) ? round((float)$b1['prosek'],    1) : null;
$bMin    = ($b1['najbrze']   !== null && $bUkupno > 0) ? (int)$b1['najbrze']             : null;
$bMax    = ($b1['najsporije'] !== null && $bUkupno > 0) ? (int)$b1['najsporije']          : null;

$rowsB2 = $exec("
    SELECT
        z.tip_analize,
        COUNT(*)                                             AS broj,
        AVG(DATEDIFF(r.datum_unosa, z.datum_kreiranja))     AS prosek,
        MIN(DATEDIFF(r.datum_unosa, z.datum_kreiranja))     AS najbrze,
        MAX(DATEDIFF(r.datum_unosa, z.datum_kreiranja))     AS najsporije
    FROM zahtev_za_analizu z
    JOIN rezultat_analize r ON r.zahtev_id = z.id
    $whereB
    GROUP BY z.tip_analize
    ORDER BY prosek DESC
");

$rowsB3 = $exec("
    SELECT
        v.id_korisnik,
        k.ime,
        k.prezime,
        COUNT(*)                                             AS broj_analiza,
        AVG(DATEDIFF(r.datum_unosa, z.datum_kreiranja))     AS prosek,
        MIN(DATEDIFF(r.datum_unosa, z.datum_kreiranja))     AS najbrze,
        MAX(DATEDIFF(r.datum_unosa, z.datum_kreiranja))     AS najsporije
    FROM zahtev_za_analizu z
    JOIN rezultat_analize r ON r.zahtev_id = z.id
    JOIN vestak v            ON v.id_korisnik = z.vestak_id
    JOIN korisnik k          ON k.id = v.id_korisnik
    $whereB
    GROUP BY v.id_korisnik, k.ime, k.prezime
    ORDER BY prosek ASC
");

// ═══════════════════════════════════════════════════════════════════════════════
// SEKCIJA C — Kašnjenja
// Obuhvata: PREKORACEN analize + aktivne (DODELJEN/U_TOKU) kojima je rok prošao
// Sortiranje po danima kašnjenja DESC (najkritičniji prvi)
// ═══════════════════════════════════════════════════════════════════════════════
$whereC = "$bWhere AND (
    z.status = 'PREKORACEN'
    OR (z.status IN ('DODELJEN','U_TOKU') AND z.rok IS NOT NULL AND z.rok < CURDATE())
)";

$rowsC1 = $exec("
    SELECT
        COUNT(*)                            AS ukupno,
        AVG(DATEDIFF(CURDATE(), z.rok))    AS prosek_kasnjenja
    FROM zahtev_za_analizu z
    $whereC
");
$c1           = $rowsC1[0] ?? [];
$cUkupno      = (int)($c1['ukupno'] ?? 0);
$cProsekKasn  = ($c1['prosek_kasnjenja'] !== null && $cUkupno > 0)
                  ? round((float)$c1['prosek_kasnjenja'], 1)
                  : null;

$rowsC2 = $exec("
    SELECT
        z.id,
        z.tip_analize,
        z.status,
        z.rok,
        DATEDIFF(CURDATE(), z.rok)         AS dana_kasnjenja,
        p.naziv                            AS predmet_naziv,
        k.ime                              AS vestak_ime,
        k.prezime                          AS vestak_prezime
    FROM zahtev_za_analizu z
    JOIN  predmet p  ON p.id  = z.predmet_id
    LEFT JOIN korisnik k ON k.id = z.vestak_id
    $whereC
    ORDER BY dana_kasnjenja DESC
");

// ─── C2 — Istorijska kašnjenja: analize koje su prošle kroz status PREKORACEN, ─
// ─── ali trenutni status više nije PREKORACEN (npr. naknadno ZAVRSEN) ─────────
$rowsC3 = $exec("
    SELECT DISTINCT
        z.id, z.tip_analize, z.status AS trenutni_status, z.rok,
        k.ime                              AS vestak_ime,
        k.prezime                          AS vestak_prezime,
        (SELECT MIN(isa2.datum_vreme) FROM istorija_statusa_analize isa2
         WHERE isa2.zahtev_id = z.id AND isa2.novi_status = 'PREKORACEN')  AS datum_kad_je_kasnio
    FROM zahtev_za_analizu z
    JOIN istorija_statusa_analize isa ON isa.zahtev_id = z.id AND isa.novi_status = 'PREKORACEN'
    LEFT JOIN vestak v   ON v.id_korisnik = z.vestak_id
    LEFT JOIN korisnik k ON k.id = v.id_korisnik
    $bWhere AND z.status != 'PREKORACEN'
    ORDER BY datum_kad_je_kasnio DESC
");

// ═══════════════════════════════════════════════════════════════════════════════
// SEKCIJA D — Opterećenje veštaka
// LEFT JOIN: filteri idu u ON klauzulu da bi veštaci bez analiza ostali vidljivi
// sa nultim brojevima (za ADMINISTRATORA bez filtera).
// ═══════════════════════════════════════════════════════════════════════════════
$dOnExtra = "";
$dParams  = [];
$dTypes   = '';

if ($uloga === 'ISTRAZITELJ') {
    $dOnExtra .= " AND z.istrazitelj_id = ?";
    $dParams[] = $userId;
    $dTypes   .= 'i';
}
if ($filterDatumOd !== '') {
    $dOnExtra .= " AND DATE(z.datum_kreiranja) >= ?";
    $dParams[] = $filterDatumOd;
    $dTypes   .= 's';
}
if ($filterDatumDo !== '') {
    $dOnExtra .= " AND DATE(z.datum_kreiranja) <= ?";
    $dParams[] = $filterDatumDo;
    $dTypes   .= 's';
}
if ($filterPredmetId > 0) {
    $dOnExtra .= " AND z.predmet_id = ?";
    $dParams[] = $filterPredmetId;
    $dTypes   .= 'i';
}
if ($filterTip !== '') {
    $dOnExtra .= " AND z.tip_analize = ?";
    $dParams[] = $filterTip;
    $dTypes   .= 's';
}

$stmtD = $conn->prepare("
    SELECT
        v.id_korisnik,
        k.ime,
        k.prezime,
        v.specijalnost,
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
$rowsD = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtD->close();

// ─── Praga upozorenja za veštake: 5+ aktivnih = vizuelno upozorenje ────────────
$PRAG_AKTIVNIH = 5;

// ─── Base URL za PDF export (prenosi aktivne filtere) ─────────────────────────
$exportBase = '?page=izvestaj-analize&action=izvestaj-analize-pdf';
if ($filterDatumOd !== '') $exportBase .= '&datum_od='    . urlencode($filterDatumOd);
if ($filterDatumDo !== '') $exportBase .= '&datum_do='    . urlencode($filterDatumDo);
if ($filterPredmetId > 0)  $exportBase .= '&predmet_id='  . $filterPredmetId;
if ($filterTip !== '')     $exportBase .= '&tip_analize=' . urlencode($filterTip);
?>

<div class="page-eyebrow">Analize</div>
<div class="page-title">Izveštaj analiza</div>

<!-- ═══ FILTERI ═════════════════════════════════════════════════════════════════ -->
<div class="filter-bar">
    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <input type="hidden" name="page" value="izvestaj-analize">

        <div class="form-group" style="margin-bottom:0;">
            <label>Datum kreiranja od</label>
            <input type="date" name="datum_od" value="<?= e($filterDatumOd) ?>" onchange="this.form.submit()">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Datum kreiranja do</label>
            <input type="date" name="datum_do" value="<?= e($filterDatumDo) ?>" onchange="this.form.submit()">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Predmet</label>
            <select name="predmet_id" onchange="this.form.submit()">
                <option value="0">Svi predmeti</option>
                <?php foreach ($predmetiOpcije as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $filterPredmetId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Tip analize</label>
            <select name="tip_analize" onchange="this.form.submit()">
                <option value="">Svi tipovi</option>
                <?php foreach (['BALISTICKA','DNK','DIGITALNA','HEMIJSKA','TOKSIKOLOSKA','DOKUMENTOLOSKA','DRUGA'] as $t): ?>
                <option value="<?= $t ?>" <?= $filterTip === $t ? 'selected' : '' ?>><?= tipAnalizeLabel($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($filterDatumOd !== '' || $filterDatumDo !== '' || $filterPredmetId > 0 || $filterTip !== ''): ?>
        <a href="?page=izvestaj-analize" class="btn btn-ghost btn-sm" style="align-self:flex-end;">Poništi filtere</a>
        <?php endif; ?>
    </form>
</div>
<div style="display:flex; justify-content:flex-end; margin:4px 0 16px;">
    <a href="<?= e($exportBase) ?>&sekcija=sve" class="btn btn-outline btn-sm">Export sve (PDF)</a>
</div>

<!-- ═══ SEKCIJA A — PREGLED PO STATUSU ══════════════════════════════════════════ -->
<div style="display:flex; align-items:center; justify-content:space-between; margin:8px 0 12px;">
    <div style="font-family:var(--mono); font-size:9px; text-transform:uppercase; letter-spacing:2px; color:var(--yellow);">A — Pregled po statusu analiza</div>
    <a href="<?= e($exportBase) ?>&sekcija=status" class="btn btn-outline btn-sm">Export PDF</a>
</div>

<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-label">Ukupno analiza</div>
        <div class="stat-value"><?= $ukupnoA ?></div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-label">Aktivne</div>
        <div class="stat-value"><?= $aktivneA ?></div>
        <div class="stat-sub">Kreiran + Dodeljen + U toku</div>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Završene</div>
        <div class="stat-value"><?= $zavrseneA ?></div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Prekoračene</div>
        <div class="stat-value"><?= $prekoraA ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Distribucija po statusu</h3>
        <?php if ($ukupnoA > 0): ?>
        <span style="font-family:var(--mono); font-size:10px; color:var(--text-3);">ukupno <?= $ukupnoA ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if ($ukupnoA > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Broj analiza</th>
                    <th>Udeo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (['KREIRAN','DODELJEN','U_TOKU','ZAVRSEN','PREKORACEN','ODBIJEN'] as $s):
                    $broj = $byStatus[$s] ?? 0;
                    $pct  = $ukupnoA > 0 ? round($broj / $ukupnoA * 100, 1) : 0;
                ?>
                <tr>
                    <td><span class="badge <?= badgeClass($s) ?>"><?= e(badgeLabel($s)) ?></span></td>
                    <td><?= $broj ?></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="flex:1; height:4px; background:var(--surface-3); min-width:120px;">
                                <div style="height:4px; background:var(--yellow); width:<?= $pct ?>%;"></div>
                            </div>
                            <span style="font-family:var(--mono); font-size:11px; color:var(--text-2); min-width:40px;"><?= $pct ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema analiza koje odgovaraju filterima</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($filterPredmetId > 0): ?>
<!-- A2: Pojedinačni pregled analiza izabranog predmeta -->
<div class="card" style="margin-top:16px;">
    <div class="card-header"><h3>Analize predmeta — pojedinačni pregled</h3></div>
    <div class="card-body" style="padding:0;">
        <?php if (!empty($rowsA2)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tip</th>
                    <th>Veštak</th>
                    <th>Status</th>
                    <th>Rok</th>
                    <th>Dana do roka</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rowsA2 as $row):
                    $dani           = isset($row['dani_do_roka']) ? (int)$row['dani_do_roka'] : null;
                    $statusFinal    = in_array($row['status'], ['ZAVRSEN','ODBIJEN','PREKORACEN']);
                    $pragUpozorenja = (int)$row['prag_upozorenja_dana'];
                    $blizakRok      = !$statusFinal && $dani !== null && $dani >= 0 && $dani <= $pragUpozorenja;
                ?>
                <tr <?= $blizakRok ? 'style="background:rgba(245,197,24,.04);"' : '' ?>>
                    <td><a href="?page=analiza-detalji&id=<?= $row['id'] ?>" style="color:var(--yellow);">#<?= $row['id'] ?></a></td>
                    <td><span class="badge <?= tipAnalizeClass($row['tip_analize']) ?>"><?= e(tipAnalizeLabel($row['tip_analize'])) ?></span></td>
                    <td><?= $row['vestak_ime'] ? e($row['vestak_ime'] . ' ' . $row['vestak_prezime']) : '<span style="color:var(--text-3)">Nedodeljen</span>' ?></td>
                    <td><span class="badge <?= badgeClass($row['status']) ?>"><?= e(badgeLabel($row['status'])) ?></span></td>
                    <td><?= $row['rok'] ? formatDatum($row['rok']) : '<span style="color:var(--text-3)">—</span>' ?></td>
                    <td>
                        <?php if ($statusFinal): ?>
                        <span style="color:var(--text-3)">—</span>
                        <?php elseif ($row['rok'] === null): ?>
                        <span style="color:var(--text-3)">Bez roka</span>
                        <?php elseif ($dani < 0): ?>
                        <span style="color:var(--red); font-weight:600;">KASNI <?= abs($dani) ?> <?= abs($dani) === 1 ? 'dan' : 'dana' ?></span>
                        <?php elseif ($dani === 0): ?>
                        <span style="color:var(--orange); font-weight:600;">Danas</span>
                        <?php elseif ($blizakRok): ?>
                        <span style="color:var(--yellow); font-weight:600;"><?= $dani ?> <?= $dani === 1 ? 'dan' : 'dana' ?></span>
                        <?php else: ?>
                        <span><?= $dani ?> <?= $dani === 1 ? 'dan' : 'dana' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema analiza za izabrani predmet u okviru ostalih filtera</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ═══ SEKCIJA B — VREME REALIZACIJE ═══════════════════════════════════════════ -->
<div style="display:flex; align-items:center; justify-content:space-between; margin:28px 0 12px;">
    <div style="font-family:var(--mono); font-size:9px; text-transform:uppercase; letter-spacing:2px; color:var(--yellow);">B — Vreme realizacije završenih analiza</div>
    <a href="<?= e($exportBase) ?>&sekcija=vreme" class="btn btn-outline btn-sm">Export PDF</a>
</div>

<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-label">Završenih analiza</div>
        <div class="stat-value"><?= $bUkupno ?></div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-label">Prosek trajanja</div>
        <div class="stat-value" style="font-size:36px;"><?= $bProsek !== null ? $bProsek : '—' ?></div>
        <?php if ($bProsek !== null): ?><div class="stat-sub">dana</div><?php endif; ?>
    </div>
    <div class="stat-card green">
        <div class="stat-label">Najbrže</div>
        <div class="stat-value" style="font-size:36px;"><?= $bMin !== null ? $bMin : '—' ?></div>
        <?php if ($bMin !== null): ?><div class="stat-sub">dana</div><?php endif; ?>
    </div>
    <div class="stat-card red">
        <div class="stat-label">Najsporije</div>
        <div class="stat-value" style="font-size:36px;"><?= $bMax !== null ? $bMax : '—' ?></div>
        <?php if ($bMax !== null): ?><div class="stat-sub">dana</div><?php endif; ?>
    </div>
</div>

<?php if ($bUkupno > 0): ?>

<!-- B: Prosek po tipu analize -->
<div class="card">
    <div class="card-header"><h3>Vreme realizacije po tipu analize</h3></div>
    <div class="card-body" style="padding:0;">
        <?php if (!empty($rowsB2)): ?>
        <table>
            <thead>
                <tr>
                    <th>Tip analize</th>
                    <th>Broj završenih</th>
                    <th>Prosek (dana)</th>
                    <th>Najbrže (dana)</th>
                    <th>Najsporije (dana)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rowsB2 as $row): ?>
                <tr>
                    <td><span class="badge <?= tipAnalizeClass($row['tip_analize']) ?>"><?= e(tipAnalizeLabel($row['tip_analize'])) ?></span></td>
                    <td><?= (int)$row['broj'] ?></td>
                    <td><?= $row['prosek'] !== null ? round((float)$row['prosek'], 1) : '—' ?></td>
                    <td style="color:var(--green);"><?= $row['najbrze'] !== null ? (int)$row['najbrze'] : '—' ?></td>
                    <td style="color:var(--red);"><?= $row['najsporije'] !== null ? (int)$row['najsporije'] : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema završenih analiza</div>
        <?php endif; ?>
    </div>
</div>

<!-- B: Vreme realizacije po veštaku -->
<div class="card">
    <div class="card-header"><h3>Vreme realizacije po veštaku</h3></div>
    <div class="card-body" style="padding:0;">
        <?php if (!empty($rowsB3)): ?>
        <table>
            <thead>
                <tr>
                    <th>Veštak</th>
                    <th>Broj završenih</th>
                    <th>Prosek (dana)</th>
                    <th>Najbrže (dana)</th>
                    <th>Najsporije (dana)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rowsB3 as $row): ?>
                <tr>
                    <td style="color:var(--text-1);"><?= e($row['ime'] . ' ' . $row['prezime']) ?></td>
                    <td><?= (int)$row['broj_analiza'] ?></td>
                    <td><?= $row['prosek'] !== null ? round((float)$row['prosek'], 1) : '—' ?></td>
                    <td style="color:var(--green);"><?= $row['najbrze'] !== null ? (int)$row['najbrze'] : '—' ?></td>
                    <td style="color:var(--red);"><?= $row['najsporije'] !== null ? (int)$row['najsporije'] : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema završenih analiza</div>
        <?php endif; ?>
    </div>
</div>

<!-- B: Distribucija trajanja -->
<div class="card">
    <div class="card-header"><h3>Raspodela trajanja analiza</h3></div>
    <div class="card-body" style="padding:0;">
        <?php
        $dist = [
            '0 – 3 dana'  => (int)($b1['d0_3']  ?? 0),
            '4 – 7 dana'  => (int)($b1['d4_7']  ?? 0),
            '8 – 14 dana' => (int)($b1['d8_14'] ?? 0),
            '15+ dana'    => (int)($b1['d15p']  ?? 0),
        ];
        ?>
        <table>
            <thead>
                <tr>
                    <th>Opseg</th>
                    <th>Broj analiza</th>
                    <th>Udeo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dist as $label => $broj):
                    $pct = $bUkupno > 0 ? round($broj / $bUkupno * 100, 1) : 0;
                ?>
                <tr>
                    <td style="color:var(--text-1); font-family:var(--mono);"><?= e($label) ?></td>
                    <td><?= $broj ?></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="flex:1; height:4px; background:var(--surface-3); min-width:120px;">
                                <div style="height:4px; background:var(--blue); width:<?= $pct ?>%;"></div>
                            </div>
                            <span style="font-family:var(--mono); font-size:11px; color:var(--text-2); min-width:40px;"><?= $pct ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">Nema završenih analiza za prikaz vremena realizacije</div>
    </div>
</div>
<?php endif; ?>

<!-- ═══ SEKCIJA C — KAŠNJENJA ════════════════════════════════════════════════════ -->
<div style="display:flex; align-items:center; justify-content:space-between; margin:28px 0 12px;">
    <div style="font-family:var(--mono); font-size:9px; text-transform:uppercase; letter-spacing:2px; color:var(--yellow);">C — Kašnjenja</div>
    <a href="<?= e($exportBase) ?>&sekcija=kasnjenja" class="btn btn-outline btn-sm">Export PDF</a>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
    <div class="stat-card red">
        <div class="stat-label">Ukupno kasnih analiza</div>
        <div class="stat-value"><?= $cUkupno ?></div>
        <div class="stat-sub">prekoračene ili aktivne sa isteklim rokom</div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-label">Prosečno kašnjenje</div>
        <div class="stat-value" style="font-size:36px;"><?= $cProsekKasn !== null ? $cProsekKasn : '—' ?></div>
        <?php if ($cProsekKasn !== null): ?><div class="stat-sub">dana</div><?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Lista kasnih analiza</h3>
        <span style="font-family:var(--mono); font-size:10px; color:var(--text-3);">sortirano od najkritičnijeg</span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (!empty($rowsC2)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Predmet</th>
                    <th>Veštak</th>
                    <th>Tip</th>
                    <th>Status</th>
                    <th>Rok</th>
                    <th>Dana kašnjenja</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rowsC2 as $row):
                    $dana = (int)$row['dana_kasnjenja'];
                ?>
                <tr>
                    <td><a href="?page=analiza-detalji&id=<?= $row['id'] ?>" style="color:var(--yellow);">#<?= $row['id'] ?></a></td>
                    <td><?= e($row['predmet_naziv']) ?></td>
                    <td><?= $row['vestak_ime'] ? e($row['vestak_ime'] . ' ' . $row['vestak_prezime']) : '<span style="color:var(--text-3)">—</span>' ?></td>
                    <td><span class="badge <?= tipAnalizeClass($row['tip_analize']) ?>"><?= e(tipAnalizeLabel($row['tip_analize'])) ?></span></td>
                    <td><span class="badge <?= badgeClass($row['status']) ?>"><?= e(badgeLabel($row['status'])) ?></span></td>
                    <td style="color:var(--red);"><?= formatDatum($row['rok']) ?></td>
                    <td>
                        <span style="color:var(--red); font-weight:600; font-family:var(--mono);">
                            <?= $dana ?> <?= $dana === 1 ? 'dan' : 'dana' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema kasnih analiza</div>
        <?php endif; ?>
    </div>
</div>

<!-- C2: Istorijska kašnjenja -->
<div class="card">
    <div class="card-header">
        <h3>Analize koje su kasnile (istorijski)</h3>
        <span style="font-family:var(--mono); font-size:10px; color:var(--text-3);">bile PREKORACEN, trenutno nisu</span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (!empty($rowsC3)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Veštak</th>
                    <th>Tip</th>
                    <th>Trenutni status</th>
                    <th>Rok</th>
                    <th>Prvi put prekoračena</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rowsC3 as $row): ?>
                <tr>
                    <td><a href="?page=analiza-detalji&id=<?= $row['id'] ?>" style="color:var(--yellow);">#<?= $row['id'] ?></a></td>
                    <td><?= $row['vestak_ime'] ? e($row['vestak_ime'] . ' ' . $row['vestak_prezime']) : '<span style="color:var(--text-3)">—</span>' ?></td>
                    <td><span class="badge <?= tipAnalizeClass($row['tip_analize']) ?>"><?= e(tipAnalizeLabel($row['tip_analize'])) ?></span></td>
                    <td><span class="badge <?= badgeClass($row['trenutni_status']) ?>"><?= e(badgeLabel($row['trenutni_status'])) ?></span></td>
                    <td><?= $row['rok'] ? formatDatum($row['rok']) : '<span style="color:var(--text-3)">—</span>' ?></td>
                    <td><?= $row['datum_kad_je_kasnio'] ? formatDatumVreme($row['datum_kad_je_kasnio']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema analiza koje su ranije kasnile</div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ SEKCIJA D — OPTEREĆENJE VEŠTAKA ══════════════════════════════════════════ -->
<div style="display:flex; align-items:center; justify-content:space-between; margin:28px 0 12px;">
    <div style="font-family:var(--mono); font-size:9px; text-transform:uppercase; letter-spacing:2px; color:var(--yellow);">D — Opterećenje veštaka</div>
    <a href="<?= e($exportBase) ?>&sekcija=opterecenje" class="btn btn-outline btn-sm">Export PDF</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Pregled opterećenja veštaka</h3>
        <span style="font-family:var(--mono); font-size:10px; color:var(--text-3);">
            ⚠ upozorenje: <?= $PRAG_AKTIVNIH ?>+ aktivnih ili prekoračenih analiza
        </span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (!empty($rowsD)): ?>
        <table>
            <thead>
                <tr>
                    <th>Veštak</th>
                    <th>Specijalnost</th>
                    <th>Ukupno dodeljeno</th>
                    <th>Trenutno aktivno</th>
                    <th>Završeno</th>
                    <th>Prekoračeno</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rowsD as $row):
                    $aktivno    = (int)$row['trenutno_aktivno'];
                    $prekoracen = (int)$row['prekoraceno'];
                    $upozorenje = $aktivno >= $PRAG_AKTIVNIH || $prekoracen > 0;
                ?>
                <tr <?= $upozorenje ? 'style="background: rgba(239,68,68,.04);"' : '' ?>>
                    <td style="color:var(--text-1);"><?= e($row['ime'] . ' ' . $row['prezime']) ?></td>
                    <td style="color:var(--text-2);"><?= e($row['specijalnost'] ?: '—') ?></td>
                    <td><?= (int)$row['ukupno_dodeljeno'] ?></td>
                    <td>
                        <?php if ($aktivno === 0): ?>
                        <span style="color:var(--text-3);">0</span>
                        <?php elseif ($aktivno >= $PRAG_AKTIVNIH): ?>
                        <span style="color:var(--red); font-weight:600;">⚠ <?= $aktivno ?></span>
                        <?php else: ?>
                        <span style="color:var(--yellow);"><?= $aktivno ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--green);"><?= (int)$row['zavrseno'] ?></td>
                    <td>
                        <?php if ($prekoracen === 0): ?>
                        <span style="color:var(--text-3);">0</span>
                        <?php else: ?>
                        <span style="color:var(--red); font-weight:600;"><?= $prekoracen ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema veštaka u sistemu</div>
        <?php endif; ?>
    </div>
</div>
