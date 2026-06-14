<?php
/**
 * pages/predmet-detalji.php — Detalji predmeta
 *
 * Phase stepper, info grid, tabovi (Dokazi, Analize, Dokumenti),
 * i dugmad za akcije (sledeća faza, izmeni, zatvori, obriši).
 */

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

// ─── Statistike ────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM dokaz WHERE predmet_id = ?");
$stmt->bind_param('i', $predmetId);
$stmt->execute();
$brDokaza = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM zahtev_za_analizu WHERE predmet_id = ?");
$stmt->bind_param('i', $predmetId);
$stmt->execute();
$brAnaliza = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM dokument WHERE predmet_id = ?");
$stmt->bind_param('i', $predmetId);
$stmt->execute();
$brDokumenata = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ─── Faze za stepper ──────────────────────────────────────────────────────
$faze = ['OTVOREN_SLUCAJ', 'PRIKUPLJANJE_DOKAZA', 'ANALIZA_DOKAZA', 'DONOSENJE_ZAKLJUCKA', 'ZATVOREN_SLUCAJ'];
$trenutniIndex = array_search($predmet['faza'], $faze);
?>

<!-- Breadcrumb -->
<div class="page-breadcrumb">
    <a href="?page=predmeti" class="btn btn-ghost btn-sm">&larr; Predmeti</a>
    <span style="color: var(--text-3); font-family: var(--mono); font-size: 12px;">/</span>
    <span style="color: var(--text-2); font-family: var(--mono); font-size: 12px;">#<?= $predmetId ?></span>
</div>

<!-- Naslov + status -->
<div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
    <div class="page-title" style="margin-bottom: 0;"><?= e($predmet['naziv']) ?></div>
    <span class="badge <?= badgeClass($predmet['status']) ?>"><?= e(badgeLabel($predmet['status'])) ?></span>
</div>

<!-- Phase stepper -->
<div class="phase-stepper">
    <?php foreach ($faze as $i => $faza): ?>
        <?php if ($i > 0): ?>
        <div class="phase-connector <?= $i <= $trenutniIndex ? 'done' : '' ?>"></div>
        <?php endif; ?>
        <div class="phase-step <?= $i < $trenutniIndex ? 'done' : ($i === $trenutniIndex ? 'active' : '') ?>">
            <div class="phase-circle"><?= $i < $trenutniIndex ? '✓' : ($i + 1) ?></div>
            <div class="phase-step-label"><?= e(fazaLabel($faza)) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Info grid -->
<div class="info-grid">
    <div class="info-item">
        <div class="info-label">Datum otvaranja</div>
        <div class="info-value"><?= formatDatumVreme($predmet['datum_otvaranja']) ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Tekuća faza</div>
        <div class="info-value"><span class="badge <?= fazaBadge($predmet['faza']) ?>"><?= e(fazaLabel($predmet['faza'])) ?></span></div>
    </div>
    <div class="info-item">
        <div class="info-label">Broj dokaza</div>
        <div class="info-value"><?= $brDokaza ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Zahtevi za analizu</div>
        <div class="info-value"><?= $brAnaliza ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Dokumenti</div>
        <div class="info-value"><?= $brDokumenata ?></div>
    </div>
    <div class="info-item">
        <div class="info-label">Status</div>
        <div class="info-value"><span class="badge <?= badgeClass($predmet['status']) ?>"><?= e(badgeLabel($predmet['status'])) ?></span></div>
    </div>
</div>

<?php if ($predmet['opis']): ?>
<div class="card">
    <div class="card-header"><h3>Opis</h3></div>
    <div class="card-body"><?= e($predmet['opis']) ?></div>
</div>
<?php endif; ?>

