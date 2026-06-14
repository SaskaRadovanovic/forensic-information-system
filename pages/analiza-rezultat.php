<?php
/**
 * pages/analiza-rezultat.php — Forma za unos rezultata analize
 *
 * Polje: sadržaj rezultata. Samo VESTAK, samo U_TOKU.
 */
requireRole('VESTAK');

$zahtevId = (int)($_GET['id'] ?? 0);

// ─── Provera da je zahtev U_TOKU i dodeljen ovom veštaku ──────────────────
$stmt = $conn->prepare("
    SELECT z.*, d.sifra_dokaza, d.naziv AS dokaz_naziv
    FROM zahtev_za_analizu z
    JOIN dokaz d ON d.id = z.dokaz_id
    WHERE z.id = ? AND z.vestak_id = ? AND z.status = 'U_TOKU'
");
$userId = $_SESSION['user_id'];
$stmt->bind_param('ii', $zahtevId, $userId);
$stmt->execute();
$zahtev = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$zahtev) {
    flashError('Zahtev ne postoji, nije vam dodeljen, ili nije u statusu U_TOKU.');
    header('Location: ?page=analize');
    exit;
}
?>

<div class="page-breadcrumb">
    <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost btn-sm">&larr; Nazad na detalje</a>
</div>

<div class="page-eyebrow">Unos rezultata</div>
<div class="page-title">Analiza #<?= $zahtevId ?> — <?= e($zahtev['sifra_dokaza']) ?></div>

<div class="card">
    <div class="card-body">
        <div class="info-grid" style="margin-bottom:20px;">
            <div class="info-item">
                <div class="info-label">Tip analize</div>
                <div class="info-value"><?= e(tipAnalizeLabel($zahtev['tip_analize'])) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Dokaz</div>
                <div class="info-value"><?= e($zahtev['sifra_dokaza'] . ' — ' . $zahtev['dokaz_naziv']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Opis zahteva</div>
                <div class="info-value"><?= e($zahtev['opis'] ?: '—') ?></div>
            </div>
        </div>

        <form method="POST" action="?page=analiza-rezultat&id=<?= $zahtevId ?>">
            <div class="form-group">
                <label>Sadržaj rezultata *</label>
                <textarea name="sadrzaj" rows="10" required placeholder="Unesite rezultat analize..."></textarea>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Sačuvaj rezultat</button>
                <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
