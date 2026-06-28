<?php
/**
 * pages/dokaz-izmeni.php — Forma za izmenu dokaza
 *
 * Ista forma kao dokaz-novi.php ali pre-popunjena sa postojećim podacima.
 */
requireRole('TEHNICAR', 'ADMINISTRATOR');

$dokazId = (int)($_GET['id'] ?? 0);

// ─── Učitavanje dokaza ──────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM dokaz WHERE id = ?");
$stmt->bind_param('i', $dokazId);
$stmt->execute();
$dokaz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dokaz) {
    flashError('Dokaz nije pronađen.');
    header('Location: ?page=dokazi');
    exit;
}

if ($dokaz['status'] === 'ARHIVIRANO') {
    flashError('Arhivirani dokaz se ne može menjati.');
    header("Location: ?page=dokaz-detalji&id={$dokazId}");
    exit;
}

// ─── Učitavanje ISA podataka ────────────────────────────────────────────────
$isaData = [];
$tip = $dokaz['tip_dokaza'];
switch ($tip) {
    case 'BIOLOSKI_TRAG':
        $stmt = $conn->prepare("SELECT * FROM bioloski_trag WHERE id_dokaz = ?");
        break;
    case 'ORUZJE':
        $stmt = $conn->prepare("SELECT * FROM oruzje WHERE id_dokaz = ?");
        break;
    case 'DOKUMENT':
        $stmt = $conn->prepare("SELECT * FROM dokument_dokaz WHERE id_dokaz = ?");
        break;
    case 'ODECA':
        $stmt = $conn->prepare("SELECT * FROM odeca WHERE id_dokaz = ?");
        break;
    case 'UZORAK':
        $stmt = $conn->prepare("SELECT * FROM uzorak WHERE id_dokaz = ?");
        break;
}
if (isset($stmt)) {
    $stmt->bind_param('i', $dokazId);
    $stmt->execute();
    $isaData = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
}

// Predmeti za select (read-only — ne može se menjati predmet)
$predmetiRes = $conn->query("SELECT id, naziv FROM predmet ORDER BY naziv");
?>

<div class="page-breadcrumb">
    <a href="?page=dokaz-detalji&id=<?= $dokazId ?>" class="btn btn-ghost btn-sm">&larr; Nazad na detalje</a>
</div>

