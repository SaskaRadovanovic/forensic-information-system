<?php
/**
 * pages/predmeti.php — Lista predmeta
 *
 * Filteri: pretraga, istražitelj, godina, status/faza.
 * Tabela: ID, naziv, istražitelj, dat. otvaranja, dat. zatvaranja, status, dugme Otvori.
 */

if ($_SESSION['uloga'] === 'TEHNICAR') {
    header('Location: ?page=dokazi');
    exit;
}

// ─── Čitanje filtera ──────────────────────────────────────────────────────────
$filterPretraga    = trim($_GET['pretraga'] ?? '');
$filterIstrazitelj = (int)($_GET['istrazitelj_id'] ?? 0);
$filterGodina      = (int)($_GET['godina'] ?? 0);
$filterStatus      = $_GET['status'] ?? '';

// ─── Dropdown: svi istražitelji koji imaju bar jedan predmet ──────────────────
$istrazitelji = $conn->query("
    SELECT DISTINCT k.id, k.ime, k.prezime
    FROM korisnik k
    JOIN predmet p ON p.istrazitelj_id = k.id
    ORDER BY k.prezime, k.ime
");

// ─── Dropdown: dostupne godine ────────────────────────────────────────────────
$godine = $conn->query("
    SELECT DISTINCT YEAR(datum_otvaranja) AS godina
    FROM predmet
    ORDER BY godina DESC
");

// ─── Gradnja SQL upita ────────────────────────────────────────────────────────
$sql = "
    SELECT p.id, p.naziv, p.faza, p.status,
           p.datum_otvaranja,
           ki.ime AS istrazitelj_ime, ki.prezime AS istrazitelj_prezime
    FROM predmet p
    LEFT JOIN korisnik ki ON ki.id = p.istrazitelj_id
    WHERE 1=1
";

$params = [];
$types  = '';

if ($filterPretraga !== '') {
    $sql .= " AND (p.naziv LIKE ? OR p.id = ?)";
    $params[] = "%{$filterPretraga}%";
    $params[] = (int)$filterPretraga;
    $types .= 'si';
}

if ($filterIstrazitelj > 0) {
    $sql .= " AND p.istrazitelj_id = ?";
    $params[] = $filterIstrazitelj;
    $types .= 'i';
}

if ($filterGodina > 0) {
    $sql .= " AND YEAR(p.datum_otvaranja) = ?";
    $params[] = $filterGodina;
    $types .= 'i';
}

if ($filterStatus !== '') {
    // Status može biti AKTIVAN/ZATVOREN ili faza
    if (in_array($filterStatus, ['AKTIVAN', 'ZATVOREN'])) {
        $sql .= " AND p.status = ?";
    } else {
        $sql .= " AND p.faza = ?";
    }
    $params[] = $filterStatus;
    $types .= 's';
}

$sql .= " ORDER BY p.datum_otvaranja DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$predmeti = $stmt->get_result();

// ─── Helper: formatirani ID predmeta (P-YYYY-NNN) ─────────────────────────────
function formatPredmetId(int $id, string $datum): string {
    $god = date('Y', strtotime($datum));
    return 'P-' . $god . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);
}
?>

<div class="page-eyebrow">Istraga</div>
<div class="page-title">Istražni predmeti</div>

<!-- ── Filter traka ─────────────────────────────────────────────────────────── -->
<style>
.filter-row {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: nowrap;
}
.filter-row input[type="text"],
.filter-row select {
    height: 36px;
    box-sizing: border-box;
    font-family: var(--mono);
    font-size: 12px;
    padding: 0 12px;
    margin: 0;
    border: 1px solid var(--border);
    background: var(--surface-2);
    color: var(--text-1);
    outline: none;
}
.filter-row .btn {
    height: 36px;
    box-sizing: border-box;
    padding: 0 14px;
    margin: 0;
}
.filter-row input[type="text"] {
    flex: 2;
    min-width: 0;
}
.filter-row select {
    flex: 1;
    min-width: 0;
    cursor: pointer;
}
.filter-row input[type="text"]:focus,
.filter-row select:focus {
    border-color: var(--yellow);
}
.filter-row .btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    line-height: 1;
}
</style>

