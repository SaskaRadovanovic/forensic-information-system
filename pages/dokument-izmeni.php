<?php
/**
 * pages/dokument-izmeni.php — Forma za izmenu dokumenta
 *
 * Pre-popunjena forma sa postojećim podacima.
 * Polje za razlog izmene (čuva se u dokument_arhiva).
 */

$dokumentId = (int)($_GET['id'] ?? 0);

// ─── Učitavanje dokumenta ──────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM dokument WHERE id = ?");
$stmt->bind_param('i', $dokumentId);
$stmt->execute();
$dokument = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dokument) {
    flashError('Dokument nije pronađen.');
    header('Location: ?page=dokumentacija');
    exit;
}
if ($dokument['status'] === 'ARHIVIRAN') {
    flashError('Arhiviran dokument se ne može menjati.');
    header("Location: ?page=dokument-detalji&id={$dokumentId}");
    exit;
}

// Provera prava za izmenu: admin, autor, ili korisnik sa nivoom IZMENA
$currentUloga = $_SESSION['uloga'];
$currentUserId = $_SESSION['user_id'];
$nemaIzmenuPravo = false;
if ($currentUloga !== 'ADMINISTRATOR' && $dokument['autor_id'] !== $currentUserId) {
    $stmtPravo = $conn->prepare("SELECT nivo_pristupa FROM pravo_pristupa WHERE dokument_id = ? AND korisnik_id = ?");
    $stmtPravo->bind_param('ii', $dokumentId, $currentUserId);
    $stmtPravo->execute();
    $pravo = $stmtPravo->get_result()->fetch_assoc();
    $stmtPravo->close();

    if (!$pravo || $pravo['nivo_pristupa'] !== 'IZMENA') {
        $nemaIzmenuPravo = true;
    }
}

if ($nemaIzmenuPravo): ?>
<div class="page-breadcrumb">
    <a href="?page=dokument-detalji&id=<?= $dokumentId ?>" class="btn btn-ghost btn-sm">&larr; Nazad na detalje</a>
</div>
<div class="card">
    <div class="card-body" style="text-align:center; padding:48px;">
        <div style="font-size:48px; margin-bottom:16px;">&#128274;</div>
        <div class="page-title" style="margin-bottom:8px;">Nemate pravo izmene</div>
        <p style="color:var(--text-2);">Imate samo pravo čitanja ovog dokumenta. Obratite se autoru ili administratoru za pristup izmeni.</p>
        <a href="?page=dokument-detalji&id=<?= $dokumentId ?>" class="btn btn-primary" style="margin-top:16px;">Nazad na detalje</a>
    </div>
</div>
<?php return; endif;

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
?>

<div class="page-breadcrumb">
    <a href="?page=dokument-detalji&id=<?= $dokumentId ?>" class="btn btn-ghost btn-sm">&larr; Nazad na detalje</a>
</div>

<div class="page-eyebrow">Izmena dokumenta</div>
<div class="page-title"><?= e($dokument['naziv']) ?> — v<?= $dokument['verzija'] ?></div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=dokument-izmeni&id=<?= $dokumentId ?>" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label>Naziv *</label>
                    <input type="text" name="naziv" value="<?= e($dokument['naziv']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Tip dokumenta</label>
                    <select name="tip_dokumenta">
                        <option value="">— Izaberi tip —</option>
                        <?php foreach (['Izveštaj','Fotografija','Zapisnik','Veštačenje','Zbirni izveštaj','Ostalo'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($metapodaci['tipDokumenta'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nivo poverljivosti</label>
                    <select name="nivo_poverljivosti">
                        <?php foreach (['JAVNO','INTERNO','POVERLJIVO','STROGO_POVERLJIVO'] as $nivo): ?>
                        <option value="<?= $nivo ?>" <?= ($dokument['nivo_poverljivosti'] ?? 'INTERNO') === $nivo ? 'selected' : '' ?>><?= nivoPoverljivostiLabel($nivo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Opis</label>
                    <textarea name="opis" rows="4"><?= e($metapodaci['opis'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Nova verzija fajla (opciono)</label>
                    <input type="file" name="fajl" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <small style="color:var(--text-3);">Ako ne izaberete fajl, zadržava se postojeći. Maks. 10MB.</small>
                </div>

                <div class="form-group full">
                    <label>Razlog izmene</label>
                    <textarea name="razlog_izmene" rows="2" placeholder="Opišite razlog izmene (opciono)"></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Sačuvaj izmene</button>
                <a href="?page=dokument-detalji&id=<?= $dokumentId ?>" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
