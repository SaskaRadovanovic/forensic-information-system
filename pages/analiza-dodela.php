<?php
/**
 * pages/analiza-dodela.php — Forma za dodelu veštaka na analizu
 *
 * Select za veštaka. Ako je preraspodela, razlog je obavezan.
 */
requireRole('ADMINISTRATOR', 'ISTRAZITELJ');

$zahtevId = (int)($_GET['id'] ?? 0);

// ─── Učitavanje zahteva ────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT z.*, d.sifra_dokaza, d.naziv AS dokaz_naziv
    FROM zahtev_za_analizu z
    JOIN dokaz d ON d.id = z.dokaz_id
    WHERE z.id = ?
");
$stmt->bind_param('i', $zahtevId);
$stmt->execute();
$zahtev = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$zahtev) {
    flashError('Zahtev ne postoji.');
    header('Location: ?page=analize');
    exit;
}

$prvaDodela = is_null($zahtev['vestak_id']);

// ─── Trenutni veštak ────────────────────────────────────────────────────────
$trenutniVestak = null;
if ($zahtev['vestak_id']) {
    $stmtV = $conn->prepare("
        SELECT k.ime, k.prezime, k.email, v.specijalnost, v.sertifikat_br, v.id_vestak
        FROM vestak v JOIN korisnik k ON k.id = v.id_korisnik
        WHERE v.id_korisnik = ?
    ");
    $stmtV->bind_param('i', $zahtev['vestak_id']);
    $stmtV->execute();
    $trenutniVestak = $stmtV->get_result()->fetch_assoc();
    $stmtV->close();
}

// ─── Istorija dodela ────────────────────────────────────────────────────────
$stmtH = $conn->prepare("
    SELECT idod.datum_dodele, idod.razlog_promene,
           kv.ime AS vestak_ime, kv.prezime AS vestak_prezime,
           v.specijalnost,
           kd.ime AS dodelio_ime, kd.prezime AS dodelio_prezime
    FROM istorija_dodele idod
    JOIN korisnik kv ON kv.id = idod.vestak_id
    JOIN vestak v    ON v.id_korisnik = idod.vestak_id
    JOIN korisnik kd ON kd.id = idod.dodelio_id
    WHERE idod.zahtev_id = ?
    ORDER BY idod.datum_dodele DESC
");
$stmtH->bind_param('i', $zahtevId);
$stmtH->execute();
$istorijaDodela = $stmtH->get_result();
$stmtH->close();

// ─── Lista veštaka ─────────────────────────────────────────────────────────
$vestaci = $conn->query("SELECT k.id, k.ime, k.prezime, v.specijalnost FROM vestak v JOIN korisnik k ON k.id = v.id_korisnik ORDER BY k.prezime");
?>

<div class="page-breadcrumb">
    <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost btn-sm">&larr; Nazad na detalje</a>
</div>

<div class="page-eyebrow"><?= $prvaDodela ? 'Dodela veštaka' : 'Preraspodela veštaka' ?></div>
<div class="page-title">Analiza #<?= $zahtevId ?> — <?= e($zahtev['sifra_dokaza']) ?></div>

<!-- Trenutni veštak -->
<?php if ($trenutniVestak): ?>
<div class="card" style="margin-bottom:16px; border-color:var(--yellow-dim);">
    <div class="card-header">
        <h3>Trenutno dodeljen veštak</h3>
        <span class="badge badge-yellow">Aktivan</span>
    </div>
    <div class="info-grid" style="margin-bottom:0; border:none;">
        <div class="info-item">
            <div class="info-label">Ime i prezime</div>
            <div class="info-value"><?= e($trenutniVestak['ime'] . ' ' . $trenutniVestak['prezime']) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Specijalnost</div>
            <div class="info-value"><?= $trenutniVestak['specijalnost'] ? e($trenutniVestak['specijalnost']) : '<span style="color:var(--text-3)">—</span>' ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">ID veštaka</div>
            <div class="info-value"><?= e($trenutniVestak['id_vestak']) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Email</div>
            <div class="info-value"><?= e($trenutniVestak['email']) ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Sertifikat br.</div>
            <div class="info-value"><?= $trenutniVestak['sertifikat_br'] ? e($trenutniVestak['sertifikat_br']) : '<span style="color:var(--text-3)">—</span>' ?></div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-yellow" style="margin-bottom:16px;">Analiza još nema dodeljenog veštaka.</div>
<?php endif; ?>

<!-- Forma za dodelu / preraspodelu -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3><?= $prvaDodela ? 'Dodeli veštaka' : 'Preraspodeli veštaka' ?></h3></div>
    <div class="card-body">
        <form method="POST" action="?page=analiza-dodela&id=<?= $zahtevId ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Veštak *</label>
                    <select name="vestak_id" required>
                        <option value="">— Izaberi veštaka —</option>
                        <?php while ($v = $vestaci->fetch_assoc()): ?>
                        <option value="<?= $v['id'] ?>"
                            <?= $zahtev['vestak_id'] == $v['id'] ? 'selected' : '' ?>
                            <?= $zahtev['vestak_id'] == $v['id'] ? 'style="color:var(--text-3);"' : '' ?>>
                            <?= e($v['ime'] . ' ' . $v['prezime']) ?>
                            <?= $v['specijalnost'] ? ' (' . e($v['specijalnost']) . ')' : '' ?>
                            <?= $zahtev['vestak_id'] == $v['id'] ? ' — trenutni' : '' ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Razlog<?= !$prvaDodela ? ' preraspodele *' : '' ?></label>
                    <textarea name="razlog" rows="3" <?= !$prvaDodela ? 'required' : '' ?>
                        placeholder="<?= $prvaDodela ? 'Opcionalna napomena uz dodelu...' : 'Obavezno — objasnite razlog preraspodele...' ?>"></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary"><?= $prvaDodela ? 'Dodeli veštaka' : 'Potvrdi preraspodelu' ?></button>
                <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>

<!-- Istorija dodela -->
<?php if ($istorijaDodela->num_rows > 0): ?>
<div class="card">
    <div class="card-header"><h3>Istorija dodela</h3></div>
    <div class="card-body">
        <ul class="timeline">
            <?php while ($unos = $istorijaDodela->fetch_assoc()): ?>
            <li>
                <div class="tl-ts"><?= formatDatumVreme($unos['datum_dodele']) ?></div>
                <div class="tl-dot"></div>
                <div class="tl-body">
                    <strong><?= e($unos['vestak_ime'] . ' ' . $unos['vestak_prezime']) ?></strong>
                    <?php if ($unos['specijalnost']): ?>
                    <span style="color:var(--text-3);"> · <?= e($unos['specijalnost']) ?></span>
                    <?php endif; ?>
                    <?php if ($unos['razlog_promene']): ?>
                    <br><span style="color:var(--text-2);">Razlog: <?= e($unos['razlog_promene']) ?></span>
                    <?php endif; ?>
                    <br><span style="color:var(--text-3);">Dodelio: <?= e($unos['dodelio_ime'] . ' ' . $unos['dodelio_prezime']) ?></span>
                </div>
            </li>
            <?php endwhile; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
