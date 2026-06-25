<?php
/**
 * pages/tag-novi.php — Forma za kreiranje novog taga
 *
 * Polja: naziv, boja (color picker).
 * Pristup: samo ADMINISTRATOR.
 */
requireRole('ADMINISTRATOR');
?>

<div class="page-breadcrumb">
    <a href="?page=tagovi" class="btn btn-ghost btn-sm">&larr; Tagovi</a>
</div>

<div class="page-eyebrow">Novi tag</div>
<div class="page-title">Kreiraj tag</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=tag-novi">
            <div class="form-grid">
                <div class="form-group">
                    <label>Naziv *</label>
                    <input type="text" name="naziv" required placeholder="npr. Hitno, DNK, Balistika...">
                </div>

                <div class="form-group">
                    <label>Boja</label>
                    <input type="color" name="boja" value="#FACC15" style="height:38px; padding:4px;">
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Kreiraj tag</button>
                <a href="?page=tagovi" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
