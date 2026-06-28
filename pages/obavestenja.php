<?php
/**
 * pages/obavestenja.php — Lista obaveštenja korisnika
 *
 * Prikazuje obaveštenja za ulogovanog korisnika.
 * Nepročitana imaju gold border. Dugmad za označavanje pročitanim.
 */

$userId = $_SESSION['user_id'];

// ─── Učitavanje obaveštenja ────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT o.*, z.tip_analize
    FROM obavestenje o
    LEFT JOIN zahtev_za_analizu z ON z.id = o.zahtev_id
    WHERE o.korisnik_id = ?
    ORDER BY o.datum_vreme DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$obavestenja = $stmt->get_result();

// Brojač nepročitanih
$stmt2 = $conn->prepare("SELECT COUNT(*) as cnt FROM obavestenje WHERE korisnik_id = ? AND procitano = 0");
$stmt2->bind_param('i', $userId);
$stmt2->execute();
$neprocitano = $stmt2->get_result()->fetch_assoc()['cnt'];
$stmt2->close();
?>

<div class="page-eyebrow">Sistem</div>
<div class="page-title">Obaveštenja</div>

<?php if ($neprocitano > 0): ?>
<div style="margin-bottom:16px; display:flex; align-items:center; gap:12px;">
    <span style="font-family:var(--mono); font-size:11px; color:var(--text-2);"><?= $neprocitano ?> nepročitanih</span>
    <form method="POST" action="?page=obavestenja&action=procitaj-sve" style="display:inline;">
        <button type="submit" class="btn btn-outline btn-sm">Označi sve pročitano</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if ($obavestenja->num_rows > 0): ?>
            <?php while ($o = $obavestenja->fetch_assoc()): ?>
            <div class="notif-item <?= !$o['procitano'] ? 'unread' : '' ?>">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <div class="notif-title">
                            <?php if ($o['tip']): ?>
                            <span class="badge badge-blue" style="margin-right:6px;"><?= e($o['tip']) ?></span>
                            <?php endif; ?>
                            <?= e($o['sadrzaj']) ?>
                        </div>
                        <?php if ($o['zahtev_id']): ?>
                        <div class="notif-body">
                            <a href="?page=analiza-detalji&id=<?= $o['zahtev_id'] ?>" style="color:var(--yellow);">
                                Pogledaj analizu #<?= $o['zahtev_id'] ?>
                                <?= $o['tip_analize'] ? ' (' . e(tipAnalizeLabel($o['tip_analize'])) . ')' : '' ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <div class="notif-time"><?= formatDatumVreme($o['datum_vreme']) ?></div>
                    </div>
                    <?php if (!$o['procitano']): ?>
                    <form method="POST" action="?page=obavestenja&action=procitaj" style="display:inline;">
                        <input type="hidden" name="obavestenje_id" value="<?= $o['id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" title="Označi kao pročitano">✓</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
        <div class="empty-state">Nemate obaveštenja</div>
        <?php endif; ?>
    </div>
</div>
<?php $stmt->close(); ?>
