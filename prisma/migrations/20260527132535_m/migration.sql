/*
  Warnings:

  - A unique constraint covering the columns `[korIme]` on the table `Korisnik` will be added. If there are existing duplicate values, this will fail.

*/
-- AlterTable
ALTER TABLE `korisnik` ADD COLUMN `korIme` VARCHAR(191) NULL;

-- CreateTable
CREATE TABLE `TehnicarZaDokaze` (
    `idKorisnik` INTEGER NOT NULL,
    `idTd` VARCHAR(191) NOT NULL,
    `odeljenje` VARCHAR(191) NULL,

    UNIQUE INDEX `TehnicarZaDokaze_idTd_key`(`idTd`),
    PRIMARY KEY (`idKorisnik`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Dokaz` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sifraDokaza` VARCHAR(191) NOT NULL,
    `naziv` VARCHAR(191) NOT NULL,
    `opis` TEXT NULL,
    `tipDokaza` ENUM('BIOLOSKI_TRAG', 'ORUZJE', 'DOKUMENT', 'ODECA', 'UZORAK') NOT NULL,
    `datumPrijema` DATETIME(3) NOT NULL,
    `datumPronalaska` DATETIME(3) NULL,
    `lokacijaPronalaska` VARCHAR(191) NULL,
    `lokacijaSkladistenja` VARCHAR(191) NULL,
    `status` ENUM('PRIJEM', 'U_SKLADISTU', 'IZDATO_ZA_ANALIZU', 'VRACENO', 'KOMPROMITOVAN', 'ARHIVIRANO') NOT NULL DEFAULT 'PRIJEM',
    `predmetId` INTEGER NOT NULL,
    `tehnicarId` INTEGER NOT NULL,

    UNIQUE INDEX `Dokaz_sifraDokaza_key`(`sifraDokaza`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `LanacCuvanja` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `akcija` VARCHAR(191) NOT NULL,
    `datumVreme` DATETIME(3) NOT NULL,
    `napomena` TEXT NULL,
    `dokazId` INTEGER NOT NULL,
    `tehnicarId` INTEGER NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `BioloskiTrag` (
    `idDokaz` INTEGER NOT NULL,
    `vrstaTraga` VARCHAR(191) NULL,
    `nacinUzorkovanja` VARCHAR(191) NULL,
    `usloviCuvanja` VARCHAR(191) NULL,
    `kolicina` VARCHAR(191) NULL,

    PRIMARY KEY (`idDokaz`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Oruzje` (
    `idDokaz` INTEGER NOT NULL,
    `vrstaOruzja` VARCHAR(191) NULL,
    `marka` VARCHAR(191) NULL,
    `model` VARCHAR(191) NULL,
    `kalibar` VARCHAR(191) NULL,
    `serijskiBr` VARCHAR(191) NULL,

    UNIQUE INDEX `Oruzje_serijskiBr_key`(`serijskiBr`),
    PRIMARY KEY (`idDokaz`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `DokumentDokaz` (
    `idDokaz` INTEGER NOT NULL,
    `vrstaDokumenta` VARCHAR(191) NULL,
    `jezik` VARCHAR(191) NULL,
    `brojStranica` INTEGER NULL,

    PRIMARY KEY (`idDokaz`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Odeca` (
    `idDokaz` INTEGER NOT NULL,
    `velicina` VARCHAR(191) NULL,
    `vrstaOdevnogPredmeta` VARCHAR(191) NULL,
    `boja` VARCHAR(191) NULL,
    `stanje` VARCHAR(191) NULL,

    PRIMARY KEY (`idDokaz`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Uzorak` (
    `idDokaz` INTEGER NOT NULL,
    `vrstaUzorka` VARCHAR(191) NULL,
    `kolicina` VARCHAR(191) NULL,
    `jedinicaMere` VARCHAR(191) NULL,
    `nacinUzorkovanja` VARCHAR(191) NULL,
    `usloviCuvanja` VARCHAR(191) NULL,

    PRIMARY KEY (`idDokaz`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `ZahtevZaDokaz` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `tip` VARCHAR(191) NOT NULL,
    `razlog` TEXT NULL,
    `status` ENUM('NA_CEKANJU', 'ODOBREN', 'ODBIJEN') NOT NULL DEFAULT 'NA_CEKANJU',
    `datumKreiranja` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `datumObrade` DATETIME(3) NULL,
    `napomena` TEXT NULL,
    `dokazId` INTEGER NOT NULL,
    `podnosilacId` INTEGER NOT NULL,
    `tehnicarId` INTEGER NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Istrazitelj` (
    `idKorisnik` INTEGER NOT NULL,
    `brojZnacke` VARCHAR(191) NOT NULL,
    `odeljenje` VARCHAR(191) NULL,

    UNIQUE INDEX `Istrazitelj_brojZnacke_key`(`brojZnacke`),
    PRIMARY KEY (`idKorisnik`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Vestak` (
    `idKorisnik` INTEGER NOT NULL,
    `idVestak` VARCHAR(191) NOT NULL,
    `specijalnost` VARCHAR(191) NULL,
    `sertifikatBr` VARCHAR(191) NULL,

    UNIQUE INDEX `Vestak_idVestak_key`(`idVestak`),
    PRIMARY KEY (`idKorisnik`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `ZahtevZaAnalizu` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `opis` TEXT NULL,
    `tipAnalize` ENUM('BALISTICKA', 'DNK', 'DIGITALNA', 'HEMIJSKA', 'TOKSIKOLOSKA', 'DOKUMENTOLOSKA', 'DRUGA') NOT NULL,
    `datumKreiranja` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `datumPocetka` DATETIME(3) NULL,
    `rok` DATETIME(3) NULL,
    `status` ENUM('KREIRAN', 'DODELJEN', 'U_TOKU', 'ZAVRSEN', 'PREKORACEN', 'ODBIJEN') NOT NULL DEFAULT 'KREIRAN',
    `pragUpozorenjaDana` INTEGER NOT NULL DEFAULT 3,
    `istraziteljId` INTEGER NOT NULL,
    `dokazId` INTEGER NOT NULL,
    `predmetId` INTEGER NOT NULL,
    `vestakId` INTEGER NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `RezultatAnalize` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sadrzaj` TEXT NOT NULL,
    `datumUnosa` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `verifikovan` BOOLEAN NOT NULL DEFAULT false,
    `zahtevId` INTEGER NOT NULL,
    `uneaoId` INTEGER NOT NULL,

    UNIQUE INDEX `RezultatAnalize_zahtevId_key`(`zahtevId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `IstorijaDodele` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `datumDodele` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `razlogPromene` TEXT NULL,
    `zahtevId` INTEGER NOT NULL,
    `vestakId` INTEGER NOT NULL,
    `dodeliooId` INTEGER NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `IstorijaStatusaAnalize` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `stariStatus` ENUM('KREIRAN', 'DODELJEN', 'U_TOKU', 'ZAVRSEN', 'PREKORACEN', 'ODBIJEN') NULL,
    `noviStatus` ENUM('KREIRAN', 'DODELJEN', 'U_TOKU', 'ZAVRSEN', 'PREKORACEN', 'ODBIJEN') NOT NULL,
    `datumVreme` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `napomena` TEXT NULL,
    `zahtevId` INTEGER NOT NULL,
    `iniciraoId` INTEGER NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `LogBrisanjaZahteva` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `zahtevId` INTEGER NOT NULL,
    `datumBrisanja` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `razlog` TEXT NOT NULL,
    `obrisaoId` INTEGER NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `IstorijaIzmeneZahteva` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `polje` VARCHAR(191) NOT NULL,
    `staraVrednost` TEXT NULL,
    `novaVrednost` TEXT NOT NULL,
    `datumVreme` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `zahtevId` INTEGER NOT NULL,
    `korisnikId` INTEGER NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Obavestenje` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sadrzaj` TEXT NOT NULL,
    `procitano` BOOLEAN NOT NULL DEFAULT false,
    `tip` VARCHAR(191) NOT NULL,
    `datumVreme` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `korisnikId` INTEGER NOT NULL,
    `zahtevId` INTEGER NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateIndex
CREATE UNIQUE INDEX `Korisnik_korIme_key` ON `Korisnik`(`korIme`);

-- AddForeignKey
ALTER TABLE `TehnicarZaDokaze` ADD CONSTRAINT `TehnicarZaDokaze_idKorisnik_fkey` FOREIGN KEY (`idKorisnik`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Dokaz` ADD CONSTRAINT `Dokaz_predmetId_fkey` FOREIGN KEY (`predmetId`) REFERENCES `Predmet`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Dokaz` ADD CONSTRAINT `Dokaz_tehnicarId_fkey` FOREIGN KEY (`tehnicarId`) REFERENCES `TehnicarZaDokaze`(`idKorisnik`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `LanacCuvanja` ADD CONSTRAINT `LanacCuvanja_dokazId_fkey` FOREIGN KEY (`dokazId`) REFERENCES `Dokaz`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `LanacCuvanja` ADD CONSTRAINT `LanacCuvanja_tehnicarId_fkey` FOREIGN KEY (`tehnicarId`) REFERENCES `TehnicarZaDokaze`(`idKorisnik`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `BioloskiTrag` ADD CONSTRAINT `BioloskiTrag_idDokaz_fkey` FOREIGN KEY (`idDokaz`) REFERENCES `Dokaz`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Oruzje` ADD CONSTRAINT `Oruzje_idDokaz_fkey` FOREIGN KEY (`idDokaz`) REFERENCES `Dokaz`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `DokumentDokaz` ADD CONSTRAINT `DokumentDokaz_idDokaz_fkey` FOREIGN KEY (`idDokaz`) REFERENCES `Dokaz`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Odeca` ADD CONSTRAINT `Odeca_idDokaz_fkey` FOREIGN KEY (`idDokaz`) REFERENCES `Dokaz`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Uzorak` ADD CONSTRAINT `Uzorak_idDokaz_fkey` FOREIGN KEY (`idDokaz`) REFERENCES `Dokaz`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ZahtevZaDokaz` ADD CONSTRAINT `ZahtevZaDokaz_dokazId_fkey` FOREIGN KEY (`dokazId`) REFERENCES `Dokaz`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ZahtevZaDokaz` ADD CONSTRAINT `ZahtevZaDokaz_podnosilacId_fkey` FOREIGN KEY (`podnosilacId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ZahtevZaDokaz` ADD CONSTRAINT `ZahtevZaDokaz_tehnicarId_fkey` FOREIGN KEY (`tehnicarId`) REFERENCES `TehnicarZaDokaze`(`idKorisnik`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Istrazitelj` ADD CONSTRAINT `Istrazitelj_idKorisnik_fkey` FOREIGN KEY (`idKorisnik`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Vestak` ADD CONSTRAINT `Vestak_idKorisnik_fkey` FOREIGN KEY (`idKorisnik`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ZahtevZaAnalizu` ADD CONSTRAINT `ZahtevZaAnalizu_istraziteljId_fkey` FOREIGN KEY (`istraziteljId`) REFERENCES `Istrazitelj`(`idKorisnik`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ZahtevZaAnalizu` ADD CONSTRAINT `ZahtevZaAnalizu_dokazId_fkey` FOREIGN KEY (`dokazId`) REFERENCES `Dokaz`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ZahtevZaAnalizu` ADD CONSTRAINT `ZahtevZaAnalizu_predmetId_fkey` FOREIGN KEY (`predmetId`) REFERENCES `Predmet`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ZahtevZaAnalizu` ADD CONSTRAINT `ZahtevZaAnalizu_vestakId_fkey` FOREIGN KEY (`vestakId`) REFERENCES `Vestak`(`idKorisnik`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `RezultatAnalize` ADD CONSTRAINT `RezultatAnalize_zahtevId_fkey` FOREIGN KEY (`zahtevId`) REFERENCES `ZahtevZaAnalizu`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `RezultatAnalize` ADD CONSTRAINT `RezultatAnalize_uneaoId_fkey` FOREIGN KEY (`uneaoId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `IstorijaDodele` ADD CONSTRAINT `IstorijaDodele_zahtevId_fkey` FOREIGN KEY (`zahtevId`) REFERENCES `ZahtevZaAnalizu`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `IstorijaDodele` ADD CONSTRAINT `IstorijaDodele_vestakId_fkey` FOREIGN KEY (`vestakId`) REFERENCES `Vestak`(`idKorisnik`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `IstorijaDodele` ADD CONSTRAINT `IstorijaDodele_dodeliooId_fkey` FOREIGN KEY (`dodeliooId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `IstorijaStatusaAnalize` ADD CONSTRAINT `IstorijaStatusaAnalize_zahtevId_fkey` FOREIGN KEY (`zahtevId`) REFERENCES `ZahtevZaAnalizu`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `IstorijaStatusaAnalize` ADD CONSTRAINT `IstorijaStatusaAnalize_iniciraoId_fkey` FOREIGN KEY (`iniciraoId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `LogBrisanjaZahteva` ADD CONSTRAINT `LogBrisanjaZahteva_obrisaoId_fkey` FOREIGN KEY (`obrisaoId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `IstorijaIzmeneZahteva` ADD CONSTRAINT `IstorijaIzmeneZahteva_zahtevId_fkey` FOREIGN KEY (`zahtevId`) REFERENCES `ZahtevZaAnalizu`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `IstorijaIzmeneZahteva` ADD CONSTRAINT `IstorijaIzmeneZahteva_korisnikId_fkey` FOREIGN KEY (`korisnikId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Obavestenje` ADD CONSTRAINT `Obavestenje_korisnikId_fkey` FOREIGN KEY (`korisnikId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Obavestenje` ADD CONSTRAINT `Obavestenje_zahtevId_fkey` FOREIGN KEY (`zahtevId`) REFERENCES `ZahtevZaAnalizu`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
