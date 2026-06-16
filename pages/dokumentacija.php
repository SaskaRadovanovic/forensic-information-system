<?php
/**
 * pages/dokumentacija.php — Lista dokumenata
 *
 * Prikazuje sve dokumente sa filterima po predmetu, tipu i statusu.
 * Sortiranje po kolonama. Dugme za kreiranje novog dokumenta.
 */

// ─── Čitanje filtera i sortiranja ──────────────────────────────────────────
$filterPredmet  = (int)($_GET['predmet_id'] ?? 0);
$filterTip      = $_GET['tip'] ?? '';
$filterStatus   = $_GET['status'] ?? '';
$filterPretraga = trim($_GET['q'] ?? '');
$filterTag      = (int)($_GET['tag_id'] ?? 0);
$filterAutor    = (int)($_GET['autor_id'] ?? 0);
$filterOdDatuma      = trim($_GET['od_datuma'] ?? '');
$filterDoDatuma      = trim($_GET['do_datuma'] ?? '');
$filterPoverljivost  = $_GET['poverljivost'] ?? '';
$sort                = $_GET['sort'] ?? 'datum_kreiranja';
$dir           = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

// Dozvoljene kolone za sortiranje
$dozvoljenoSortiranje = ['naziv', 'datum_kreiranja', 'verzija'];
if (!in_array($sort, $dozvoljenoSortiranje)) {
    $sort = 'datum_kreiranja';
}

// ─── Gradnja SQL upita ────────────────────────────────────────────────────
$currentUserId = $_SESSION['user_id'];
$currentUloga  = $_SESSION['uloga'];

$sql = "
    SELECT dok.id, dok.naziv, dok.verzija, dok.status, dok.nivo_poverljivosti, dok.datum_kreiranja,
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

// Filtriranje po pravima pristupa (admin vidi sve)
if ($currentUloga !== 'ADMINISTRATOR') {
    $sql .= " AND (
        dok.nivo_poverljivosti = 'JAVNO'
        OR dok.autor_id = ?
        OR EXISTS (SELECT 1 FROM pravo_pristupa pp WHERE pp.dokument_id = dok.id AND pp.korisnik_id = ?)
    )";
    $params[] = $currentUserId;
    $params[] = $currentUserId;
    $types .= 'ii';
}

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

if ($filterPretraga !== '') {
    $sql .= " AND (dok.naziv LIKE CONCAT('%', ?, '%')
        OR EXISTS (SELECT 1 FROM metapodatak m WHERE m.dokument_id = dok.id AND m.vrednost LIKE CONCAT('%', ?, '%')))";
    $params[] = $filterPretraga;
    $params[] = $filterPretraga;
    $types .= 'ss';
}

if ($filterTag > 0) {
    $sql .= " AND EXISTS (SELECT 1 FROM dokument_tag dt WHERE dt.dokument_id = dok.id AND dt.tag_id = ?)";
    $params[] = $filterTag;
    $types .= 'i';
}

if ($filterAutor > 0) {
    $sql .= " AND dok.autor_id = ?";
    $params[] = $filterAutor;
    $types .= 'i';
}

if ($filterOdDatuma !== '') {
    $sql .= " AND dok.datum_kreiranja >= ?";
    $params[] = $filterOdDatuma . ' 00:00:00';
    $types .= 's';
}

if ($filterDoDatuma !== '') {
    $sql .= " AND dok.datum_kreiranja <= ?";
    $params[] = $filterDoDatuma . ' 23:59:59';
    $types .= 's';
}

