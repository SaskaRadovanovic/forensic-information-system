<?php
/**
 * pages/analize.php — Lista analiza
 *
 * Za ISTRAZITELJ/ADMINISTRATOR: sve analize sa filterima.
 * Za VESTAK: samo analize dodeljene njemu.
 * Automatska provera rokova na učitavanju.
 */

$uloga  = $_SESSION['uloga'];
$userId = $_SESSION['user_id'];

// ─── Automatska provera rokova — prekoračeni zahtevi ───────────────────────
$conn->query("UPDATE zahtev_za_analizu SET status = 'PREKORACEN' WHERE rok < NOW() AND status IN ('KREIRAN','DODELJEN','U_TOKU')");

// ─── Filteri ───────────────────────────────────────────────────────────────
$filterPredmet = (int)($_GET['predmet_id'] ?? 0);
$filterTip     = $_GET['tip_analize'] ?? '';
$filterStatus  = $_GET['status'] ?? '';
$filterVestak  = (int)($_GET['vestak_id'] ?? 0);

// ─── Gradnja SQL upita ────────────────────────────────────────────────────
$sql = "
    SELECT z.id, z.tip_analize, z.status, z.rok, z.datum_kreiranja,
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

// VESTAK vidi samo svoje analize
if ($uloga === 'VESTAK') {
    $sql .= " AND z.vestak_id = ?";
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

$sql .= " ORDER BY z.datum_kreiranja DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$analize = $stmt->get_result();

// Predmeti i veštaci za filter dropdown
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
                    <th>Dokaz</th>
                    <th>Tip</th>
                    <th>Veštak</th>
                    <th>Rok</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $analize->fetch_assoc()): ?>
                <tr>
                    <td><a href="?page=analiza-detalji&id=<?= $row['id'] ?>">#<?= $row['id'] ?></a></td>
                    <td><?= e($row['sifra_dokaza'] . ' — ' . $row['dokaz_naziv']) ?></td>
                    <td><span class="badge badge-blue"><?= e(tipAnalizeLabel($row['tip_analize'])) ?></span></td>
                    <td><?= $row['vestak_ime'] ? e($row['vestak_ime'] . ' ' . $row['vestak_prezime']) : '<span style="color:var(--text-3)">Nedodeljen</span>' ?></td>
                    <td><?= formatDatum($row['rok']) ?></td>
                    <td><span class="badge <?= badgeClass($row['status']) ?>"><?= e(badgeLabel($row['status'])) ?></span></td>
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
