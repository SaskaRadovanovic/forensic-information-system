<?php
/**
 * pages/analiza-izmeni.php — Forma za izmenu zahteva za analizu
 *
 * Pre-popunjena forma: tip, opis, rok, prag. Samo KREIRAN/DODELJEN.
 */
requireRole('ISTRAZITELJ', 'ADMINISTRATOR');

$zahtevId = (int)($_GET['id'] ?? 0);

// ─── Učitavanje zahteva ────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM zahtev_za_analizu WHERE id = ?");
$stmt->bind_param('i', $zahtevId);
$stmt->execute();
$zahtev = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$zahtev || !in_array($zahtev['status'], ['KREIRAN', 'DODELJEN'])) {
    flashError('Zahtev ne postoji ili se ne može menjati.');
    header('Location: ?page=analize');
    exit;
}
?>

<div class="page-breadcrumb">
    <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost btn-sm">&larr; Nazad na detalje</a>
</div>

<div class="page-eyebrow">Izmena analize</div>
<div class="page-title">Analiza #<?= $zahtevId ?></div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=analiza-izmeni&id=<?= $zahtevId ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Tip analize *</label>
                    <select name="tip_analize" required>
                        <?php foreach (['BALISTICKA','DNK','DIGITALNA','HEMIJSKA','TOKSIKOLOSKA','DOKUMENTOLOSKA','DRUGA'] as $t): ?>
                        <option value="<?= $t ?>" <?= $zahtev['tip_analize'] === $t ? 'selected' : '' ?>><?= tipAnalizeLabel($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Rok</label>
                    <input type="date" name="rok" value="<?= $zahtev['rok'] ? date('Y-m-d', strtotime($zahtev['rok'])) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Prag upozorenja (dana)</label>
                    <input type="number" name="prag_upozorenja_dana" value="<?= $zahtev['prag_upozorenja_dana'] ?>" min="1" max="30">
                </div>

                <div class="form-group full">
                    <label>Opis</label>
                    <textarea name="opis" rows="4"><?= e($zahtev['opis'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Sačuvaj izmene</button>
                <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
