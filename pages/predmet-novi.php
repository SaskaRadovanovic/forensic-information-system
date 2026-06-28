<?php
/**
 * pages/predmet-novi.php — Forma za kreiranje novog predmeta
 *
 * Polja: naziv, opis. Pristup: ADMINISTRATOR, ISTRAZITELJ.
 */
requireRole('ADMINISTRATOR', 'ISTRAZITELJ');
?>

<div class="page-breadcrumb">
    <a href="?page=predmeti" class="btn btn-ghost btn-sm">&larr; Predmeti</a>
</div>

<div class="page-eyebrow">Novi predmet</div>
<div class="page-title">Kreiraj predmet</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=predmet-novi">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Naziv *</label>
                    <input type="text" name="naziv" required placeholder="npr. Razbojništvo – Beograd Centar">
                </div>

                <div class="form-group full">
                    <label>Opis</label>
                    <textarea name="opis" rows="4" placeholder="Opis predmeta (opciono)"></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Kreiraj predmet</button>
                <a href="?page=predmeti" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
