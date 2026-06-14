<?php
/**
 * pages/dokument-detalji.php — Detalji dokumenta
 *
 * Prikazuje sve podatke o dokumentu, metapodatke, tagove,
 * istoriju verzija, i dugmad za akcije.
 */

$dokumentId = (int)($_GET['id'] ?? 0);

// ─── Učitavanje dokumenta sa relacijama ────────────────────────────────────
$stmt = $conn->prepare("
    SELECT dok.*, p.naziv AS predmet_naziv,
           k.ime AS autor_ime, k.prezime AS autor_prezime
    FROM dokument dok
    JOIN predmet p ON p.id = dok.predmet_id
    JOIN korisnik k ON k.id = dok.autor_id
    WHERE dok.id = ?
");
$stmt->bind_param('i', $dokumentId);
$stmt->execute();
$dokument = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dokument) {
    flashError('Dokument nije pronađen.');
    header('Location: ?page=dokumentacija');
    exit;
}

// ─── Učitavanje metapodataka ───────────────────────────────────────────────
$stmt = $conn->prepare("SELECT kljuc, vrednost FROM metapodatak WHERE dokument_id = ?");
$stmt->bind_param('i', $dokumentId);
$stmt->execute();
$metapodaci = [];
$metaResult = $stmt->get_result();
while ($m = $metaResult->fetch_assoc()) {
    $metapodaci[$m['kljuc']] = $m['vrednost'];
}
$stmt->close();

// ─── Učitavanje tagova na dokumentu ────────────────────────────────────────
$stmt = $conn->prepare("SELECT t.id, t.naziv, t.boja FROM dokument_tag dt JOIN tag t ON t.id = dt.tag_id WHERE dt.dokument_id = ?");
$stmt->bind_param('i', $dokumentId);
$stmt->execute();
$tagovi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Svi tagovi (za upravljanje)
$sviTagovi = $conn->query("SELECT id, naziv, boja FROM tag ORDER BY naziv")->fetch_all(MYSQLI_ASSOC);
$tagIdsNaDokumentu = array_column($tagovi, 'id');

// ─── Istorija verzija ──────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT da.*, k.ime, k.prezime
    FROM dokument_arhiva da
    JOIN korisnik k ON k.id = da.sacuvao_id
    WHERE da.dokument_id = ?
    ORDER BY da.datum_arhiviranja DESC
");
$stmt->bind_param('i', $dokumentId);
$stmt->execute();
$istorija = $stmt->get_result();
?>

<!-- Breadcrumb -->
<div class="page-breadcrumb">
    <a href="?page=dokumentacija" class="btn btn-ghost btn-sm">&larr; Dokumentacija</a>
    <span style="color: var(--text-3); font-family: var(--mono); font-size: 12px;">/</span>
    <span style="color: var(--text-2); font-family: var(--mono); font-size: 12px;"><?= e($dokument['naziv']) ?></span>
</div>

<!-- Naslov + status -->
<div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
    <div class="page-title" style="margin-bottom: 0;"><?= e($dokument['naziv']) ?></div>
    <span class="badge <?= badgeClass($dokument['status']) ?>"><?= e(badgeLabel($dokument['status'])) ?></span>
    <span class="badge badge-blue">v<?= $dokument['verzija'] ?></span>
</div>

<!-- Info grid -->
<div class="info-grid">
    <div class="info-item">
        <div class="info-label">Tip dokumenta</div>
        <div class="info-value"><?= e($metapodaci['tipDokumenta'] ?? '—') ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Predmet</div>
        <div class="info-value"><?= e($dokument['predmet_naziv']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Autor</div>
        <div class="info-value"><?= e($dokument['autor_ime'] . ' ' . $dokument['autor_prezime']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Verzija</div>
        <div class="info-value">v<?= $dokument['verzija'] ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Status</div>
        <div class="info-value"><span class="badge <?= badgeClass($dokument['status']) ?>"><?= e(badgeLabel($dokument['status'])) ?></span></div>
    </div>
    <div class="info-item">
        <div class="info-label">Datum kreiranja</div>
        <div class="info-value"><?= formatDatumVreme($dokument['datum_kreiranja']) ?></div>
    </div>
</div>

<?php if (!empty($metapodaci['opis'])): ?>
<div class="card">
    <div class="card-header"><h3>Opis</h3></div>
    <div class="card-body"><?= e($metapodaci['opis']) ?></div>
</div>
<?php endif; ?>

<!-- Tagovi -->
<div class="card">
    <div class="card-header"><h3>Tagovi</h3></div>
    <div class="card-body">
        <?php if (!empty($tagovi)): ?>
        <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px;">
            <?php foreach ($tagovi as $tag): ?>
            <span class="badge" style="background:<?= e($tag['boja']) ?>22; color:<?= e($tag['boja']) ?>; border-color:<?= e($tag['boja']) ?>44;"><?= e($tag['naziv']) ?></span>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="color:var(--text-3); font-family:var(--mono); font-size:11px; margin-bottom:12px;">Nema tagova</div>
        <?php endif; ?>

        <?php if ($dokument['status'] !== 'ARHIVIRAN'): ?>
        <div style="display:flex; gap:4px; flex-wrap:wrap;">
            <?php foreach ($sviTagovi as $tag): ?>
            <form method="POST" action="?page=dokument-detalji&id=<?= $dokumentId ?>&action=toggle-tag" style="display:inline;">
                <input type="hidden" name="tag_id" value="<?= $tag['id'] ?>">
                <button type="submit" class="btn btn-sm" style="<?= in_array($tag['id'], $tagIdsNaDokumentu) ? 'background:' . e($tag['boja']) . '22; color:' . e($tag['boja']) . '; border-color:' . e($tag['boja']) . '44;' : '' ?>">
                    <?= in_array($tag['id'], $tagIdsNaDokumentu) ? '✕' : '+' ?> <?= e($tag['naziv']) ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Istorija verzija -->
<?php if ($istorija->num_rows > 0): ?>
<div class="card">
    <div class="card-header"><h3>Istorija verzija</h3></div>
    <div class="card-body" style="padding:0;">
        <table>
            <thead>
                <tr>
                    <th>Verzija</th>
                    <th>Razlog izmene</th>
                    <th>Sačuvao</th>
                    <th>Datum</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($v = $istorija->fetch_assoc()): ?>
                <tr>
                    <td>v<?= $v['verzija'] ?></td>
                    <td><?= e($v['razlog_izmene'] ?: '—') ?></td>
                    <td><?= e($v['ime'] . ' ' . $v['prezime']) ?></td>
                    <td><?= formatDatumVreme($v['datum_arhiviranja']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Dugmad -->
<div class="action-bar">
    <?php if ($dokument['status'] !== 'ARHIVIRAN'): ?>
    <a href="?page=dokument-izmeni&id=<?= $dokumentId ?>" class="btn btn-outline">Izmeni</a>
    <?php if ($_SESSION['uloga'] === 'ADMINISTRATOR'): ?>
    <form method="POST" action="?page=dokument-detalji&id=<?= $dokumentId ?>&action=arhiviraj" style="display:inline;">
        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Da li ste sigurni da želite da arhivirate ovaj dokument?')">Arhiviraj</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php $stmt->close(); ?>
