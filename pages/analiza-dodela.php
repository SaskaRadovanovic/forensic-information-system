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

// ─── Lista veštaka ─────────────────────────────────────────────────────────
$vestaci = $conn->query("SELECT k.id, k.ime, k.prezime, v.specijalnost FROM vestak v JOIN korisnik k ON k.id = v.id_korisnik ORDER BY k.prezime");
?>

<div class="page-breadcrumb">
    <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost btn-sm">&larr; Nazad na detalje</a>
</div>

<div class="page-eyebrow"><?= $prvaDodela ? 'Dodela veštaka' : 'Preraspodela veštaka' ?></div>
<div class="page-title">Analiza #<?= $zahtevId ?> — <?= e($zahtev['sifra_dokaza']) ?></div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=analiza-dodela&id=<?= $zahtevId ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Veštak *</label>
                    <select name="vestak_id" required>
                        <option value="">— Izaberi veštaka —</option>
                        <?php while ($v = $vestaci->fetch_assoc()): ?>
                        <option value="<?= $v['id'] ?>" <?= $zahtev['vestak_id'] == $v['id'] ? 'selected' : '' ?>>
                            <?= e($v['ime'] . ' ' . $v['prezime']) ?><?= $v['specijalnost'] ? ' (' . e($v['specijalnost']) . ')' : '' ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Razlog<?= !$prvaDodela ? ' (obavezno za preraspodelu) *' : '' ?></label>
                    <textarea name="razlog" rows="3" <?= !$prvaDodela ? 'required' : '' ?> placeholder="Razlog dodele/preraspodele..."></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary"><?= $prvaDodela ? 'Dodeli' : 'Preraspodeli' ?></button>
                <a href="?page=analiza-detalji&id=<?= $zahtevId ?>" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
