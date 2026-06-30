<?php
/**
 * pages/tagovi.php — Lista tagova
 *
 * Prikazuje sve tagove sa badge-ovima u boji.
 * Administrator može brisati tagove i dodavati nove.
 */

// Učitaj sve tagove
$tagovi = $conn->query("SELECT t.id, t.naziv, t.boja, COUNT(dt.dokument_id) as br_dokumenata FROM tag t LEFT JOIN dokument_tag dt ON dt.tag_id = t.id GROUP BY t.id ORDER BY t.naziv");
?>

<div class="page-eyebrow">Sistem</div>
<div class="page-title">Tagovi</div>

<?php if ($_SESSION['uloga'] === 'ADMINISTRATOR'): ?>
<div style="margin-bottom:16px;">
    <a href="?page=tag-novi" class="btn btn-primary">+ Novi tag</a>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if ($tagovi->num_rows > 0): ?>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <?php while ($tag = $tagovi->fetch_assoc()): ?>
            <div style="display:flex; align-items:center; gap:8px; padding:10px 16px; background:var(--surface-2); border:1px solid var(--border);">
                <span class="badge" style="background:<?= e($tag['boja']) ?>22; color:<?= e($tag['boja']) ?>; border-color:<?= e($tag['boja']) ?>44;"><?= e($tag['naziv']) ?></span>
                <span style="font-family:var(--mono); font-size:10px; color:var(--text-3);"><?= $tag['br_dokumenata'] ?> dok.</span>
                <?php if ($_SESSION['uloga'] === 'ADMINISTRATOR'): ?>
                <form method="POST" action="?page=tagovi&action=obrisi" style="display:inline;">
                    <input type="hidden" name="tag_id" value="<?= $tag['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Obrisati tag \'<?= e($tag['naziv']) ?>\'?')" style="padding:2px 6px; font-size:9px;">✕</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Nema kreiranih tagova</div>
        <?php endif; ?>
    </div>
</div>
