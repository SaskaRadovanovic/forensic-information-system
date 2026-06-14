-- ============================================================================
-- ForenzIS — Test podaci (seed)
--
-- Lozinka za sve korisnike: password123
-- Hash generisan sa: password_hash('password123', PASSWORD_BCRYPT)
-- ============================================================================

-- ─── Korisnici ──────────────────────────────────────────────────────────────
INSERT INTO korisnik (ime, prezime, email, kor_ime, lozinka_hash, uloga) VALUES
('Admin',    'Adminović',  'admin@fis.rs',       'admin',       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMINISTRATOR'),
('Marko',    'Petrović',   'istrazitelj@fis.rs', 'istrazitelj', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ISTRAZITELJ'),
('Jelena',   'Marinković', 'tehnicar@fis.rs',    'tehnicar',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'TEHNICAR'),
('Dr. Dejan','Nikolić',    'vestak@fis.rs',      'vestak',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'VESTAK');

-- ─── ISA profili ────────────────────────────────────────────────────────────

-- Tehničar za dokaze (korisnik id=3, Jelena)
INSERT INTO tehnicar_za_dokaze (id_korisnik, id_td, odeljenje) VALUES
(3, 'TD-001', 'Odeljenje za dokaze');

-- Istražitelj (korisnik id=2, Marko)
INSERT INTO istrazitelj (id_korisnik, broj_znacke, odeljenje) VALUES
(2, 'IZ-001', 'Kriminalistika');

-- Veštak (korisnik id=4, Dr. Dejan)
INSERT INTO vestak (id_korisnik, id_vestak, specijalnost, sertifikat_br) VALUES
(4, 'VS-001', 'DNK analiza', 'SRT-2024-001');

-- ─── Predmeti ───────────────────────────────────────────────────────────────
INSERT INTO predmet (naziv, opis, status, faza, datum_otvaranja) VALUES
('Razbojništvo – Beograd Centar', 'Razbojništvo u centru Beograda, ul. Knez Mihailova', 'AKTIVAN', 'PRIKUPLJANJE_DOKAZA', '2026-01-15 09:00:00'),
('Ubistvo – Novi Sad',            'Ubistvo u Novom Sadu, naselje Liman',               'AKTIVAN', 'ANALIZA_DOKAZA',      '2026-02-10 14:30:00'),
('Prevara – Niš',                 'Finansijska prevara u Nišu',                          'AKTIVAN', 'OTVOREN_SLUCAJ',      '2026-03-20 11:00:00');

-- ─── Dokazi ─────────────────────────────────────────────────────────────────
INSERT INTO dokaz (sifra_dokaza, naziv, opis, tip_dokaza, datum_prijema, datum_pronalaska, lokacija_pronalaska, lokacija_skladistenja, status, predmet_id, tehnicar_id) VALUES
('DOK-2026-001', 'Krv na mestu zločina',    'Uzorak krvi pronađen na ulazu',        'BIOLOSKI_TRAG', '2026-01-16 10:00:00', '2026-01-15 22:30:00', 'Knez Mihailova 24',   'Sef A-12',    'U_SKLADISTU',       1, 3),
('DOK-2026-002', 'Pištolj CZ 99',           'Poluautomatski pištolj, 9mm',          'ORUZJE',         '2026-02-11 08:00:00', '2026-02-10 18:00:00', 'Liman, park',         'Sef B-03',    'IZDATO_ZA_ANALIZU', 2, 3),
('DOK-2026-003', 'Lažna faktura',            'Falsifikovana faktura za konsalting',  'DOKUMENT',       '2026-03-21 09:00:00', '2026-03-20 15:00:00', 'Kancelarija osumnjičenog', 'Sef C-07', 'U_SKLADISTU',      3, 3),
('DOK-2026-004', 'Uzorak vlakana sa odeće',  'Vlakna pronađena na žrtvi',            'UZORAK',         '2026-02-12 11:00:00', '2026-02-11 09:00:00', 'Liman, stan žrtve',   'Sef B-05',    'U_SKLADISTU',       2, 3);

-- ─── ISA podtipovi dokaza ───────────────────────────────────────────────────
INSERT INTO bioloski_trag (id_dokaz, vrsta_traga, nacin_uzorkovanja, uslovi_cuvanja, kolicina) VALUES
(1, 'Krv', 'Bris sterilnim štapićem', 'Rashladni uslovi, 4°C', '2 ml');

INSERT INTO oruzje (id_dokaz, vrsta_oruzja, marka, model, kalibar, serijski_br) VALUES
(2, 'Pištolj', 'Zastava', 'CZ 99', '9mm', 'CZ-2019-45892');

INSERT INTO dokument_dokaz (id_dokaz, vrsta_dokumenta, jezik, broj_stranica) VALUES
(3, 'Faktura', 'Srpski', 2);

INSERT INTO uzorak (id_dokaz, vrsta_uzorka, kolicina, jedinica_mere, nacin_uzorkovanja, uslovi_cuvanja) VALUES
(4, 'Tekstilna vlakna', '15', 'komada', 'Pinceta + papirna koverta', 'Suvo, sobna temperatura');

-- ─── Lanac čuvanja ──────────────────────────────────────────────────────────
INSERT INTO lanac_cuvanja (akcija, datum_vreme, napomena, dokaz_id, tehnicar_id) VALUES
('Prijem dokaza',   '2026-01-16 10:00:00', 'Dokaz zaprimljen u skladište',       1, 3),
('Skladištenje',    '2026-01-16 10:15:00', 'Smešten u sef A-12',                 1, 3),
('Prijem dokaza',   '2026-02-11 08:00:00', 'Dokaz zaprimljen u skladište',       2, 3),
('Izdavanje dokaza','2026-02-12 09:00:00', 'Izdato za balističku analizu',       2, 3),
('Prijem dokaza',   '2026-03-21 09:00:00', 'Dokaz zaprimljen u skladište',       3, 3),
('Prijem dokaza',   '2026-02-12 11:00:00', 'Dokaz zaprimljen u skladište',       4, 3),
('Skladištenje',    '2026-02-12 11:20:00', 'Smešten u sef B-05',                 4, 3);

-- ─── Zahtev za dokaz ────────────────────────────────────────────────────────
INSERT INTO zahtev_za_dokaz (tip, razlog, status, datum_kreiranja, dokaz_id, podnosilac_id) VALUES
('PREDAJA', 'Potrebna balistička analiza pištolja', 'ODOBREN', '2026-02-12 08:30:00', 2, 2),
('POVRACAJ', 'Analiza završena, vraćanje dokaza u skladište', 'NA_CEKANJU', '2026-02-20 14:00:00', 2, 2);

-- ─── Zahtev za analizu ──────────────────────────────────────────────────────
INSERT INTO zahtev_za_analizu (opis, tip_analize, datum_kreiranja, datum_pocetka, rok, status, istrazitelj_id, dokaz_id, predmet_id, vestak_id) VALUES
('Balistička analiza pištolja CZ 99 — utvrditi da li je oružje korišćeno u zločinu', 'BALISTICKA', '2026-02-12 10:00:00', '2026-02-13 08:00:00', '2026-03-01 00:00:00', 'U_TOKU', 2, 2, 2, 4),
('DNK analiza krvi sa mesta zločina',                                                  'DNK',        '2026-01-17 09:00:00', NULL,                   '2026-02-15 00:00:00', 'KREIRAN', 2, 1, 1, NULL);

-- ─── Istorija statusa analize ───────────────────────────────────────────────
INSERT INTO istorija_statusa_analize (stari_status, novi_status, datum_vreme, napomena, zahtev_id, inicirao_id) VALUES
(NULL,       'KREIRAN',  '2026-02-12 10:00:00', 'Zahtev kreiran',         1, 2),
('KREIRAN',  'DODELJEN', '2026-02-12 10:30:00', 'Dodeljen veštaku',       1, 2),
('DODELJEN', 'U_TOKU',   '2026-02-13 08:00:00', 'Veštak započeo analizu', 1, 4),
(NULL,       'KREIRAN',  '2026-01-17 09:00:00', 'Zahtev kreiran',         2, 2);

-- ─── Istorija dodele ────────────────────────────────────────────────────────
INSERT INTO istorija_dodele (datum_dodele, razlog_promene, zahtev_id, vestak_id, dodelio_id) VALUES
('2026-02-12 10:30:00', NULL, 1, 4, 2);

-- ─── Tagovi ─────────────────────────────────────────────────────────────────
INSERT INTO tag (naziv, boja) VALUES
('Hitno',       '#EF4444'),
('DNK',         '#3B82F6'),
('Balistika',   '#F97316'),
('Finansije',   '#22C55E');

-- ─── Obaveštenja ────────────────────────────────────────────────────────────
INSERT INTO obavestenje (sadrzaj, procitano, tip, datum_vreme, korisnik_id, zahtev_id) VALUES
('Dodeljena vam je balistička analiza za predmet "Ubistvo – Novi Sad"', 0, 'DODELA', '2026-02-12 10:30:00', 4, 1),
('Zahtev za DNK analizu je kreiran za predmet "Razbojništvo – Beograd Centar"', 1, 'PROMENA_STATUSA', '2026-01-17 09:00:00', 2, 2);
