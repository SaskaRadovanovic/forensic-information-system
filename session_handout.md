# ForenzIS PHP Konverzija — Session Handout

## Prompt za novu sesiju (copy-paste)

```
Pročitaj PROMPT.txt u root-u projekta za osnovna pravila.

Tvoj zadatak: implementiraj PHP konverziju ForenzIS projekta prateći implementacioni plan.

Pročitaj ove fajlove redom:
1. `docs/superpowers/specs/2026-06-10-php-konverzija-design.md` — spec (odluke, arhitektura)
2. `docs/superpowers/plans/2026-06-10-php-konverzija.md` — implementacioni plan (21 task)
3. `forensic-information-system/prisma/schema.prisma` — izvor istine za bazu podataka
4. `index.html` — izvor istine za CSS dizajn (kopiraj CSS 1:1 u layout/header.php)
5. `forenzika-php/session_handout.md` — ovaj fajl, dodatni kontekst

Sve piši u folder `forenzika-php/`. Prati plan task po task (Task 1 → Task 21).

PRAVILA:
- Komentari na srpskom, obilno
- Prepared statements za SVE SQL upite
- htmlspecialchars() za sav output u HTML
- SIMPLE FIRST — minimum koda koji rešava problem
- Kad završiš značajan deo (npr. ceo modul), ažuriraj ovaj session_handout.md sa progressom
- Ako potrošiš 70% konteksta, STANI i ažuriraj session_handout.md sa promptom za nastavak

Počni od Task 1 i idi redom.
```

## Kontekst projekta

- **Šta je ovo:** Forenzički informacioni sistem — upravljanje predmetima, dokazima, dokumentacijom, analizama
- **Originalni stack:** Next.js 16, React 19, TypeScript, Prisma, MySQL, Tailwind, shadcn/ui
- **Novi stack:** Čist PHP 8+, MySQLi, PHP sesije, bcrypt — BEZ framework-a
- **Baza:** MySQL, ista šema kao Prisma samo prevedena u SQL

## Ključne odluke

- Routing: `index.php?page=X&action=Y`
- Auth: `$_SESSION` + `password_verify()`
- Upload: SIMULIRAN (forma postoji, fajl se ne čuva)
- API: NEMA — samo UI stranice
- CSS: kopiran 1:1 iz index.html

## Test korisnici (lozinka: password123)

| Email | Uloga |
|-------|-------|
| admin@fis.rs | ADMINISTRATOR |
| istrazitelj@fis.rs | ISTRAZITELJ |
| tehnicar@fis.rs | TEHNICAR |
| vestak@fis.rs | VESTAK |

## Progress

- [x] Task 1: Folder struktura + config.php + helpers.php
- [x] Task 2: schema.sql
- [x] Task 3: seed.sql
- [x] Task 4: index.php (front controller)
- [x] Task 5: layout/header.php + footer.php
- [x] Task 6: Login + auth
- [x] Task 7: Dashboard
- [x] Task 8: napomene.txt
- [x] Task 9: Dokazi lista + akcije
- [x] Task 10: Dokazi novi/detalji/izmeni
- [x] Task 11: Dokazi zahtevi
- [x] Task 12: Dokumentacija lista + akcije
- [x] Task 13: Dokumentacija novi/detalji/izmeni
- [x] Task 14: Tagovi
- [x] Task 15: Predmeti lista + akcije
- [x] Task 16: Predmeti novi/detalji/izmeni
- [x] Task 17: Analize lista + akcije
- [x] Task 18: Analize novi/detalji/izmeni/dodela/rezultat
- [x] Task 19: Obaveštenja
- [x] Task 20: Izveštaji
- [x] Task 21: Završni pregled
