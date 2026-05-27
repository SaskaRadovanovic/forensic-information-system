-- ─── Migracija: Proširenje FazaPredmeta sa 3 na 5 faza ───────────────────────
-- Stare faze: ISTRAGA, PRIKUPLJANJE_DOKAZA, SUDJENJE
-- Nove faze:  OTVOREN_SLUCAJ, PRIKUPLJANJE_DOKAZA, ANALIZA_DOKAZA, DONOSENJE_ZAKLJUCKA, ZATVOREN_SLUCAJ

-- Korak 1: Privremeno proširujemo enum da sadrži I stare I nove vrednosti
ALTER TABLE `Predmet` MODIFY COLUMN `faza`
  ENUM('ISTRAGA','PRIKUPLJANJE_DOKAZA','SUDJENJE','OTVOREN_SLUCAJ','ANALIZA_DOKAZA','DONOSENJE_ZAKLJUCKA','ZATVOREN_SLUCAJ')
  NOT NULL DEFAULT 'OTVOREN_SLUCAJ';

-- Korak 2: Migriramo postojeće podatke na nove vrednosti
UPDATE `Predmet` SET `faza` = 'OTVOREN_SLUCAJ'      WHERE `faza` = 'ISTRAGA';
UPDATE `Predmet` SET `faza` = 'DONOSENJE_ZAKLJUCKA'  WHERE `faza` = 'SUDJENJE';

-- Korak 3: Uklanjamo stare vrednosti iz enuma (ostaju samo nove 5)
ALTER TABLE `Predmet` MODIFY COLUMN `faza`
  ENUM('OTVOREN_SLUCAJ','PRIKUPLJANJE_DOKAZA','ANALIZA_DOKAZA','DONOSENJE_ZAKLJUCKA','ZATVOREN_SLUCAJ')
  NOT NULL DEFAULT 'OTVOREN_SLUCAJ';
