<?php
/**
 * pages/analize.php — Lista analiza
 *
 * Za VESTAK: samo analize dodeljene njemu.
 * Za ISTRAZITELJA: samo analize njegovih predmeta.
 * Za TEHNICARA i ADMINISTRATORA: sve analize.
 * Automatska provera rokova i generisanje upozorenja na učitavanju.
 */

$uloga  = $_SESSION['uloga'];
$userId = $_SESSION['user_id'];

// ─── Automatska provera rokova + notifikacije ──────────────────────────────
proveriPrekoraceneAnalize($conn, $userId);
generisuUpozorenjaOBliskomRoku($conn, $userId);

// ─── Filteri ───────────────────────────────────────────────────────────────
$filterPredmet = (int)($_GET['predmet_id'] ?? 0);
$filterTip     = $_GET['tip_analize'] ?? '';
$filterStatus  = $_GET['status'] ?? '';
$filterVestak  = (int)($_GET['vestak_id'] ?? 0);
$filterRokOd   = $_GET['rok_od'] ?? '';
$filterRokDo   = $_GET['rok_do'] ?? '';

// ─── Gradnja SQL upita ─────────────────────────────────────────────────────
$sql = "
    SELECT z.id, z.tip_analize, z.status, z.rok, z.datum_kreiranja,
           z.prag_upozorenja_dana,
           DATEDIFF(DATE(z.rok), CURDATE()) AS dani_do_roka,
           d.sifra_dokaza, d.naziv AS dokaz_naziv,
           p.naziv AS predmet_naziv,
           kv.ime AS vestak_ime, kv.prezime AS vestak_prezime
    FROM zahtev_za_analizu z
    JOIN dokaz d ON d.id = z.dokaz_id
    JOIN predmet p ON p.id = z.predmet_id
    LEFT JOIN korisnik kv ON kv.id = z.vestak_id
    WHERE 1=1
";

$params = [];
$types  = '';

if ($uloga === 'VESTAK') {
    $sql .= " AND z.vestak_id = ?";
    $params[] = $userId;
    $types .= 'i';
} elseif ($uloga === 'ISTRAZITELJ') {
    $sql .= " AND z.istrazitelj_id = ?";
    $params[] = $userId;
    $types .= 'i';
}

if ($filterPredmet > 0) {
    $sql .= " AND z.predmet_id = ?";
    $params[] = $filterPredmet;
    $types .= 'i';
}
if ($filterTip !== '') {
    $sql .= " AND z.tip_analize = ?";
    $params[] = $filterTip;
    $types .= 's';
}
if ($filterStatus !== '') {
    $sql .= " AND z.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}
if ($filterVestak > 0 && $uloga !== 'VESTAK') {
    $sql .= " AND z.vestak_id = ?";
    $params[] = $filterVestak;
    $types .= 'i';
}
if ($filterRokOd !== '') {
    $sql .= " AND DATE(z.rok) >= ?";
    $params[] = $filterRokOd;
    $types .= 's';
}
if ($filterRokDo !== '') {
    $sql .= " AND DATE(z.rok) <= ?";
    $params[] = $filterRokDo;
    $types .= 's';
}

$sql .= " ORDER BY z.datum_kreiranja DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$analize = $stmt->get_result();

// Predmeti za filter dropdown
$predmetiRes = $conn->query("SELECT id, naziv FROM predmet ORDER BY naziv");
$vestaci = $conn->query("SELECT k.id, k.ime, k.prezime FROM vestak v JOIN korisnik k ON k.id = v.id_korisnik ORDER BY k.prezime");
?>

<div class="page-eyebrow">Forenzika</div>
<div class="page-title"><?= $uloga === 'VESTAK' ? 'Moje analize' : 'Analize' ?></div>

<!-- Filter bar -->
<div class="filter-bar">
    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <input type="hidden" name="page" value="analize">

        <?php if ($uloga !== 'VESTAK'): ?>
        <div class="form-group" style="margin-bottom:0;">
            <label>Predmet</label>
            <select name="predmet_id" onchange="this.form.submit()">
                <option value="0">Svi predmeti</option>
                <?php while ($p = $predmetiRes->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>" <?= $filterPredmet === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['naziv']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin-bottom:0;">
            <label>Tip analize</label>
            <select name="tip_analize" onchange="this.form.submit()">
                <option value="">Svi tipovi</option>
                <?php foreach (['BALISTICKA','DNK','DIGITALNA','HEMIJSKA','TOKSIKOLOSKA','DOKUMENTOLOSKA','DRUGA'] as $t): ?>
                <option value="<?= $t ?>" <?= $filterTip === $t ? 'selected' : '' ?>><?= tipAnalizeLabel($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">Svi statusi</option>
                <?php foreach (['KREIRAN','DODELJEN','U_TOKU','ZAVRSEN','PREKORACEN','ODBIJEN'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= badgeLabel($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($uloga !== 'VESTAK'): ?>
        <div class="form-group" style="margin-bottom:0;">
            <label>Veštak</label>
            <select name="vestak_id" onchange="this.form.submit()">
                <option value="0">Svi veštaci</option>
                <?php while ($v = $vestaci->fetch_assoc()): ?>
                <option value="<?= $v['id'] ?>" <?= $filterVestak === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['ime'] . ' ' . $v['prezime']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin-bottom:0;">
            <label>Rok od</label>
            <input type="date" name="rok_od" value="<?= e($filterRokOd) ?>" onchange="this.form.submit()">
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Rok do</label>
            <input type="date" name="rok_do" value="<?= e($filterRokDo) ?>" onchange="this.form.submit()">
        </div>

        <div style="margin-left:auto;">
            <?php if ($uloga === 'ISTRAZITELJ'): ?>
            <a href="?page=analiza-nova" class="btn btn-primary">+ Novi zahtev</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabela analiza -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if ($analize->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Predmet</th>
                    <th>Dokaz</th>
                    <th>Tip</th>
                    <th>Veštak</th>
                    <th>Status</th>
                    <th>Rok</th>
                    <th>Dana do roka</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $analize->fetch_assoc()):
                    $dani = isset($row['dani_do_roka']) ? (int)$row['dani_do_roka'] : null;
                    $statusFinal = in_array($row['status'], ['ZAVRSEN','ODBIJEN','PREKORACEN']);
                    $pragUpozorenja = (int)$row['prag_upozorenja_dana'];
                    $blizakRok = !$statusFinal && $dani !== null && $dani >= 0 && $dani <= $pragUpozorenja;
                ?>
                <tr <?= $blizakRok ? 'style="background:rgba(245,197,24,.04);"' : '' ?>>
                    <td>
                        #<?= $row['id'] ?>
                        <?php if ($blizakRok): ?>
                        <span style="color:var(--yellow); margin-left:4px;" title="Rok se bliži">⚠</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($row['predmet_naziv']) ?></td>
                    <td><?= e($row['sifra_dokaza'] . ' — ' . $row['dokaz_naziv']) ?></td>
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
                    <td style="text-align:right; padding-right:12px;">
                        <a href="?page=analiza-detalji&id=<?= $row['id'] ?>" class="btn btn-ghost btn-sm">Detalji →</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema analiza koje odgovaraju filterima</div>
        <?php endif; ?>
    </div>
</div>
<?php $stmt->close(); ?>
