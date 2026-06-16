# Plan implementacije — Podsistem za upravljanje forenzickom dokumentacijom

## Kontekst

- **Projekat:** ForenzIS — Sistem za upravljanje forenzickim dokazima i istragama
- **Stack:** PHP 8+, MySQLi, server-rendered stranice, BEZ frameworka
- **Grana:** `sprint2`
- **Putanja:** `C:\Users\Filip\Documents\GitHub\forensic-information-system`
- **Specifikacija:** Pogledaj fajl `TIM10.md` — sekcija "Zahtevi podsistema za upravljanje forenzickom dokumentacijom"

## Trenutno stanje (sta je vec implementirano)

### Gotovo
- Dokument vezan za predmet (FK `predmet_id`) — ne moze postojati bez predmeta
- Metapodaci: naziv, datum, autor, tip dokumenta, opis (tabela `metapodatak`)
- Istorija verzija (tabela `dokument_arhiva`) — svaka izmena cuva staru verziju
- CRUD: kreiranje (`dokument-novi.php`), izmena (`dokument-izmeni.php`), arhiviranje
- Filtriranje po predmetu, tipu dokumenta, statusu
- Sortiranje po kolonama (naziv, datum, verzija)
- Tagovi: admin kreira predefinisane tagove, korisnici ih rucno dodaju/uklanjaju na dokumentu
- Tagovi se prikazuju u listi i na detaljima dokumenta

### Delimicno implementirano
- Filtriranje postoji ali **ne pokriva** sve zahtevane kriterijume (nedostaje: autor, datum, tag, kljucne reci)
- DB tabela `pravo_pristupa` postoji u semi ali se **nigde ne koristi** u UI niti u logici pristupa

### Nedostaje kompletno
- Upload fajlova (trenutno simuliran — `putanja = 'simulirano'`)
- PDF preview na stranici detalja dokumenta
- Ceo modul deljenja pristupa dokumentima
- Nivoi poverljivosti (javno/interno/poverljivo/strogo poverljivo)
- Auto-dodela pristupa vestaku pri prihvatanju analize
- Napredna pretraga po sadrzaju i kombinovanim kriterijumima
- Poluautomatsko predlaganje tagova
- Izvestaj o stanju dokumentacije po predmetu

---

## FAZA 0: Upload fajlova i PDF preview (OSNOVA)

Upload je trenutno simuliran — forma ima `<input type="file" disabled>` i u bazu se cuva `putanja = 'simulirano'`. Ova faza to popravlja.

### Task 0.1 — Kreiranje uploads foldera i konfiguracija

**Fajlovi:** `config.php`

- Dodaj konstantu `UPLOAD_DIR` koja pokazuje na `uploads/` folder u root-u projekta
- Kreiraj folder `uploads/` sa `.gitkeep` fajlom
- Dodaj `uploads/` u `.gitignore` (sem `.gitkeep`)
- Definisi dozvoljene tipove fajlova: `pdf, doc, docx, jpg, jpeg, png`
- Definisi max velicinu fajla: `10MB`

### Task 0.2 — Implementacija upload-a pri kreiranju dokumenta

**Fajlovi:** `pages/dokument-novi.php`, `actions/dokumenti-actions.php`

- Na `dokument-novi.php`: omoguci file input (ukloni `disabled`), dodaj `enctype="multipart/form-data"` na formu
- Na `dokumenti-actions.php` (sekcija `dokument-novi`):
  - Validacija: tip fajla, velicina, da li je upload uspeo (`$_FILES['fajl']['error']`)
  - Generisanje unikatnog naziva fajla: `DOK-{predmet_id}-{timestamp}-{random}.{ext}`
  - Premestanje fajla iz temp u `uploads/` folder pomocu `move_uploaded_file()`
  - Cuvanje putanje u kolonu `dokument.putanja` umesto `'simulirano'`
  - Ako upload nije poslan (opciono polje), sacuvaj `putanja = 'nema-fajla'`

### Task 0.3 — Upload nove verzije fajla pri izmeni dokumenta

**Fajlovi:** `pages/dokument-izmeni.php`, `actions/dokumenti-actions.php`

- Na `dokument-izmeni.php`: dodaj file input za novu verziju fajla (opciono), dodaj `enctype="multipart/form-data"`
- Na `dokumenti-actions.php` (sekcija `dokument-izmeni`):
  - Ako je novi fajl poslan: validacija + upload + azuriraj `putanja`
  - Stara putanja se vec cuva u `dokument_arhiva` (to vec radi)
  - Ako fajl nije poslan: zadrzi postojecu putanju

### Task 0.4 — PDF preview na stranici detalja

