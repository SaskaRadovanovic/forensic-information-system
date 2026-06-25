<?php
/**
 * pages/dokaz-novi.php — Forma za kreiranje novog dokaza
 *
 * Dinamička polja po tipu dokaza (JavaScript prikazuje/sakriva sekcije).
 */
requireRole('TEHNICAR', 'ADMINISTRATOR');

// Samo predmeti u fazi prikupljanja dokaza su dostupni za unos
$predmetiRes = $conn->query("SELECT id, naziv FROM predmet WHERE status = 'AKTIVAN' AND faza = 'PRIKUPLJANJE_DOKAZA' ORDER BY naziv");
?>

<div class="page-breadcrumb">
    <a href="?page=dokazi" class="btn btn-ghost btn-sm">&larr; Dokazi</a>
</div>

<div class="page-eyebrow">Novi dokaz</div>
<div class="page-title">Evidentiraj dokaz</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="?page=dokaz-novi">
            <div class="form-grid">
                <!-- Opšti podaci -->
                <div class="form-section-title">Opšti podaci</div>

                <div class="form-group">
                    <label>Naziv *</label>
                    <input type="text" name="naziv" required>
                </div>

                <div class="form-group">
                    <label>Tip dokaza *</label>
                    <select name="tip_dokaza" id="tip-dokaza-select" required onchange="showEvidenceFields(this.value)">
                        <option value="">Izaberite tip</option>
                        <option value="BIOLOSKI_TRAG">Biološki trag</option>
                        <option value="ORUZJE">Oružje</option>
                        <option value="DOKUMENT">Dokument</option>
                        <option value="ODECA">Odeća</option>
                        <option value="UZORAK">Uzorak</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Predmet *</label>
                    <select name="predmet_id" required>
                        <option value="">Izaberite predmet</option>
                        <?php while ($p = $predmetiRes->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['naziv']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Datum prijema *</label>
                    <input type="datetime-local" name="datum_prijema" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>

                <div class="form-group">
                    <label>Datum pronalaska</label>
                    <input type="datetime-local" name="datum_pronalaska">
                </div>

                <div class="form-group">
                    <label>Lokacija pronalaska</label>
                    <input type="text" name="lokacija_pronalaska">
                </div>

                <div class="form-group">
                    <label>Lokacija skladištenja</label>
                    <input type="text" name="lokacija_skladistenja">
                </div>

                <div class="form-group full">
                    <label>Opis</label>
                    <textarea name="opis" rows="3"></textarea>
                </div>

                <!-- Dinamička polja po tipu — skrivena po defaultu -->

                <!-- BIOLOŠKI TRAG -->
                <div class="form-section-title isa-fields" id="fields-BIOLOSKI_TRAG" style="display:none;">Biološki trag — specifična obeležja</div>
                <div class="form-group isa-fields" data-tip="BIOLOSKI_TRAG" style="display:none;">
                    <label>Vrsta traga</label>
                    <input type="text" name="vrsta_traga">
                </div>
                <div class="form-group isa-fields" data-tip="BIOLOSKI_TRAG" style="display:none;">
                    <label>Način uzorkovanja</label>
                    <input type="text" name="nacin_uzorkovanja">
                </div>
                <div class="form-group isa-fields" data-tip="BIOLOSKI_TRAG" style="display:none;">
                    <label>Uslovi čuvanja</label>
                    <input type="text" name="uslovi_cuvanja">
                </div>
                <div class="form-group isa-fields" data-tip="BIOLOSKI_TRAG" style="display:none;">
                    <label>Količina</label>
                    <input type="text" name="kolicina">
                </div>

                <!-- ORUŽJE -->
                <div class="form-section-title isa-fields" id="fields-ORUZJE" style="display:none;">Oružje — specifična obeležja</div>
                <div class="form-group isa-fields" data-tip="ORUZJE" style="display:none;">
                    <label>Vrsta oružja</label>
                    <input type="text" name="vrsta_oruzja">
                </div>
                <div class="form-group isa-fields" data-tip="ORUZJE" style="display:none;">
                    <label>Marka</label>
                    <input type="text" name="marka">
                </div>
                <div class="form-group isa-fields" data-tip="ORUZJE" style="display:none;">
                    <label>Model</label>
                    <input type="text" name="model_oruzja">
                </div>
                <div class="form-group isa-fields" data-tip="ORUZJE" style="display:none;">
                    <label>Kalibar</label>
                    <input type="text" name="kalibar">
                </div>
                <div class="form-group isa-fields" data-tip="ORUZJE" style="display:none;">
                    <label>Serijski broj</label>
                    <input type="text" name="serijski_br">
                </div>

                <!-- DOKUMENT -->
                <div class="form-section-title isa-fields" id="fields-DOKUMENT" style="display:none;">Dokument — specifična obeležja</div>
                <div class="form-group isa-fields" data-tip="DOKUMENT" style="display:none;">
                    <label>Vrsta dokumenta</label>
                    <input type="text" name="vrsta_dokumenta">
                </div>
                <div class="form-group isa-fields" data-tip="DOKUMENT" style="display:none;">
                    <label>Jezik</label>
                    <input type="text" name="jezik">
                </div>
                <div class="form-group isa-fields" data-tip="DOKUMENT" style="display:none;">
                    <label>Broj stranica</label>
                    <input type="number" name="broj_stranica" min="0">
                </div>

                <!-- ODEĆA -->
                <div class="form-section-title isa-fields" id="fields-ODECA" style="display:none;">Odeća — specifična obeležja</div>
                <div class="form-group isa-fields" data-tip="ODECA" style="display:none;">
                    <label>Veličina</label>
                    <input type="text" name="velicina">
                </div>
                <div class="form-group isa-fields" data-tip="ODECA" style="display:none;">
                    <label>Vrsta odevnog predmeta</label>
                    <input type="text" name="vrsta_odevnog_predmeta">
                </div>
                <div class="form-group isa-fields" data-tip="ODECA" style="display:none;">
                    <label>Boja</label>
                    <input type="text" name="boja">
                </div>
                <div class="form-group isa-fields" data-tip="ODECA" style="display:none;">
                    <label>Stanje</label>
                    <input type="text" name="stanje">
                </div>

                <!-- UZORAK -->
                <div class="form-section-title isa-fields" id="fields-UZORAK" style="display:none;">Uzorak — specifična obeležja</div>
                <div class="form-group isa-fields" data-tip="UZORAK" style="display:none;">
                    <label>Vrsta uzorka</label>
                    <input type="text" name="vrsta_uzorka">
                </div>
                <div class="form-group isa-fields" data-tip="UZORAK" style="display:none;">
                    <label>Količina</label>
                    <input type="text" name="kolicina_uzorka">
                </div>
                <div class="form-group isa-fields" data-tip="UZORAK" style="display:none;">
                    <label>Jedinica mere</label>
                    <input type="text" name="jedinica_mere">
                </div>
                <div class="form-group isa-fields" data-tip="UZORAK" style="display:none;">
                    <label>Način uzorkovanja</label>
                    <input type="text" name="nacin_uzorkovanja">
                </div>
                <div class="form-group isa-fields" data-tip="UZORAK" style="display:none;">
                    <label>Uslovi čuvanja</label>
                    <input type="text" name="uslovi_cuvanja">
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn btn-primary">Sačuvaj dokaz</button>
                <a href="?page=dokazi" class="btn btn-ghost">Otkaži</a>
            </div>
        </form>
    </div>
</div>

<script>
// Prikazuje/sakriva polja za specifični tip dokaza
function showEvidenceFields(tip) {
    // Sakrij sve ISA sekcije
    document.querySelectorAll('.isa-fields').forEach(function(el) {
        el.style.display = 'none';
    });
    // Prikaži sekciju za izabrani tip
    if (tip) {
        document.querySelectorAll('.isa-fields[data-tip="' + tip + '"], #fields-' + tip).forEach(function(el) {
            el.style.display = '';
        });
    }
}
</script>
