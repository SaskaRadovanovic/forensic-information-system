<?php
/**
 * pages/predmet-izmeni.php — Forma za izmenu predmeta
 *
 * Pre-popunjena forma: naziv, opis.
 */
requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

$predmetId = (int)($_GET['id'] ?? 0);

// ─── Učitavanje predmeta ───────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM predmet WHERE id = ?");
$stmt->bind_param('i', $predmetId);
$stmt->execute();
$predmet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$predmet) {
    flashError('Predmet nije pronađen.');
    header('Location: ?page=predmeti');
    exit;
}
?>

<div class="page-breadcrumb">
    <a href="?page=predmet-detalji&id=<?= $predmetId ?>" class="btn btn-ghost btn-sm">&larr; Nazad na detalje</a>
</div>

<div class="page-eyebrow">Izmena predmeta</div>
<div class="page-title"><?= e($predmet['naziv']) ?></div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=predmet-izmeni&id=<?= $predmetId ?>">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Naziv *</label>
                    <input type="text" name="naziv" value="<?= e($predmet['naziv']) ?>" required>
                </div>

                <div class="form-group full">
                    <label>Opis</label>
                    <textarea name="opis" rows="4"><?= e($predmet['opis'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Sačuvaj izmene</button>
                <a href="?page=predmet-detalji&id=<?= $predmetId ?>" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