**Fajlovi:** `pages/dokument-detalji.php`

- Proveri da li fajl postoji na disku i da li je PDF (`pathinfo() + file_exists()`)
- Ako je PDF: prikazi `<iframe>` ili `<embed>` sa putanjom do fajla za inline preview
  ```html
  <embed src="uploads/DOK-1-xxxx.pdf" type="application/pdf" width="100%" height="600px" />
  ```
- Ako je slika (jpg/png): prikazi `<img>` tag
- Ako fajl ne postoji ili je `'simulirano'`/`'nema-fajla'`: prikazi poruku "Fajl nije uploadovan"
- Dodaj dugme "Preuzmi fajl" koje vodi na `?page=dokument-detalji&id=X&action=download`

### Task 0.5 — Download akcija

**Fajlovi:** `actions/dokumenti-actions.php`

- Novi action `download` na `dokument-detalji` stranici
- Provera da fajl postoji na disku
- Provera prava pristupa (da li korisnik sme da vidi dokument)
- Slanje fajla sa ispravnim headerima:
  ```php
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="naziv-dokumenta.pdf"');
  readfile($putanja);
  ```

### Task 0.6 — Serving uploadovanih fajlova (PHP dev server)

**Fajlovi:** koreni folder

- PHP built-in server (`php -S`) automatski servira staticke fajlove iz root-a
- Za embed/iframe preview, fajlovi iz `uploads/` moraju biti dostupni preko HTTP-a
- Proveri da putanja u `<embed src="...">` bude relativna: `uploads/ime-fajla.pdf`
- VAZNO: ne servirati fajlove direktno bez provere pristupa u produkciji (za dev je ok)

---

## FAZA 1: Deljenje pristupa dokumentima

Specifikacija zahteva kontrolisano deljenje dokumenata sa nivoima poverljivosti. DB tabela `pravo_pristupa` vec postoji ali se ne koristi.

### Task 1.1 — Nivo poverljivosti na dokumentu

**Fajlovi:** `schema.sql`, `pages/dokument-novi.php`, `pages/dokument-izmeni.php`, `pages/dokument-detalji.php`, `pages/dokumentacija.php`, `helpers.php`

- Dodaj kolonu u tabelu `dokument`:
  ```sql
  ALTER TABLE dokument ADD COLUMN nivo_poverljivosti
    ENUM('JAVNO','INTERNO','POVERLJIVO','STROGO_POVERLJIVO')
    NOT NULL DEFAULT 'INTERNO'
    AFTER status;
  ```
- Dodaj select polje za nivo poverljivosti u formu za kreiranje i izmenu
- Prikazi nivo poverljivosti u info gridu na detalji stranici
- Prikazi kao badge u tabeli na listi dokumenata
- Dodaj helper funkcije `nivoPoverljivostiLabel()` i `nivoPoverljivostiBadge()` u `helpers.php`

### Task 1.2 — UI za upravljanje pravima pristupa na dokumentu

**Fajlovi:** `pages/dokument-detalji.php`, `actions/dokumenti-actions.php`

- Na `dokument-detalji.php` dodaj novu sekciju/card "Prava pristupa":
  - Tabela sa kolonama: Korisnik | Uloga | Nivo pristupa | Datum dodele | Akcija
  - Lista svih korisnika koji imaju zapis u `pravo_pristupa` za ovaj dokument
  - Dugme "Ukloni" pored svakog korisnika (POST forma)
- Forma za dodavanje novog pristupa:
  - Select za korisnika (iz tabele `korisnik`, izuzmi one koji vec imaju pristup)
  - Select za nivo pristupa (citanje, izmena)
  - Submit dugme
- Samo autor dokumenta i administrator mogu upravljati pristupom
- Action handleri u `dokumenti-actions.php`:
  - `action=dodaj-pristup`: INSERT u `pravo_pristupa`
  - `action=ukloni-pristup`: DELETE iz `pravo_pristupa`

### Task 1.3 — Filtriranje sadrzaja po pravima pristupa

**Fajlovi:** `pages/dokumentacija.php`, `pages/dokument-detalji.php`

- Na `dokumentacija.php` izmeni SQL upit — korisnik vidi dokument AKO:
  - Je administrator (vidi sve), ILI
  - Je autor dokumenta (`autor_id = session user_id`), ILI
  - Ima zapis u `pravo_pristupa` za taj dokument, ILI
  - Dokument ima nivo `JAVNO`
- Na `dokument-detalji.php` dodaj proveru pristupa pre prikazivanja:
  - Ako korisnik nema pristup → 403 stranica
- Isto vazi za download akciju

### Task 1.4 — Automatska dodela pristupa vestaku pri prihvatanju analize

