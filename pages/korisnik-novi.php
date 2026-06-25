<?php
/**
 * pages/korisnik-novi.php — Forma za dodavanje novog korisnika
 *
 * Polja: ime, prezime, email, lozinka, uloga. Pristup: samo ADMINISTRATOR.
 */
requireRole('ADMINISTRATOR');
?>

<div class="page-breadcrumb">
    <a href="?page=dashboard" class="btn btn-ghost btn-sm">&larr; Dashboard</a>
</div>

<div class="page-eyebrow">Administracija</div>
<div class="page-title">Novi korisnik</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=korisnik-novi">
            <div class="form-grid">
                <div class="form-group">
                    <label>Ime *</label>
                    <input type="text" name="ime" required placeholder="npr. Marko">
                </div>

                <div class="form-group">
                    <label>Prezime *</label>
                    <input type="text" name="prezime" required placeholder="npr. Petrović">
                </div>

                <div class="form-group full">
                    <label>Email adresa *</label>
                    <input type="email" name="email" required placeholder="npr. marko.petrovic@forenzis.rs">
                </div>

                <div class="form-group full">
                    <label>Lozinka *</label>
                    <input type="password" name="lozinka" required minlength="6" placeholder="Minimalno 6 karaktera">
                </div>

                <div class="form-group full">
                    <label>Uloga *</label>
                    <select name="uloga" required>
                        <option value="">— Izaberite ulogu —</option>
                        <option value="ADMINISTRATOR">Administrator</option>
                        <option value="ISTRAZITELJ">Istražitelj</option>
                        <option value="TEHNICAR">Tehničar</option>
                        <option value="VESTAK">Veštak</option>
                    </select>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Kreiraj korisnika</button>
                <a href="?page=dashboard" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>