<!-- Tabovi: Dokazi | Analize | Dokumenti -->
<div data-tab-group="predmet">
    <div class="tabs">
        <div class="tab active" data-tab="dokazi" onclick="switchTab('predmet','dokazi')">Dokazi (<?= $brDokaza ?>)</div>
        <div class="tab" data-tab="analize" onclick="switchTab('predmet','analize')">Analize (<?= $brAnaliza ?>)</div>
        <div class="tab" data-tab="dokumenti" onclick="switchTab('predmet','dokumenti')">Dokumenti (<?= $brDokumenata ?>)</div>
    </div>

    <!-- Tab: Dokazi -->
    <div class="tab-content active" data-tab="dokazi">
        <div class="card">
            <div class="card-body" style="padding:0;">
                <?php
                $stmt = $conn->prepare("SELECT id, sifra_dokaza, naziv, tip_dokaza, status, datum_prijema FROM dokaz WHERE predmet_id = ? ORDER BY datum_prijema DESC");
                $stmt->bind_param('i', $predmetId);
                $stmt->execute();
                $dokazi = $stmt->get_result();
                ?>
                <?php if ($dokazi->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Šifra</th><th>Naziv</th><th>Tip</th><th>Status</th><th>Datum</th></tr></thead>
                    <tbody>
                        <?php while ($d = $dokazi->fetch_assoc()): ?>
                        <tr>
                            <td><a href="?page=dokaz-detalji&id=<?= $d['id'] ?>"><?= e($d['sifra_dokaza']) ?></a></td>
                            <td><?= e($d['naziv']) ?></td>
                            <td><span class="badge <?= tipDokazaBadge($d['tip_dokaza']) ?>"><?= e(tipDokazaLabel($d['tip_dokaza'])) ?></span></td>
                            <td><span class="badge <?= badgeClass($d['status']) ?>"><?= e(badgeLabel($d['status'])) ?></span></td>
                            <td><?= formatDatum($d['datum_prijema']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">Nema dokaza za ovaj predmet</div>
                <?php endif; ?>
                <?php $stmt->close(); ?>
            </div>
        </div>
    </div>

    <!-- Tab: Analize -->
    <div class="tab-content" data-tab="analize">
        <div class="card">
            <div class="card-body" style="padding:0;">
                <?php
                $stmt = $conn->prepare("
                    SELECT z.id, z.tip_analize, z.status, z.rok,
                           d.sifra_dokaza, d.naziv as dokaz_naziv,
                           k.ime, k.prezime
                    FROM zahtev_za_analizu z
                    JOIN dokaz d ON d.id = z.dokaz_id
                    LEFT JOIN korisnik k ON k.id = z.vestak_id
                    WHERE z.predmet_id = ?
                    ORDER BY z.datum_kreiranja DESC
                ");
                $stmt->bind_param('i', $predmetId);
                $stmt->execute();
                $analize = $stmt->get_result();
                ?>
                <?php if ($analize->num_rows > 0): ?>
                <table>
                    <thead><tr><th>ID</th><th>Dokaz</th><th>Tip</th><th>Veštak</th><th>Rok</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php while ($a = $analize->fetch_assoc()): ?>
                        <tr>
                            <td><a href="?page=analiza-detalji&id=<?= $a['id'] ?>">#<?= $a['id'] ?></a></td>
                            <td><?= e($a['sifra_dokaza']) ?></td>
                            <td><span class="badge badge-blue"><?= e(tipAnalizeLabel($a['tip_analize'])) ?></span></td>
                            <td><?= $a['ime'] ? e($a['ime'] . ' ' . $a['prezime']) : '—' ?></td>
                            <td><?= formatDatum($a['rok']) ?></td>
                            <td><span class="badge <?= badgeClass($a['status']) ?>"><?= e(badgeLabel($a['status'])) ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">Nema analiza za ovaj predmet</div>
                <?php endif; ?>
                <?php $stmt->close(); ?>
            </div>
        </div>
    </div>

    <!-- Tab: Dokumenti -->
    <div class="tab-content" data-tab="dokumenti">
        <div class="card">
            <div class="card-body" style="padding:0;">
                <?php
                $stmt = $conn->prepare("
                    SELECT dok.id, dok.naziv, dok.verzija, dok.status, dok.datum_kreiranja,
                           k.ime AS autor_ime, k.prezime AS autor_prezime
                    FROM dokument dok
                    JOIN korisnik k ON k.id = dok.autor_id
                    WHERE dok.predmet_id = ?
                    ORDER BY dok.datum_kreiranja DESC
                ");
                $stmt->bind_param('i', $predmetId);
                $stmt->execute();
                $dokumenti = $stmt->get_result();
                ?>
                <?php if ($dokumenti->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Naziv</th><th>Autor</th><th>Verzija</th><th>Status</th><th>Datum</th></tr></thead>
                    <tbody>
                        <?php while ($dok = $dokumenti->fetch_assoc()): ?>
                        <tr>
                            <td><a href="?page=dokument-detalji&id=<?= $dok['id'] ?>"><?= e($dok['naziv']) ?></a></td>
                            <td><?= e($dok['autor_ime'] . ' ' . $dok['autor_prezime']) ?></td>
                            <td>v<?= $dok['verzija'] ?></td>
                            <td><span class="badge <?= badgeClass($dok['status']) ?>"><?= e(badgeLabel($dok['status'])) ?></span></td>
                            <td><?= formatDatum($dok['datum_kreiranja']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">Nema dokumenata za ovaj predmet</div>
                <?php endif; ?>
                <?php $stmt->close(); ?>
            </div>
        </div>
    </div>
</div>

<!-- Dugmad -->
<div class="action-bar">
    <?php if ($predmet['status'] === 'AKTIVAN'): ?>
        <?php if (in_array($_SESSION['uloga'], ['ADMINISTRATOR', 'ISTRAZITELJ']) && $trenutniIndex < count($faze) - 1): ?>
        <form method="POST" action="?page=predmet-detalji&id=<?= $predmetId ?>&action=sledeca-faza" style="display:inline;">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Promeniti fazu u: <?= e(fazaLabel($faze[$trenutniIndex + 1])) ?>?')">
                Sledeća faza →
            </button>
        </form>
        <?php endif; ?>

        <?php if (in_array($_SESSION['uloga'], ['ADMINISTRATOR', 'ISTRAZITELJ'])): ?>
        <a href="?page=predmet-izmeni&id=<?= $predmetId ?>" class="btn btn-outline">Izmeni</a>
        <?php endif; ?>

        <?php if ($_SESSION['uloga'] === 'ADMINISTRATOR'): ?>
        <form method="POST" action="?page=predmet-detalji&id=<?= $predmetId ?>&action=zatvori" style="display:inline;">
            <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Zatvoriti ovaj predmet?')">Zatvori</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($_SESSION['uloga'] === 'ADMINISTRATOR'): ?>
        <?php if ($brDokaza == 0 && $brDokumenata == 0 && $brAnaliza == 0): ?>
        <form method="POST" action="?page=predmet-detalji&id=<?= $predmetId ?>&action=obrisi" style="display:inline;">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Da li ste sigurni? Ova akcija je nepovratna.')">Obriši</button>
        </form>
        <?php else: ?>
        <form method="POST" action="?page=predmet-detalji&id=<?= $predmetId ?>&action=obrisi-sve" style="display:inline;">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('UPOZORENJE: Ovo će obrisati predmet i SVE povezane podatke (<?= $brDokaza ?> dokaza, <?= $brAnaliza ?> analiza, <?= $brDokumenata ?> dokumenata). Da li ste apsolutno sigurni?')">Obriši sve</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