<form method="GET" class="filter-row">
    <input type="hidden" name="page" value="predmeti">

    <input type="text" name="pretraga"
           value="<?= e($filterPretraga) ?>"
           placeholder="Pretraži po nazivu ili metapodacima...">

    <select name="istrazitelj_id" onchange="this.form.submit()">
        <option value="0">Sve istražitelje</option>
        <?php while ($ist = $istrazitelji->fetch_assoc()): ?>
        <option value="<?= $ist['id'] ?>"
            <?= $filterIstrazitelj === (int)$ist['id'] ? 'selected' : '' ?>>
            <?= e(mb_strtoupper($ist['prezime']) . ' ' . mb_strtoupper($ist['ime'])) ?>
        </option>
        <?php endwhile; ?>
    </select>

    <select name="godina" onchange="this.form.submit()">
        <option value="0">Sve godine</option>
        <?php while ($g = $godine->fetch_assoc()): ?>
        <option value="<?= $g['godina'] ?>"
            <?= $filterGodina === (int)$g['godina'] ? 'selected' : '' ?>>
            <?= $g['godina'] ?>
        </option>
        <?php endwhile; ?>
    </select>

    <select name="status" onchange="this.form.submit()">
        <option value="">Svi statusi</option>
        <optgroup label="Status">
            <option value="AKTIVAN"  <?= $filterStatus === 'AKTIVAN'  ? 'selected' : '' ?>>Aktivan</option>
            <option value="ZATVOREN" <?= $filterStatus === 'ZATVOREN' ? 'selected' : '' ?>>Zatvoren</option>
        </optgroup>
        <optgroup label="Faza">
            <?php foreach ([
                'OTVOREN_SLUCAJ'      => 'Otvoren slučaj',
                'PRIKUPLJANJE_DOKAZA' => 'Prikupljanje dokaza',
                'ANALIZA_DOKAZA'      => 'Analiza dokaza',
                'DONOSENJE_ZAKLJUCKA' => 'Donošenje zaključka',
                'ZATVOREN_SLUCAJ'     => 'Zatvoren slučaj',
            ] as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= $filterStatus === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </optgroup>
    </select>

    <button type="submit" class="btn btn-ghost">Pretraži</button>

    <?php if ($filterPretraga || $filterIstrazitelj || $filterGodina || $filterStatus): ?>
    <a href="?page=predmeti" class="btn btn-ghost">✕ Resetuj</a>
    <?php endif; ?>

    <?php if (in_array($_SESSION['uloga'], ['ADMINISTRATOR', 'ISTRAZITELJ'])): ?>
    <a href="?page=predmet-novi" class="btn btn-primary">+ Novi predmet</a>
    <?php endif; ?>
</form>

<!-- ── Tabela predmeta ──────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if ($predmeti->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID predmeta</th>
                    <th>Naziv predmeta</th>
                    <th>Istražitelj</th>
                    <th>Dat. otvaranja</th>
                    <th>Dat. zatvaranja</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $predmeti->fetch_assoc()): ?>
                <tr>
                    <td style="color:var(--yellow); font-weight:600; font-family:var(--mono);">
                        <?= e(formatPredmetId($row['id'], $row['datum_otvaranja'])) ?>
                    </td>
                    <td style="color:var(--text-1);"><?= e($row['naziv']) ?></td>
                    <td>
                        <?php if ($row['istrazitelj_prezime']): ?>
                            <?= e(mb_strtoupper(mb_substr($row['istrazitelj_ime'], 0, 1)) . '. ' . mb_strtoupper($row['istrazitelj_prezime'])) ?>
                        <?php else: ?>
                            <span style="color:var(--text-3);">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= formatDatum($row['datum_otvaranja']) ?></td>
                    <td>
                        <?php if ($row['status'] === 'ZATVOREN'): ?>
                            <span style="color:var(--text-3); font-family:var(--mono); font-size:11px;">
                                <?= formatDatum($row['datum_otvaranja']) ?>
                            </span>
                        <?php else: ?>
                            <span style="color:var(--text-3);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['status'] === 'ZATVOREN'): ?>
                            <span class="badge badge-gray">Zatvoren slučaj</span>
                        <?php else: ?>
                            <span class="badge <?= fazaBadge($row['faza']) ?>"><?= e(fazaLabel($row['faza'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=predmet-detalji&id=<?= $row['id'] ?>"
                           class="btn btn-outline btn-sm"
                           style="white-space:nowrap;">
                            Otvori &rarr;
                        </a>
                    </td>
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