**Fajlovi:** `actions/analize-actions.php`

- Kada se vestak dodeli analizi (akcija dodele u analize-actions):
  - Pronadji sve dokumente predmeta za koji je analiza
  - Za svaki dokument insertuj zapis u `pravo_pristupa` (nivo: 'CITANJE')
  - Koristi `INSERT IGNORE` da ne dupliras ako pristup vec postoji
  - Kreiraj obavestenje vestaku: "Dodeljen vam je pristup dokumentima predmeta X"

### Task 1.5 — Evidencija/log deljenja pristupa

**Fajlovi:** `schema.sql`, `actions/dokumenti-actions.php`

- Kreiraj novu tabelu:
  ```sql
  CREATE TABLE log_pristupa (
      id              INT AUTO_INCREMENT PRIMARY KEY,
      akcija          ENUM('DODELA','UKLANJANJE') NOT NULL,
      datum_vreme     DATETIME NOT NULL DEFAULT NOW(),
      dokument_id     INT NOT NULL,
      korisnik_id     INT NOT NULL,   -- kome je dat/oduzet pristup
      izvrsio_id      INT NOT NULL,   -- ko je izvrsio akciju
      napomena        TEXT NULL,
      FOREIGN KEY (dokument_id) REFERENCES dokument(id) ON DELETE CASCADE,
      FOREIGN KEY (korisnik_id) REFERENCES korisnik(id),
      FOREIGN KEY (izvrsio_id) REFERENCES korisnik(id)
  );
  ```
- Pri svakom dodavanju/uklanjanju pristupa loguj zapis
- Na `dokument-detalji.php` prikazi istoriju deljenja (timeline)

---

## FAZA 2: Napredna pretraga po metapodacima i sadrzaju

### Task 2.1 — Pretraga po kljucnim recima (naziv + opis)

**Fajlovi:** `pages/dokumentacija.php`

- Dodaj text input polje "Pretraga" u filter bar
- SQL uslov:
  ```sql
  AND (dok.naziv LIKE CONCAT('%', ?, '%')
    OR EXISTS (SELECT 1 FROM metapodatak m
               WHERE m.dokument_id = dok.id
               AND m.vrednost LIKE CONCAT('%', ?, '%')))
  ```
- Koristi prepared statements za LIKE parametre

### Task 2.2 — Filter po tagu

**Fajlovi:** `pages/dokumentacija.php`

- Dodaj multi-select ili dropdown za tagove u filter bar
- Ucitaj sve tagove iz baze za opcije
- SQL: JOIN ili EXISTS sa `dokument_tag` tabelom
- Podrzi filtriranje po jednom ili vise tagova

### Task 2.3 — Filter po autoru i datumskom opsegu

**Fajlovi:** `pages/dokumentacija.php`

- Dodaj select za autora (lista korisnika koji su autori bar jednog dokumenta)
- Dodaj dva date inputa: "Od datuma" i "Do datuma"
- SQL uslovi:
  ```sql
  AND dok.autor_id = ?
  AND dok.datum_kreiranja >= ?
  AND dok.datum_kreiranja <= ?
  ```

### Task 2.4 — Kombinovana pretraga

**Fajlovi:** `pages/dokumentacija.php`

- Svi filteri (tekst + tag + predmet + tip + status + autor + datum + nivo poverljivosti) rade zajedno
- Dinamicko gradenje SQL upita sa pripremljenim parametrima
- Prikaz aktivnih filtera iznad tabele (badge-ovi sa X dugmetom za uklanjanje)

---

## FAZA 3: Poluautomatsko obelezavanje dokumenata

### Task 3.1 — Predlaganje tagova na osnovu tipa dokumenta i opisa

**Fajlovi:** `pages/dokument-novi.php`, `pages/dokument-detalji.php`, moguce novi fajl `helpers-tagovi.php`

- Definisi mapiranje tip_dokumenta → predlozeni tagovi:
  - "Vestacenje" → predlozi tag "Vestacenje" ako postoji
  - "Fotografija" → predlozi tag "Foto-dokaz"
  - itd.
- Na formi za kreiranje: nakon izbora tipa, JavaScript prikazuje predlozene tagove
- Korisnik mora da klikne "Dodaj" da potvrdi — tagovi se NE dodaju automatski bez odobrenja
- Na detalji stranici: sekcija "Predlozeni tagovi" sa dugmicima za brzo dodavanje

### Task 3.2 — Pretraga opisa za kljucne reci i predlaganje tagova

**Fajlovi:** isti kao Task 3.1

