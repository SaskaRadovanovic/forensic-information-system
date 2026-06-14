<?php
/**
 * pages/dokument-novi.php — Forma za kreiranje novog dokumenta
 *
 * Polja: naziv, predmet, tip dokumenta, opis, fajl (simulirano).
 */

// Predmeti za select
$predmetiRes = $conn->query("SELECT id, naziv FROM predmet WHERE status = 'AKTIVAN' ORDER BY naziv");
?>

<div class="page-breadcrumb">
    <a href="?page=dokumentacija" class="btn btn-ghost btn-sm">&larr; Dokumentacija</a>
</div>

<div class="page-eyebrow">Novi dokument</div>
<div class="page-title">Kreiraj dokument</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=dokument-novi">
            <div class="form-grid">
                <div class="form-group">
                    <label>Naziv *</label>
                    <input type="text" name="naziv" required>
                </div>

                <div class="form-group">
                    <label>Predmet *</label>
                    <select name="predmet_id" required>
                        <option value="">— Izaberi predmet —</option>
                        <?php while ($p = $predmetiRes->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['naziv']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tip dokumenta</label>
                    <select name="tip_dokumenta">
                        <option value="">— Izaberi tip —</option>
                        <?php foreach (['Izveštaj','Fotografija','Zapisnik','Veštačenje','Zbirni izveštaj','Ostalo'] as $t): ?>
                        <option value="<?= $t ?>"><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fajl (simulirano)</label>
                    <input type="file" disabled style="opacity:0.5;" title="Upload je simuliran">
                </div>

                <div class="form-group full">
                    <label>Opis</label>
                    <textarea name="opis" rows="4"></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Kreiraj dokument</button>
                <a href="?page=dokumentacija" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