if ($filterPoverljivost !== '') {
    $sql .= " AND dok.nivo_poverljivosti = ?";
    $params[] = $filterPoverljivost;
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

// Tagovi za filter dropdown
$tagoviRes = $conn->query("SELECT id, naziv FROM tag ORDER BY naziv");

// Autori za filter dropdown (samo korisnici koji su autori bar jednog dokumenta)
$autoriRes = $conn->query("SELECT DISTINCT k.id, k.ime, k.prezime FROM korisnik k JOIN dokument dok ON dok.autor_id = k.id ORDER BY k.prezime, k.ime");

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
<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
    <div class="page-title" style="margin-bottom:0;">Dokumentacija</div>
    <a href="?page=dokument-novi" class="btn btn-primary">+ Novi dokument</a>
</div>

<!-- Filter bar -->
<div class="filter-bar">
    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <input type="hidden" name="page" value="dokumentacija">

        <div class="form-group" style="margin-bottom:0;">
            <label>Pretraga</label>
            <input type="text" name="q" value="<?= e($filterPretraga) ?>" placeholder="Naziv ili opis..." style="min-width:180px;">
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
            <label>Tip dokumenta</label>
            <select name="tip" onchange="this.form.submit()">
                <option value="">Svi tipovi</option>
                <?php foreach (['Izveštaj','Fotografija','Zapisnik','Veštačenje','Zbirni izveštaj','Ostalo'] as $t): ?>
                <option value="<?= $t ?>" <?= $filterTip === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Autor</label>
            <select name="autor_id" onchange="this.form.submit()">
                <option value="0">Svi autori</option>
                <?php while ($a = $autoriRes->fetch_assoc()): ?>
                <option value="<?= $a['id'] ?>" <?= $filterAutor === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['ime'] . ' ' . $a['prezime']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Od datuma</label>
            <input type="date" name="od_datuma" value="<?= e($filterOdDatuma) ?>" onchange="this.form.submit()">
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Do datuma</label>
            <input type="date" name="do_datuma" value="<?= e($filterDoDatuma) ?>" onchange="this.form.submit()">
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Tag</label>
            <select name="tag_id" onchange="this.form.submit()">
                <option value="0">Svi tagovi</option>
                <?php while ($t = $tagoviRes->fetch_assoc()): ?>
                <option value="<?= $t['id'] ?>" <?= $filterTag === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['naziv']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label>Poverljivost</label>
            <select name="poverljivost" onchange="this.form.submit()">
                <option value="">Svi nivoi</option>
                <?php foreach (['JAVNO','INTERNO','POVERLJIVO','STROGO_POVERLJIVO'] as $nivo): ?>
                <option value="<?= $nivo ?>" <?= $filterPoverljivost === $nivo ? 'selected' : '' ?>><?= nivoPoverljivostiLabel($nivo) ?></option>
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

        <div class="form-group" style="margin-bottom:0;">
            <button type="submit" class="btn btn-outline btn-sm">Pretraži</button>
        </div>

        <div style="margin-left:auto;"></div>
    </form>
</div>

<!-- Aktivni filteri -->
<?php
$aktivniFilteri = [];
if ($filterPretraga !== '') $aktivniFilteri['q'] = 'Pretraga: "' . $filterPretraga . '"';
if ($filterPredmet > 0) $aktivniFilteri['predmet_id'] = 'Predmet';
if ($filterTip !== '') $aktivniFilteri['tip'] = 'Tip: ' . $filterTip;
if ($filterAutor > 0) $aktivniFilteri['autor_id'] = 'Autor';
if ($filterTag > 0) $aktivniFilteri['tag_id'] = 'Tag';
if ($filterOdDatuma !== '') $aktivniFilteri['od_datuma'] = 'Od: ' . $filterOdDatuma;
if ($filterDoDatuma !== '') $aktivniFilteri['do_datuma'] = 'Do: ' . $filterDoDatuma;
if ($filterPoverljivost !== '') $aktivniFilteri['poverljivost'] = nivoPoverljivostiLabel($filterPoverljivost);
if ($filterStatus !== '') $aktivniFilteri['status'] = badgeLabel($filterStatus);

if (!empty($aktivniFilteri)): ?>
<div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; align-items:center;">
    <span style="color:var(--text-3); font-size:12px; font-family:var(--mono);">Filteri:</span>
    <?php foreach ($aktivniFilteri as $param => $label):
        $resetParams = $_GET;
        if ($param === 'predmet_id' || $param === 'autor_id' || $param === 'tag_id') {
            $resetParams[$param] = '0';
        } else {
            unset($resetParams[$param]);
        }
        $resetUrl = '?' . http_build_query($resetParams);
    ?>
    <a href="<?= e($resetUrl) ?>" class="badge badge-yellow" style="text-decoration:none; cursor:pointer;"><?= e($label) ?> ✕</a>
    <?php endforeach; ?>
    <a href="?page=dokumentacija" style="color:var(--text-3); font-size:11px; font-family:var(--mono); margin-left:8px;">Poništi sve</a>
</div>
<?php endif; ?>

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
                    <th>Poverljivost</th>
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
                    <td><span class="badge <?= nivoPoverljivostiBadge($row['nivo_poverljivosti'] ?? 'INTERNO') ?>"><?= e(nivoPoverljivostiLabel($row['nivo_poverljivosti'] ?? 'INTERNO')) ?></span></td>
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