- Definisi mapiranje kljucna_rec → tag:
  - "pistolj" / "oruzje" / "kalibar" → tag "Vatreno oruzje"
  - "krv" / "DNK" / "uzorak" → tag "Bioloski trag"
  - "hitno" / "urgent" → tag "HITNO"
- Pri kreiranju dokumenta, skeniraj opis za kljucne reci
- Prikazi predlozene tagove sa checkbox-ovima — korisnik bira koje da prihvati
- Ovo je "poluautomatsko" tagovanje — sistem predlaze, korisnik odobrava

---

## FAZA 4: Izvestaj o stanju dokumentacije po predmetu

### Task 4.1 — Stranica izvestaja o dokumentaciji

**Fajlovi:** novi fajl `pages/izvestaj-dokumentacija.php`, izmena `index.php` (dodaj u whitelist), izmena `layout/header.php` (dodaj u sidebar)

- Nova stranica dostupna istrazitelju i administratoru
- Select za izbor predmeta (ili prikazuje sve)
- Sumirani pregled:
  - Ukupno dokumenata u predmetu
  - Po statusu (aktivni vs arhivirani)
  - Po tipu dokumenta (koliko izvestaja, fotografija, zapisnika...)
  - Po nivou poverljivosti
  - Poslednji dodat/izmenjen dokument
  - Ukupno verzija (suma svih izmena)
  - Broj korisnika sa pristupom
- Detaljan pregled:
  - Tabela svih dokumenata predmeta sa: naziv, tip, autor, verzija, tagovi, nivo poverljivosti, datum
- Dugme za stampu / export (CSS `@media print`)

### Task 4.2 — Dodavanje linka u navigaciju

**Fajlovi:** `layout/header.php`, `index.php`

- Dodaj `'izvestaj-dokumentacija'` u `$dozvoljeneStranice` niz u `index.php`
- Dodaj link u sidebar za ADMINISTRATOR i ISTRAZITELJ uloge

---

## Redosled implementacije (preporuka)

| Red | Task | Faza | Procena |
|-----|------|------|---------|
| 1 | Task 0.1 — Upload konfiguracija | Faza 0 | 15 min |
| 2 | Task 0.2 — Upload pri kreiranju | Faza 0 | 30 min |
| 3 | Task 0.3 — Upload pri izmeni | Faza 0 | 20 min |
| 4 | Task 0.4 — PDF preview | Faza 0 | 30 min |
| 5 | Task 0.5 — Download akcija | Faza 0 | 15 min |
| 6 | Task 0.6 — Serving fajlova | Faza 0 | 10 min |
| 7 | Task 1.1 — Nivo poverljivosti | Faza 1 | 30 min |
| 8 | Task 1.2 — UI prava pristupa | Faza 1 | 45 min |
| 9 | Task 1.3 — Filtriranje po pravima | Faza 1 | 30 min |
| 10 | Task 1.4 — Auto-dodela vestaku | Faza 1 | 20 min |
| 11 | Task 1.5 — Log deljenja | Faza 1 | 25 min |
| 12 | Task 2.1 — Text pretraga | Faza 2 | 20 min |
| 13 | Task 2.2 — Filter po tagu | Faza 2 | 20 min |
| 14 | Task 2.3 — Filter autor + datum | Faza 2 | 20 min |
| 15 | Task 2.4 — Kombinovana pretraga | Faza 2 | 25 min |
| 16 | Task 3.1 — Predlaganje tagova | Faza 3 | 30 min |
| 17 | Task 3.2 — Keyword-based tagovi | Faza 3 | 25 min |
| 18 | Task 4.1 — Izvestaj stranica | Faza 4 | 40 min |
| 19 | Task 4.2 — Navigacija link | Faza 4 | 5 min |

**Ukupna procena: ~7-8 sati rada**

---

## Vazne napomene za implementaciju

1. **Prepared statements** za SVE SQL upite — bez izuzetka
2. **`htmlspecialchars()`** (helper `e()`) za sav output u HTML
3. **Komentari na srpskom**, obilno
4. **CSS** je vec definisan u `layout/header.php` — koristi postojece klase (`card`, `btn`, `badge`, `form-group`, `info-grid`, `filter-bar`, itd.)
5. **Flash poruke** koristiti za feedback (`flashSuccess()`, `flashError()`)
6. **Routing** ide kroz `index.php?page=X&action=Y` — nove stranice dodati u `$dozvoljeneStranice` i `$actionMapa`
7. **Upload folder** — `uploads/` u root-u projekta, ne commitovati uploadovane fajlove
8. **Test korisnici** (lozinka: `password`): admin@fis.rs, istrazitelj@fis.rs, tehnicar@fis.rs, vestak@fis.rs