<div class="page-eyebrow">Izmena dokaza</div>
<div class="page-title"><?= e($dokaz['sifra_dokaza']) ?> — <?= e($dokaz['naziv']) ?></div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=dokaz-izmeni&id=<?= $dokazId ?>">
            <input type="hidden" name="tip_dokaza" value="<?= e($tip) ?>">

            <div class="form-grid">
                <div class="form-section-title">Opšti podaci</div>

                <div class="form-group">
                    <label>Naziv *</label>
                    <input type="text" name="naziv" value="<?= e($dokaz['naziv']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Tip dokaza</label>
                    <input type="text" value="<?= e(tipDokazaLabel($tip)) ?>" disabled>
                </div>

                <div class="form-group">
                    <label>Datum pronalaska</label>
                    <input type="datetime-local" name="datum_pronalaska" value="<?= $dokaz['datum_pronalaska'] ? date('Y-m-d\TH:i', strtotime($dokaz['datum_pronalaska'])) : '' ?>">
                </div>

                <div class="form-group">
                    <label>Lokacija pronalaska</label>
                    <input type="text" name="lokacija_pronalaska" value="<?= e($dokaz['lokacija_pronalaska'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Lokacija skladištenja</label>
                    <input type="text" name="lokacija_skladistenja" value="<?= e($dokaz['lokacija_skladistenja'] ?? '') ?>">
                </div>

                <div class="form-group full">
                    <label>Opis</label>
                    <textarea name="opis" rows="3"><?= e($dokaz['opis'] ?? '') ?></textarea>
                </div>

                <!-- Specifična obeležja po tipu -->
                <?php if ($tip === 'BIOLOSKI_TRAG'): ?>
                <div class="form-section-title">Biološki trag — specifična obeležja</div>
                <div class="form-group"><label>Vrsta traga</label><input type="text" name="vrsta_traga" value="<?= e($isaData['vrsta_traga'] ?? '') ?>"></div>
                <div class="form-group"><label>Način uzorkovanja</label><input type="text" name="nacin_uzorkovanja" value="<?= e($isaData['nacin_uzorkovanja'] ?? '') ?>"></div>
                <div class="form-group"><label>Uslovi čuvanja</label><input type="text" name="uslovi_cuvanja" value="<?= e($isaData['uslovi_cuvanja'] ?? '') ?>"></div>
                <div class="form-group"><label>Količina</label><input type="text" name="kolicina" value="<?= e($isaData['kolicina'] ?? '') ?>"></div>

                <?php elseif ($tip === 'ORUZJE'): ?>
                <div class="form-section-title">Oružje — specifična obeležja</div>
                <div class="form-group"><label>Vrsta oružja</label><input type="text" name="vrsta_oruzja" value="<?= e($isaData['vrsta_oruzja'] ?? '') ?>"></div>
                <div class="form-group"><label>Marka</label><input type="text" name="marka" value="<?= e($isaData['marka'] ?? '') ?>"></div>
                <div class="form-group"><label>Model</label><input type="text" name="model_oruzja" value="<?= e($isaData['model'] ?? '') ?>"></div>
                <div class="form-group"><label>Kalibar</label><input type="text" name="kalibar" value="<?= e($isaData['kalibar'] ?? '') ?>"></div>
                <div class="form-group"><label>Serijski broj</label><input type="text" name="serijski_br" value="<?= e($isaData['serijski_br'] ?? '') ?>"></div>

                <?php elseif ($tip === 'DOKUMENT'): ?>
                <div class="form-section-title">Dokument — specifična obeležja</div>
                <div class="form-group"><label>Vrsta dokumenta</label><input type="text" name="vrsta_dokumenta" value="<?= e($isaData['vrsta_dokumenta'] ?? '') ?>"></div>
                <div class="form-group"><label>Jezik</label><input type="text" name="jezik" value="<?= e($isaData['jezik'] ?? '') ?>"></div>
                <div class="form-group"><label>Broj stranica</label><input type="number" name="broj_stranica" min="0" value="<?= e($isaData['broj_stranica'] ?? '') ?>"></div>

                <?php elseif ($tip === 'ODECA'): ?>
                <div class="form-section-title">Odeća — specifična obeležja</div>
                <div class="form-group"><label>Veličina</label><input type="text" name="velicina" value="<?= e($isaData['velicina'] ?? '') ?>"></div>
                <div class="form-group"><label>Vrsta odevnog predmeta</label><input type="text" name="vrsta_odevnog_predmeta" value="<?= e($isaData['vrsta_odevnog_predmeta'] ?? '') ?>"></div>
                <div class="form-group"><label>Boja</label><input type="text" name="boja" value="<?= e($isaData['boja'] ?? '') ?>"></div>
                <div class="form-group"><label>Stanje</label><input type="text" name="stanje" value="<?= e($isaData['stanje'] ?? '') ?>"></div>

                <?php elseif ($tip === 'UZORAK'): ?>
                <div class="form-section-title">Uzorak — specifična obeležja</div>
                <div class="form-group"><label>Vrsta uzorka</label><input type="text" name="vrsta_uzorka" value="<?= e($isaData['vrsta_uzorka'] ?? '') ?>"></div>
                <div class="form-group"><label>Količina</label><input type="text" name="kolicina_uzorka" value="<?= e($isaData['kolicina'] ?? '') ?>"></div>
                <div class="form-group"><label>Jedinica mere</label><input type="text" name="jedinica_mere" value="<?= e($isaData['jedinica_mere'] ?? '') ?>"></div>
                <div class="form-group"><label>Način uzorkovanja</label><input type="text" name="nacin_uzorkovanja" value="<?= e($isaData['nacin_uzorkovanja'] ?? '') ?>"></div>
                <div class="form-group"><label>Uslovi čuvanja</label><input type="text" name="uslovi_cuvanja" value="<?= e($isaData['uslovi_cuvanja'] ?? '') ?>"></div>
                <?php endif; ?>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Sačuvaj izmene</button>
                <a href="?page=dokaz-detalji&id=<?= $dokazId ?>" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
