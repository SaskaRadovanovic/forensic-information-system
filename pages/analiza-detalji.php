<?php
/**
 * pages/analiza-detalji.php — Detalji zahteva za analizu
 *
 * Prikazuje podatke o zahtevu, dugmad zavisno od statusa i uloge,
 * istoriju statusa, istoriju dodela, i rezultat analize.
 */

$zahtevId = (int)($_GET['id'] ?? 0);

// ─── Učitavanje zahteva sa relacijama ──────────────────────────────────────
$stmt = $conn->prepare("
    SELECT z.*,
           d.sifra_dokaza, d.naziv AS dokaz_naziv,
           p.naziv AS predmet_naziv,
           ki.ime AS istrazitelj_ime, ki.prezime AS istrazitelj_prezime,
           kv.ime AS vestak_ime, kv.prezime AS vestak_prezime
    FROM zahtev_za_analizu z
    JOIN dokaz d ON d.id = z.dokaz_id
    JOIN predmet p ON p.id = z.predmet_id
    JOIN korisnik ki ON ki.id = z.istrazitelj_id
    LEFT JOIN korisnik kv ON kv.id = z.vestak_id
    WHERE z.id = ?
");
$stmt->bind_param('i', $zahtevId);
$stmt->execute();
$zahtev = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$zahtev) {
    flashError('Zahtev za analizu nije pronađen.');
    header('Location: ?page=analize');
    exit;
}

$uloga  = $_SESSION['uloga'];
$userId = $_SESSION['user_id'];
$status = $zahtev['status'];

// ─── Rezultat analize ──────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT r.*, k.ime, k.prezime FROM rezultat_analize r JOIN korisnik k ON k.id = r.uneao_id WHERE r.zahtev_id = ?");
$stmt->bind_param('i', $zahtevId);
$stmt->execute();
$rezultat = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ─── Istorija statusa ──────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT isa.*, k.ime, k.prezime FROM istorija_statusa_analize isa JOIN korisnik k ON k.id = isa.inicirao_id WHERE isa.zahtev_id = ? ORDER BY isa.datum_vreme DESC");
$stmt->bind_param('i', $zahtevId);
$stmt->execute();
$istorijaStatusa = $stmt->get_result();
$stmt->close();

// ─── Istorija dodela ───────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id.*, kv.ime AS vestak_ime, kv.prezime AS vestak_prezime, kd.ime AS dodelio_ime, kd.prezime AS dodelio_prezime FROM istorija_dodele id JOIN korisnik kv ON kv.id = id.vestak_id JOIN korisnik kd ON kd.id = id.dodelio_id WHERE id.zahtev_id = ? ORDER BY id.datum_dodele DESC");
$stmt->bind_param('i', $zahtevId);
$stmt->execute();
$istorijaDodela = $stmt->get_result();
$stmt->close();
?>

<!-- Breadcrumb -->
<div class="page-breadcrumb">
    <a href="?page=analize" class="btn btn-ghost btn-sm">&larr; Analize</a>
    <span style="color: var(--text-3); font-family: var(--mono); font-size: 12px;">/</span>
    <span style="color: var(--text-2); font-family: var(--mono); font-size: 12px;">#<?= $zahtevId ?></span>
</div>

<!-- Naslov + status -->
<div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
    <div class="page-title" style="margin-bottom: 0;">Analiza #<?= $zahtevId ?></div>
    <span class="badge <?= badgeClass($status) ?>"><?= e(badgeLabel($status)) ?></span>
    <span class="badge badge-blue"><?= e(tipAnalizeLabel($zahtev['tip_analize'])) ?></span>
</div>

