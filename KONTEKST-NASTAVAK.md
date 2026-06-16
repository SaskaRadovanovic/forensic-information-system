# Kontekst za nastavak implementacije — Podsistem za upravljanje forenzickom dokumentacijom

## Projekat
- **Ime:** ForenzIS — Sistem za upravljanje forenzickim dokazima i istragama
- **Stack:** PHP 8+, MySQLi, server-rendered stranice, BEZ frameworka
- **Grana:** `sprint2`
- **Putanja:** `C:\Users\Filip\Documents\GitHub\forensic-information-system`
- **Specifikacija:** `TIM10.md` (funkcionalni zahtevi) i `GRUPA10.md` (definicija problema i ciljeva)
- **Plan:** `PLAN-DOKUMENTACIJA.md` (detaljan plan svih faza)

## Baza podataka
- MySQL 8.0, charset utf8mb4
- Kredencijali: root / ftn, baza: forenzis
- VAZNO: import sa `--default-character-set=utf8mb4` inace se pokvare srpski karakteri
- Test korisnici (lozinka: `password123`): admin@fis.rs, istrazitelj@fis.rs, tehnicar@fis.rs, vestak@fis.rs

## Struktura projekta (kljucni fajlovi)
```
config.php              — sesija, DB konekcija, upload konstante, auth helperi
helpers.php             — e(), flash poruke, badge klase/labele, formatiranje datuma
index.php               — front controller, routing (?page=X&action=Y), whitelist stranica
schema.sql              — sema baze (sve tabele)
seed.sql                — test podaci

actions/
  dokumenti-actions.php — POST obrada za dokumenta (kreiranje, izmena, arhiviranje, pristup, download, tagovi)
  analize-actions.php   — POST obrada za analize (kreiranje, dodela vestaka, rezultati...)

pages/
  dokumentacija.php     — lista dokumenata sa filterima i pretragom
  dokument-novi.php     — forma za kreiranje dokumenta
  dokument-izmeni.php   — forma za izmenu dokumenta
  dokument-detalji.php  — detalji dokumenta (info, fajl preview, pristup, tagovi, istorija)

layout/
  header.php            — HTML head, CSS, topbar, sidebar
  footer.php            — zatvaranje HTML-a

uploads/                — folder za uploadovane fajlove (.gitkeep)
```

## Konvencije koda
- **Prepared statements** za SVE SQL upite — bez izuzetka
- **`e()`** (htmlspecialchars wrapper) za sav output u HTML
- **Komentari na srpskom**
- CSS klase vec definisane u `layout/header.php`: `card`, `btn`, `badge`, `form-group`, `info-grid`, `filter-bar`, `action-bar`, `empty-state`, `alert`, itd.
- Flash poruke: `flashSuccess()`, `flashError()`
- Routing: `index.php?page=X&action=Y`, nove stranice dodati u `$dozvoljeneStranice` i `$actionMapa`
- Upload fajlovi: `uploads/` folder, ime formata `DOK-{predmet_id}-{timestamp}-{random}.{ext}`

## Sta je zavrseno

### Faza 0: Upload fajlova i PDF preview — KOMPLETNA
- Task 0.1: Upload konfiguracija (UPLOAD_DIR, dozvoljeni tipovi, max velicina, .gitignore)
- Task 0.2: Upload pri kreiranju dokumenta (validacija, unikatan naziv, move_uploaded_file)
- Task 0.3: Upload pri izmeni dokumenta (opcionalan novi fajl, stara putanja u arhivi)
- Task 0.4: PDF preview na detalji stranici (embed za PDF, img za slike, poruka ako nema)
- Task 0.5: Download akcija (provera pristupa, MIME tipovi, Content-Disposition)
- Task 0.6: Serving fajlova (relativne putanje, PHP dev server)

### Faza 1: Deljenje pristupa dokumentima — KOMPLETNA
- Task 1.1: Nivo poverljivosti (JAVNO/INTERNO/POVERLJIVO/STROGO_POVERLJIVO kolona, select u formama, badge na listi i detaljima, helper funkcije)
- Task 1.2: UI za upravljanje pravima pristupa (tabela prava, forma za dodelu, uklanjanje, samo autor/admin upravlja)
- Task 1.3: Filtriranje po pravima pristupa (dokumentacija.php SQL filtrira po pravima, 403 na detalji stranici i download-u)
- Task 1.4: Auto-dodela pristupa vestaku pri dodeli analize (INSERT IGNORE u pravo_pristupa, obavestenje)
- Task 1.5: Log deljenja pristupa (tabela log_pristupa, logovanje dodele/uklanjanja, istorija na detalji stranici)
- BUGFIX: Provera nivoa pristupa CITANJE vs IZMENA na stranici za izmenu (i page i action handler)

### Faza 2: Napredna pretraga — KOMPLETNA
- Task 2.1: Pretraga po kljucnim recima (naziv + opis/metapodaci, LIKE sa prepared statements)
- Task 2.2: Filter po tagu (dropdown, EXISTS upit na dokument_tag)
- Task 2.3: Filter po autoru i datumskom opsegu (dropdown autora, date inputi od/do)
- Task 2.4: Kombinovana pretraga (filter po poverljivosti, dugme Pretrazi, aktivni filteri kao zuti badge-ovi sa X, ponisti sve)
- UI FIX: Dugme "+ Novi dokument" premesteno u header red pored naslova

## Sta je ostalo za implementaciju

### Faza 3: Poluautomatsko obelezavanje dokumenata
- Task 3.1: Predlaganje tagova na osnovu tipa dokumenta i opisa (mapiranje tip→tag, JS prikaz predloga, dugme "Dodaj")
- Task 3.2: Pretraga opisa za kljucne reci i predlaganje tagova (mapiranje kljucna_rec→tag, checkbox-ovi za prihvatanje)

### Faza 4: Izvestaj o stanju dokumentacije po predmetu
- Task 4.1: Nova stranica izvestaj-dokumentacija.php (sumirani + detaljan pregled, stampa/print CSS)
- Task 4.2: Dodavanje linka u navigaciju (sidebar, $dozvoljeneStranice, za ADMINISTRATOR i ISTRAZITELJ)

## Vazne napomene
- Kolona `nivo_poverljivosti` dodata u tabelu `dokument` (schema.sql) — ENUM sa default INTERNO
- Tabela `log_pristupa` dodata u schema.sql
- Tabela `pravo_pristupa` vec postojala u semi, sada se koristi u UI i logici
- Pri reimportu baze uvek: `schema.sql` pa `seed.sql` sa `--default-character-set=utf8mb4`
- Vestak vidi dokumente SAMO kroz eksplicitnu dodelu pristupa ili auto-dodelu pri dodeli analize (po specifikaciji)
