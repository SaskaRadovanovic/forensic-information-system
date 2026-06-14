<?php
/**
 * pages/predmeti.php — Lista predmeta
 *
 * Prikazuje predmete sa filterima po pretrazi, fazi i statusu.
 * TEHNICAR se preusmerava na dokaze.
 */

// TEHNICAR nema pristup predmetima — redirect na dokaze
if ($_SESSION['uloga'] === 'TEHNICAR') {
    header('Location: ?page=dokazi');
    exit;
}

// ─── Čitanje filtera ──────────────────────────────────────────────────────
$filterPretraga = trim($_GET['pretraga'] ?? '');
$filterFaza     = $_GET['faza'] ?? '';
$filterStatus   = $_GET['status'] ?? '';

// ─── Gradnja SQL upita ────────────────────────────────────────────────────
$sql = "SELECT id, naziv, faza, status, datum_otvaranja FROM predmet WHERE 1=1";
$params = [];
$types  = '';

if ($filterPretraga !== '') {
    $sql .= " AND (naziv LIKE ? OR id = ?)";
    $likeParam = "%{$filterPretraga}%";
    $params[] = $likeParam;
    $params[] = (int)$filterPretraga;
    $types .= 'si';
}

if ($filterFaza !== '') {
    $sql .= " AND faza = ?";
    $params[] = $filterFaza;
    $types .= 's';
}

if ($filterStatus !== '') {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

$sql .= " ORDER BY datum_otvaranja DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$predmeti = $stmt->get_result();
?>

<div class="page-eyebrow">Predmeti</div>
<div class="page-title">Predmeti</div>

<!-- Filter bar -->
<div class="filter-bar">
    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <input type="hidden" name="page" value="predmeti">

        <div class="form-group" style="margin-bottom:0;">
            <label>Pretraga</label>
            <input type="text" name="pretraga" value="<?= e($filterPretraga) ?>" placeholder="Naziv ili ID...">
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Faza</label>
            <select name="faza" onchange="this.form.submit()">
                <option value="">Sve faze</option>
                <?php foreach (['OTVOREN_SLUCAJ','PRIKUPLJANJE_DOKAZA','ANALIZA_DOKAZA','DONOSENJE_ZAKLJUCKA','ZATVOREN_SLUCAJ'] as $f): ?>
                <option value="<?= $f ?>" <?= $filterFaza === $f ? 'selected' : '' ?>><?= fazaLabel($f) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">Svi statusi</option>
                <option value="AKTIVAN" <?= $filterStatus === 'AKTIVAN' ? 'selected' : '' ?>>Aktivan</option>
                <option value="ZATVOREN" <?= $filterStatus === 'ZATVOREN' ? 'selected' : '' ?>>Zatvoren</option>
            </select>
        </div>

        <button type="submit" class="btn btn-ghost btn-sm">Pretraži</button>

        <div style="margin-left:auto;">
            <?php if (in_array($_SESSION['uloga'], ['ADMINISTRATOR', 'ISTRAZITELJ'])): ?>
            <a href="?page=predmet-novi" class="btn btn-primary">+ Novi predmet</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabela predmeta -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if ($predmeti->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Naziv</th>
                    <th>Faza</th>
                    <th>Datum otvaranja</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $predmeti->fetch_assoc()): ?>
                <tr>
                    <td><a href="?page=predmet-detalji&id=<?= $row['id'] ?>">#<?= $row['id'] ?></a></td>
                    <td><?= e($row['naziv']) ?></td>
                    <td><span class="badge <?= fazaBadge($row['faza']) ?>"><?= e(fazaLabel($row['faza'])) ?></span></td>
                    <td><?= formatDatum($row['datum_otvaranja']) ?></td>
                    <td><span class="badge <?= badgeClass($row['status']) ?>"><?= e(badgeLabel($row['status'])) ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema predmeta koji odgovaraju filterima</div>
        <?php endif; ?>
    </div>
</div>
<?php $stmt->close(); ?>
