<?php
/**
 * pages/analiza-nova.php — Forma za kreiranje novog zahteva za analizu
 *
 * Polja: predmet, dokaz, tip analize, opis, rok, prag upozorenja.
 * Pristup: samo ISTRAZITELJ.
 */
requireRole('ISTRAZITELJ');

// Predmeti za select
$predmetiRes = $conn->query("SELECT id, naziv FROM predmet WHERE status = 'AKTIVAN' ORDER BY naziv");
// Dokazi za select (svi aktivni)
$dokaziRes = $conn->query("SELECT d.id, d.sifra_dokaza, d.naziv, p.naziv AS predmet_naziv FROM dokaz d JOIN predmet p ON p.id = d.predmet_id WHERE d.status != 'ARHIVIRANO' ORDER BY d.sifra_dokaza");
?>

<div class="page-breadcrumb">
    <a href="?page=analize" class="btn btn-ghost btn-sm">&larr; Analize</a>
</div>

<div class="page-eyebrow">Nova analiza</div>
<div class="page-title">Zahtev za analizu</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=analiza-nova">
            <div class="form-grid">
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
                    <label>Dokaz *</label>
                    <select name="dokaz_id" required>
                        <option value="">— Izaberi dokaz —</option>
                        <?php while ($d = $dokaziRes->fetch_assoc()): ?>
                        <option value="<?= $d['id'] ?>"><?= e($d['sifra_dokaza'] . ' — ' . $d['naziv'] . ' (' . $d['predmet_naziv'] . ')') ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tip analize *</label>
                    <select name="tip_analize" required>
                        <option value="">— Izaberi tip —</option>
                        <?php foreach (['BALISTICKA','DNK','DIGITALNA','HEMIJSKA','TOKSIKOLOSKA','DOKUMENTOLOSKA','DRUGA'] as $t): ?>
                        <option value="<?= $t ?>"><?= tipAnalizeLabel($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Rok</label>
                    <input type="date" name="rok">
                </div>

                <div class="form-group">
                    <label>Prag upozorenja (dana)</label>
                    <input type="number" name="prag_upozorenja_dana" value="3" min="1" max="30">
                </div>

                <div class="form-group full">
                    <label>Opis</label>
                    <textarea name="opis" rows="4" placeholder="Opišite šta je potrebno analizirati..."></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Kreiraj zahtev</button>
                <a href="?page=analize" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
