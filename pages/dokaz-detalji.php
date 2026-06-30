<?php
/**
 * pages/dokaz-detalji.php — Detalji dokaza
 *
 * Prikazuje sve podatke o dokazu, specifična obeležja po tipu,
 * lanac čuvanja kao timeline, i dugmad za akcije.
 */

$dokazId = (int)($_GET['id'] ?? 0);

// Rezultat verifikacije iz sesije (ako postoji)
$verifikacija = $_SESSION['verifikacija_rezultat'] ?? null;
unset($_SESSION['verifikacija_rezultat']);

// ─── Učitavanje dokaza sa relacijama ────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT d.*, p.naziv AS predmet_naziv,
           k.ime AS tehnicar_ime, k.prezime AS tehnicar_prezime
    FROM dokaz d
    JOIN predmet p ON p.id = d.predmet_id
    JOIN tehnicar_za_dokaze t ON t.id_korisnik = d.tehnicar_id
    JOIN korisnik k ON k.id = t.id_korisnik
    WHERE d.id = ?
");
$stmt->bind_param('i', $dokazId);
$stmt->execute();
$dokaz = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dokaz) {
    flashError('Dokaz nije pronađen.');
    header('Location: ?page=dokazi');
    exit;
}

// ─── Učitavanje ISA podataka po tipu ────────────────────────────────────────
$isaData = null;
switch ($dokaz['tip_dokaza']) {
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
    $isaData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ─── Lanac čuvanja ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT lc.*, k.ime, k.prezime
    FROM lanac_cuvanja lc
    JOIN korisnik k ON k.id = lc.tehnicar_id
    WHERE lc.dokaz_id = ?
    ORDER BY lc.datum_vreme DESC
");
$stmt->bind_param('i', $dokazId);
$stmt->execute();
$lanac = $stmt->get_result();
?>

<!-- Breadcrumb -->
<div class="page-breadcrumb">
    <a href="?page=dokazi" class="btn btn-ghost btn-sm">&larr; Dokazi</a>
    <span style="color: var(--text-3); font-family: var(--mono); font-size: 12px;">/</span>
    <span style="color: var(--text-2); font-family: var(--mono); font-size: 12px;"><?= e($dokaz['sifra_dokaza']) ?></span>
</div>

<!-- Naslov + status -->
<div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
    <div class="page-title" style="margin-bottom: 0;"><?= e($dokaz['naziv']) ?></div>
    <span class="badge <?= badgeClass($dokaz['status']) ?>"><?= e(badgeLabel($dokaz['status'])) ?></span>
</div>

<!-- Info grid -->
<div class="info-grid">
    <div class="info-item">
        <div class="info-label">Tip dokaza</div>
        <div class="info-value"><span class="badge <?= tipDokazaBadge($dokaz['tip_dokaza']) ?>"><?= e(tipDokazaLabel($dokaz['tip_dokaza'])) ?></span></div>
    </div>
    <div class="info-item">
        <div class="info-label">Datum pronalaska</div>
        <div class="info-value"><?= formatDatumVreme($dokaz['datum_pronalaska']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Lokacija pronalaska</div>
        <div class="info-value"><?= e($dokaz['lokacija_pronalaska'] ?: '—') ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Lokacija skladištenja</div>
        <div class="info-value"><?= e($dokaz['lokacija_skladistenja'] ?: '—') ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Uneo</div>
        <div class="info-value"><?= e($dokaz['tehnicar_ime'] . ' ' . $dokaz['tehnicar_prezime']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Datum prijema</div>
        <div class="info-value"><?= formatDatumVreme($dokaz['datum_prijema']) ?></div>
    </div>
</div>

<!-- Tabovi: Informacije | Lanac čuvanja -->
<div data-tab-group="dokaz">
    <div class="tabs">
        <div class="tab active" data-tab="info" onclick="switchTab('dokaz','info')">Informacije</div>
        <div class="tab" data-tab="lanac" onclick="switchTab('dokaz','lanac')">Lanac čuvanja</div>
    </div>

    <!-- Tab: Informacije -->
    <div class="tab-content active" data-tab="info">
        <?php if ($dokaz['opis']): ?>
        <div class="card">
            <div class="card-header"><h3>Opis</h3></div>
            <div class="card-body"><?= e($dokaz['opis']) ?></div>
        </div>
        <?php endif; ?>

        <!-- Specifična obeležja po tipu -->
        <?php if ($isaData): ?>
        <div class="card">
            <div class="card-header"><h3>Specifična obeležja — <?= e(tipDokazaLabel($dokaz['tip_dokaza'])) ?></h3></div>
            <div class="card-body">
                <div class="info-grid">
                    <?php
                    // Mapiranje kolona ISA tabele na labele
                    $labelMap = [
                        'vrsta_traga' => 'Vrsta traga', 'nacin_uzorkovanja' => 'Način uzorkovanja',
                        'uslovi_cuvanja' => 'Uslovi čuvanja', 'kolicina' => 'Količina',
                        'vrsta_oruzja' => 'Vrsta oružja', 'marka' => 'Marka', 'model' => 'Model',
                        'kalibar' => 'Kalibar', 'serijski_br' => 'Serijski broj',
                        'vrsta_dokumenta' => 'Vrsta dokumenta', 'jezik' => 'Jezik', 'broj_stranica' => 'Broj stranica',
                        'velicina' => 'Veličina', 'vrsta_odevnog_predmeta' => 'Vrsta odevnog predmeta',
                        'boja' => 'Boja', 'stanje' => 'Stanje',
                        'vrsta_uzorka' => 'Vrsta uzorka', 'jedinica_mere' => 'Jedinica mere',
                    ];
                    foreach ($isaData as $key => $val):
                        if ($key === 'id_dokaz') continue;
                        $label = $labelMap[$key] ?? ucfirst(str_replace('_', ' ', $key));
                    ?>
                    <div class="info-item">
                        <div class="info-label"><?= e($label) ?></div>
                        <div class="info-value"><?= e($val ?: '—') ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Dugmad -->
        <div class="action-bar">
            <?php if ($dokaz['status'] !== 'ARHIVIRANO'): ?>
                <?php if (in_array($_SESSION['uloga'], ['TEHNICAR', 'ADMINISTRATOR'])): ?>
                <a href="?page=dokaz-izmeni&id=<?= $dokazId ?>" class="btn btn-outline">Izmeni</a>
                <?php endif; ?>
                <?php if ($_SESSION['uloga'] === 'ADMINISTRATOR'): ?>
                <form method="POST" action="?page=dokaz-detalji&id=<?= $dokazId ?>&action=arhiviraj" style="display:inline;">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Da li ste sigurni da želite da arhivirate ovaj dokaz?')">Arhiviraj</button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Lanac čuvanja -->
    <div class="tab-content" data-tab="lanac">
        <!-- Dugme za verifikaciju — vidljivo za tehničara, istražitelja i admina -->
        <?php if (!in_array($dokaz['status'], ['KOMPROMITOVAN', 'ARHIVIRANO']) && in_array($_SESSION['uloga'], ['TEHNICAR', 'ISTRAZITELJ', 'ADMINISTRATOR'])): ?>
        <div style="margin-bottom: 16px;">
            <form method="POST" action="?page=dokaz-detalji&id=<?= $dokazId ?>&action=verifikuj" style="display:inline;">
                <button type="submit" class="btn btn-outline">Verifikuj lanac</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3>Lanac čuvanja</h3></div>
            <div class="card-body">
                <?php if ($lanac->num_rows > 0): ?>
                <ul class="timeline">
                    <?php while ($lc = $lanac->fetch_assoc()): ?>
                    <li>
                        <div class="tl-ts"><?= formatDatumVreme($lc['datum_vreme']) ?></div>
                        <div class="tl-dot"></div>
                        <div class="tl-body">
                            <strong><?= e($lc['akcija']) ?></strong>
                            <?php if ($lc['napomena']): ?>
                            <br><?= e($lc['napomena']) ?>
                            <?php endif; ?>
                            <br><span style="color:var(--text-3);"><?= e($lc['ime'] . ' ' . $lc['prezime']) ?></span>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state">Nema zapisa u lancu čuvanja</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $stmt->close(); ?>

<!-- Modal za rezultat verifikacije -->
<?php if ($verifikacija): ?>
<div class="modal-overlay" id="modal-verifikacija" style="display:flex;">
    <div class="modal-box">
        <div class="modal-header">
            <h2><?= e($verifikacija['sifra']) ?> — VERIFIKACIJA</h2>
        </div>
        <div class="modal-body">
            <div class="verifikacija-rezultat <?= $verifikacija['validan'] ? 'rezultat-ok' : 'rezultat-fail' ?>">
                <?= $verifikacija['validan'] ? '&#10003; ' : '&#10007; ' ?>
                <?= e($verifikacija['razlog']) ?>
            </div>
            <div class="verifikacija-meta">
                Verifikacija izvršena: <?= e($verifikacija['datum']) ?> &middot; <?= e($verifikacija['korisnik']) ?>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="document.getElementById('modal-verifikacija').style.display='none'">ZATVORI</button>
            <a href="?page=dokaz-detalji&id=<?= $dokazId ?>&action=izvestaj-dokaz" class="btn btn-primary" target="_blank">GENERIŠI IZVEŠTAJ O INTEGRITETU</a>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Modal stilovi za verifikaciju */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.7); display: flex;
    align-items: center; justify-content: center; z-index: 1000;
}
.modal-box {
    background: var(--surface-1, #111); border: 1px solid var(--border, #222);
    border-radius: 8px; width: 90%; max-width: 600px;
}
.modal-header {
    padding: 20px 24px 12px; border-bottom: 1px solid var(--border, #222);
}
.modal-header h2 {
    margin: 0; font-size: 16px; color: var(--text-1, #f0ede8);
    font-family: var(--mono, monospace); letter-spacing: 1px;
}
.modal-body { padding: 20px 24px; font-family: var(--mono); }
.modal-footer {
    padding: 16px 24px; border-top: 1px solid var(--border, #222);
    display: flex; justify-content: flex-end; gap: 12px;
}
.verifikacija-rezultat {
    padding: 16px; border-radius: 6px; margin-bottom: 16px;
    font-family: var(--mono); font-size: 13px; line-height: 1.6;
}
.rezultat-ok {
    background: rgba(34,197,94,0.1); border-left: 3px solid var(--green, #22c55e);
    color: var(--green, #22c55e);
}
.rezultat-fail {
    background: rgba(239,68,68,0.1); border-left: 3px solid var(--red, #ef4444);
    color: var(--red, #ef4444);
}
.verifikacija-meta {
    font-family: var(--mono); font-size: 12px; color: var(--text-3, #555);
}
.modal-footer .btn:disabled {
    opacity: 0.4; cursor: not-allowed;
}
</style>
