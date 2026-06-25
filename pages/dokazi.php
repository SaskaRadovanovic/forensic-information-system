<?php
/**
 * pages/dokazi.php — Lista dokaza
 *
 * Prikazuje sve dokaze sa filterima po tipu, statusu i predmetu.
 * Dugme za kreiranje novog dokaza (TEHNICAR, ADMINISTRATOR).
 */

// ─── Čitanje filtera iz GET parametara ──────────────────────────────────────
$filterTip      = $_GET['tip_dokaza'] ?? '';
$filterStatus   = $_GET['status'] ?? '';
$filterPredmet  = (int)($_GET['predmet_id'] ?? 0);
$filterDatumOd  = $_GET['datum_od'] ?? '';
$filterDatumDo  = $_GET['datum_do'] ?? '';

// ─── Gradnja SQL upita sa dinamičkim filterima ──────────────────────────────
$sql = "
    SELECT d.id, d.sifra_dokaza, d.naziv, d.tip_dokaza, d.status, d.datum_prijema,
           p.naziv AS predmet_naziv,
           k.ime AS tehnicar_ime, k.prezime AS tehnicar_prezime
    FROM dokaz d
    JOIN predmet p ON p.id = d.predmet_id
    JOIN tehnicar_za_dokaze t ON t.id_korisnik = d.tehnicar_id
    JOIN korisnik k ON k.id = t.id_korisnik
    WHERE d.status != 'ARHIVIRANO'
";

$params = [];
$types  = '';

if ($filterTip !== '') {
    $sql .= " AND d.tip_dokaza = ?";
    $params[] = $filterTip;
    $types .= 's';
}

if ($filterStatus !== '') {
    $sql .= " AND d.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

if ($filterPredmet > 0) {
    $sql .= " AND d.predmet_id = ?";
    $params[] = $filterPredmet;
    $types .= 'i';
}

if ($filterDatumOd !== '') {
    $sql .= " AND d.datum_prijema >= ?";
    $params[] = $filterDatumOd . ' 00:00:00';
    $types .= 's';
}

if ($filterDatumDo !== '') {
    $sql .= " AND d.datum_prijema <= ?";
    $params[] = $filterDatumDo . ' 23:59:59';
    $types .= 's';
}

$sql .= " ORDER BY d.datum_prijema DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$dokazi = $stmt->get_result();

// ─── Predmeti za filter dropdown ────────────────────────────────────────────
$predmetiRes = $conn->query("SELECT id, naziv FROM predmet ORDER BY naziv");
?>

<div class="page-eyebrow">Forenzika</div>
<div class="page-title">Evidencija dokaza</div>

<!-- Filter bar -->
<div class="filter-bar">
    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <input type="hidden" name="page" value="dokazi">

        <div class="form-group" style="margin-bottom:0;">
            <label>Tip dokaza</label>
            <select name="tip_dokaza" onchange="this.form.submit()">
                <option value="">Svi tipovi</option>
                <?php foreach (['BIOLOSKI_TRAG','ORUZJE','DOKUMENT','ODECA','UZORAK'] as $tip): ?>
                <option value="<?= $tip ?>" <?= $filterTip === $tip ? 'selected' : '' ?>><?= tipDokazaLabel($tip) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">Svi statusi</option>
                <?php foreach (['PRIJEM','U_SKLADISTU','IZDATO_ZA_ANALIZU','VRACENO','KOMPROMITOVAN'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= badgeLabel($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Predmet</label>
            <select name="predmet_id" onchange="this.form.submit()">
                <option value="0">Svi predmeti</option>
                <?php while ($p = $predmetiRes->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>" <?= $filterPredmet === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['naziv']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Datum od</label>
            <input type="date" name="datum_od" value="<?= e($filterDatumOd) ?>" onchange="this.form.submit()">
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Datum do</label>
            <input type="date" name="datum_do" value="<?= e($filterDatumDo) ?>" onchange="this.form.submit()">
        </div>

        <div style="margin-left:auto;">
            <?php if (in_array($_SESSION['uloga'], ['TEHNICAR', 'ADMINISTRATOR'])): ?>
            <a href="?page=dokaz-novi" class="btn btn-primary">+ Novi dokaz</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabela dokaza -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if ($dokazi->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Šifra</th>
                    <th>Naziv</th>
                    <th>Tip</th>
                    <th>Predmet</th>
                    <th>Status</th>
                    <th>Datum prijema</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $dokazi->fetch_assoc()): ?>
                <tr>
                    <td><a href="?page=dokaz-detalji&id=<?= $row['id'] ?>"><?= e($row['sifra_dokaza']) ?></a></td>
                    <td><?= e($row['naziv']) ?></td>
                    <td><span class="badge <?= tipDokazaBadge($row['tip_dokaza']) ?>"><?= e(tipDokazaLabel($row['tip_dokaza'])) ?></span></td>
                    <td><?= e($row['predmet_naziv']) ?></td>
                    <td><span class="badge <?= badgeClass($row['status']) ?>"><?= e(badgeLabel($row['status'])) ?></span></td>
                    <td><?= formatDatum($row['datum_prijema']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema dokaza koji odgovaraju filterima</div>
        <?php endif; ?>
    </div>
</div>
<?php $stmt->close(); ?>
