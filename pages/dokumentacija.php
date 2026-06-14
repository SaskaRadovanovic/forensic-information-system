<?php
/**
 * pages/dokumentacija.php — Lista dokumenata
 *
 * Prikazuje sve dokumente sa filterima po predmetu, tipu i statusu.
 * Sortiranje po kolonama. Dugme za kreiranje novog dokumenta.
 */

// ─── Čitanje filtera i sortiranja ──────────────────────────────────────────
$filterPredmet = (int)($_GET['predmet_id'] ?? 0);
$filterTip     = $_GET['tip'] ?? '';
$filterStatus  = $_GET['status'] ?? '';
$sort          = $_GET['sort'] ?? 'datum_kreiranja';
$dir           = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

// Dozvoljene kolone za sortiranje
$dozvoljenoSortiranje = ['naziv', 'datum_kreiranja', 'verzija'];
if (!in_array($sort, $dozvoljenoSortiranje)) {
    $sort = 'datum_kreiranja';
}

// ─── Gradnja SQL upita ────────────────────────────────────────────────────
$sql = "
    SELECT dok.id, dok.naziv, dok.verzija, dok.status, dok.datum_kreiranja,
           p.naziv AS predmet_naziv,
           k.ime AS autor_ime, k.prezime AS autor_prezime,
           (SELECT m.vrednost FROM metapodatak m WHERE m.dokument_id = dok.id AND m.kljuc = 'tipDokumenta' LIMIT 1) AS tip_dokumenta
    FROM dokument dok
    JOIN predmet p ON p.id = dok.predmet_id
    JOIN korisnik k ON k.id = dok.autor_id
    WHERE 1=1
";

$params = [];
$types  = '';

if ($filterPredmet > 0) {
    $sql .= " AND dok.predmet_id = ?";
    $params[] = $filterPredmet;
    $types .= 'i';
}

if ($filterStatus !== '') {
    $sql .= " AND dok.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}

$sql .= " ORDER BY dok.{$sort} {$dir}";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$dokumenti = $stmt->get_result();

// Filtriranje po tipu dokumenta posle upita (jer je u metapodacima)
$filteredDocs = [];
while ($row = $dokumenti->fetch_assoc()) {
    if ($filterTip !== '' && ($row['tip_dokumenta'] ?? '') !== $filterTip) {
        continue;
    }
    // Učitaj tagove za ovaj dokument
    $tagStmt = $conn->prepare("SELECT t.naziv, t.boja FROM dokument_tag dt JOIN tag t ON t.id = dt.tag_id WHERE dt.dokument_id = ?");
    $tagStmt->bind_param('i', $row['id']);
    $tagStmt->execute();
    $row['tagovi'] = $tagStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $tagStmt->close();
    $filteredDocs[] = $row;
}

// Predmeti za filter dropdown
$predmetiRes = $conn->query("SELECT id, naziv FROM predmet ORDER BY naziv");

// Pomoćna funkcija za link sortiranja
function sortLink(string $kolona, string $label, string $currentSort, string $currentDir): string {
    $newDir = ($currentSort === $kolona && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $arrow = '';
    if ($currentSort === $kolona) {
        $arrow = $currentDir === 'ASC' ? ' ↑' : ' ↓';
    }
    return '<a href="?page=dokumentacija&sort=' . $kolona . '&dir=' . $newDir . '" style="color:inherit;">' . $label . $arrow . '</a>';
}
?>

<div class="page-eyebrow">Dokumenti</div>
<div class="page-title">Dokumentacija</div>

<!-- Filter bar -->
<div class="filter-bar">
    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <input type="hidden" name="page" value="dokumentacija">

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
            <label>Tip dokumenta</label>
            <select name="tip" onchange="this.form.submit()">
                <option value="">Svi tipovi</option>
                <?php foreach (['Izveštaj','Fotografija','Zapisnik','Veštačenje','Zbirni izveštaj','Ostalo'] as $t): ?>
                <option value="<?= $t ?>" <?= $filterTip === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                <option value="">Svi statusi</option>
                <option value="AKTIVAN" <?= $filterStatus === 'AKTIVAN' ? 'selected' : '' ?>>Aktivan</option>
                <option value="ARHIVIRAN" <?= $filterStatus === 'ARHIVIRAN' ? 'selected' : '' ?>>Arhiviran</option>
            </select>
        </div>

        <div style="margin-left:auto;">
            <a href="?page=dokument-novi" class="btn btn-primary">+ Novi dokument</a>
        </div>
    </form>
</div>

<!-- Tabela dokumenata -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (count($filteredDocs) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th><?= sortLink('naziv', 'Naziv', $sort, $dir) ?></th>
                    <th>Tip</th>
                    <th>Predmet</th>
                    <th>Autor</th>
                    <th>Tagovi</th>
                    <th><?= sortLink('datum_kreiranja', 'Datum', $sort, $dir) ?></th>
                    <th><?= sortLink('verzija', 'Verzija', $sort, $dir) ?></th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filteredDocs as $row): ?>
                <tr>
                    <td><a href="?page=dokument-detalji&id=<?= $row['id'] ?>"><?= e($row['naziv']) ?></a></td>
                    <td><?= e($row['tip_dokumenta'] ?? '—') ?></td>
                    <td><?= e($row['predmet_naziv']) ?></td>
                    <td><?= e($row['autor_ime'] . ' ' . $row['autor_prezime']) ?></td>
                    <td>
                        <?php foreach ($row['tagovi'] as $tag): ?>
                        <span class="badge" style="background:<?= e($tag['boja']) ?>22; color:<?= e($tag['boja']) ?>; border-color:<?= e($tag['boja']) ?>44;"><?= e($tag['naziv']) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><?= formatDatum($row['datum_kreiranja']) ?></td>
                    <td>v<?= $row['verzija'] ?></td>
                    <td><span class="badge <?= badgeClass($row['status']) ?>"><?= e(badgeLabel($row['status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">Nema dokumenata koji odgovaraju filterima</div>
        <?php endif; ?>
    </div>
</div>
<?php $stmt->close(); ?>
