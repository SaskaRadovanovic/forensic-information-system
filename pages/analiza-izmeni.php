<?php
/**
 * pages/analiza-izmeni.php — Forma za izmenu zahteva za analizu
 *
 * Pre-popunjena forma: tip, opis, rok, prag. Samo KREIRAN/DODELJEN.
 * Na dnu prikazuje istoriju izmena.
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

// ─── Istorija izmena ───────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT iiz.*, k.ime, k.prezime
    FROM istorija_izmene_zahteva iiz
    JOIN korisnik k ON k.id = iiz.korisnik_id
    WHERE iiz.zahtev_id = ?
    ORDER BY iiz.datum_vreme DESC
");
$stmt->bind_param('i', $zahtevId);
$stmt->execute();
$istorijaIzmena = $stmt->get_result();
$stmt->close();
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
                    <select name="tip_analize" id="sel-tip" required>
                        <?php foreach (['BALISTICKA','DNK','DIGITALNA','HEMIJSKA','TOKSIKOLOSKA','DOKUMENTOLOSKA','DRUGA'] as $t): ?>
                        <option value="<?= $t ?>" <?= $zahtev['tip_analize'] === $t ? 'selected' : '' ?>><?= tipAnalizeLabel($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Rok</label>
                    <input type="date" name="rok" value="<?= $zahtev['rok'] ? date('Y-m-d', strtotime($zahtev['rok'])) : '' ?>" min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label>Datum početka</label>
                    <input type="date" name="datum_pocetka" value="<?= $zahtev['datum_pocetka'] ? date('Y-m-d', strtotime($zahtev['datum_pocetka'])) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Prag upozorenja (dana)</label>
                    <input type="number" name="prag_upozorenja_dana" value="<?= $zahtev['prag_upozorenja_dana'] ?>" min="1" max="30">
                </div>

                <div class="form-group full">
                    <label>Opis zahteva</label>
                    <textarea name="opis" rows="3" placeholder="Opišite šta treba analizirati, posebne napomene za veštaka..."><?= e($zahtev['opis'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Sačuvaj izmene</button>
                <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>


<!-- Istorija izmena -->
<?php if ($istorijaIzmena->num_rows > 0): ?>
<div class="card" style="margin-top:20px;">
    <div class="card-header"><h3>Istorija izmena</h3></div>
    <div class="card-body">
        <ul class="timeline">
            <?php while ($iiz = $istorijaIzmena->fetch_assoc()): ?>
            <li>
                <div class="tl-ts"><?= formatDatumVreme($iiz['datum_vreme']) ?></div>
                <div class="tl-dot"></div>
                <div class="tl-body">
                    <strong><?= e($iiz['polje']) ?></strong>
                    <br><span style="color:var(--text-3);">
                        <?= e($iiz['stara_vrednost'] ?? '—') ?> → <?= e($iiz['nova_vrednost']) ?>
                    </span>
                    <br><span style="color:var(--text-3);"><?= e($iiz['ime'] . ' ' . $iiz['prezime']) ?></span>
                </div>
            </li>
            <?php endwhile; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
