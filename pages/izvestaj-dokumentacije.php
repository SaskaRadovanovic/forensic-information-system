<?php
$predmetiRes = $conn->query("SELECT id, naziv FROM predmet ORDER BY naziv");
?>

<div class="page-eyebrow">Izveštaji</div>
<div class="page-title">Izveštaj o stanju dokumentacije</div>

<div class="card">
    <div class="card-body">
        <form method="GET">
            <input type="hidden" name="action" value="izvestaj-dokumentacije-pdf">

            <div class="form-group">
                <label>Predmet</label>
                <select name="predmet_id">
                    <option value="0">Svi predmeti</option>
                    <?php while ($p = $predmetiRes->fetch_assoc()): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['naziv']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Generiši PDF izveštaj</button>
            </div>
        </form>
    </div>
</div>
