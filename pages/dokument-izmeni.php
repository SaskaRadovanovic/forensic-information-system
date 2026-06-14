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
        <form method="POST" action="?page=dokument-izmeni&id=<?= $dokumentId ?>">
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

                <div class="form-group full">
                    <label>Opis</label>
                    <textarea name="opis" rows="4"><?= e($metapodaci['opis'] ?? '') ?></textarea>
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