<!-- Info grid -->
<div class="info-grid">
    <div class="info-item">
        <div class="info-label">Dokaz</div>
        <div class="info-value"><a href="?page=dokaz-detalji&id=<?= $zahtev['dokaz_id'] ?>" style="color:var(--yellow);"><?= e($zahtev['sifra_dokaza'] . ' — ' . $zahtev['dokaz_naziv']) ?></a></div>
    </div>
    <div class="info-item">
        <div class="info-label">Predmet</div>
        <div class="info-value"><a href="?page=predmet-detalji&id=<?= $zahtev['predmet_id'] ?>" style="color:var(--yellow);"><?= e($zahtev['predmet_naziv']) ?></a></div>
    </div>
    <div class="info-item">
        <div class="info-label">Istražitelj</div>
        <div class="info-value"><?= e($zahtev['istrazitelj_ime'] . ' ' . $zahtev['istrazitelj_prezime']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Veštak</div>
        <div class="info-value"><?= $zahtev['vestak_ime'] ? e($zahtev['vestak_ime'] . ' ' . $zahtev['vestak_prezime']) : '<span style="color:var(--text-3)">Nedodeljen</span>' ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Rok</div>
        <div class="info-value"><?= formatDatum($zahtev['rok']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Datum kreiranja</div>
        <div class="info-value"><?= formatDatumVreme($zahtev['datum_kreiranja']) ?></div>
    </div>
</div>

<?php if ($zahtev['opis']): ?>
<div class="card">
    <div class="card-header"><h3>Opis</h3></div>
    <div class="card-body"><?= e($zahtev['opis']) ?></div>
</div>
<?php endif; ?>

<!-- Dugmad zavisno od statusa i uloge -->
<div class="action-bar">
    <?php if ($status === 'KREIRAN'): ?>
        <?php if (in_array($uloga, ['ADMINISTRATOR', 'ISTRAZITELJ'])): ?>
        <a href="?page=analiza-dodela&id=<?= $zahtevId ?>" class="btn btn-primary">Dodeli veštaka</a>
        <a href="?page=analiza-izmeni&id=<?= $zahtevId ?>" class="btn btn-outline">Izmeni</a>
        <?php endif; ?>
        <?php if ($uloga === 'ADMINISTRATOR'): ?>
        <form method="POST" action="?page=analiza-detalji&id=<?= $zahtevId ?>&action=obrisi" style="display:inline;">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Obrisati zahtev?')">Obriši</button>
        </form>
        <?php endif; ?>
    <?php elseif ($status === 'DODELJEN'): ?>
        <?php if ($uloga === 'VESTAK' && $zahtev['vestak_id'] == $userId): ?>
        <form method="POST" action="?page=analiza-detalji&id=<?= $zahtevId ?>&action=zapocni" style="display:inline;">
            <button type="submit" class="btn btn-primary">Započni analizu</button>
        </form>
        <?php endif; ?>
        <?php if (in_array($uloga, ['ADMINISTRATOR', 'ISTRAZITELJ'])): ?>
        <a href="?page=analiza-dodela&id=<?= $zahtevId ?>" class="btn btn-outline">Preraspodeli</a>
        <a href="?page=analiza-izmeni&id=<?= $zahtevId ?>" class="btn btn-outline">Izmeni</a>
        <?php endif; ?>
    <?php elseif ($status === 'U_TOKU'): ?>
        <?php if ($uloga === 'VESTAK' && $zahtev['vestak_id'] == $userId && !$rezultat): ?>
        <a href="?page=analiza-rezultat&id=<?= $zahtevId ?>" class="btn btn-primary">Unesi rezultat</a>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (in_array($uloga, ['ADMINISTRATOR', 'ISTRAZITELJ']) && !in_array($status, ['ZAVRSEN', 'ODBIJEN'])): ?>
    <form method="POST" action="?page=analiza-detalji&id=<?= $zahtevId ?>&action=odbij" style="display:inline;">
        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Odbiti zahtev?')">Odbij</button>
    </form>
    <?php endif; ?>
</div>

<!-- Rezultat analize -->
<?php if ($rezultat): ?>
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3>Rezultat analize</h3>
        <?php if ($rezultat['verifikovan']): ?>
        <span class="badge badge-green">Verifikovan</span>
        <?php else: ?>
        <span class="badge badge-yellow">Čeka verifikaciju</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div style="white-space:pre-wrap; font-family:var(--mono); font-size:12px; color:var(--text-1); line-height:1.6;"><?= e($rezultat['sadrzaj']) ?></div>
        <div style="margin-top:12px; font-family:var(--mono); font-size:10px; color:var(--text-3);">
            Uneo: <?= e($rezultat['ime'] . ' ' . $rezultat['prezime']) ?> · <?= formatDatumVreme($rezultat['datum_unosa']) ?>
        </div>
        <?php if (!$rezultat['verifikovan'] && in_array($uloga, ['ISTRAZITELJ', 'ADMINISTRATOR'])): ?>
        <div style="margin-top:12px;">
            <form method="POST" action="?page=analiza-detalji&id=<?= $zahtevId ?>&action=verifikuj" style="display:inline;">
                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Verifikovati rezultat?')">Verifikuj rezultat</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Istorija statusa -->
<?php if ($istorijaStatusa->num_rows > 0): ?>
<div class="card" style="margin-top:20px;">
    <div class="card-header"><h3>Istorija statusa</h3></div>
    <div class="card-body">
        <ul class="timeline">
            <?php while ($is = $istorijaStatusa->fetch_assoc()): ?>
            <li>
                <div class="tl-ts"><?= formatDatumVreme($is['datum_vreme']) ?></div>
                <div class="tl-dot"></div>
                <div class="tl-body">
                    <strong><?= $is['stari_status'] ? e(badgeLabel($is['stari_status'])) . ' → ' : '' ?><?= e(badgeLabel($is['novi_status'])) ?></strong>
                    <?php if ($is['napomena']): ?><br><?= e($is['napomena']) ?><?php endif; ?>
                    <br><span style="color:var(--text-3);"><?= e($is['ime'] . ' ' . $is['prezime']) ?></span>
                </div>
            </li>
            <?php endwhile; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- Istorija dodela -->
<?php if ($istorijaDodela->num_rows > 0): ?>
<div class="card" style="margin-top:20px;">
    <div class="card-header"><h3>Istorija dodela</h3></div>
    <div class="card-body">
        <ul class="timeline">
            <?php while ($id = $istorijaDodela->fetch_assoc()): ?>
            <li>
                <div class="tl-ts"><?= formatDatumVreme($id['datum_dodele']) ?></div>
                <div class="tl-dot"></div>
                <div class="tl-body">
                    <strong>Dodeljen: <?= e($id['vestak_ime'] . ' ' . $id['vestak_prezime']) ?></strong>
                    <?php if ($id['razlog_promene']): ?><br>Razlog: <?= e($id['razlog_promene']) ?><?php endif; ?>
                    <br><span style="color:var(--text-3);">Dodelio: <?= e($id['dodelio_ime'] . ' ' . $id['dodelio_prezime']) ?></span>
                </div>
            </li>
            <?php endwhile; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
