-- ─── Migracija: Podsistem za upravljanje forenzičkim analizama (Tim 10) ────────
-- Dodaje modele: Istrazitelj, Vestak, ZahtevZaAnalizu, RezultatAnalize,
--                IstorijaDodele, IstorijaStatusaAnalize, LogBrisanjaZahteva, Obavestenje

-- CreateTable: Istrazitelj (ISA podtip Korisnika)
CREATE TABLE `Istrazitelj` (
    `idKorisnik` INTEGER NOT NULL,
    `brojZnacke` VARCHAR(191) NOT NULL,
    `odeljenje` VARCHAR(191) NULL,
    PRIMARY KEY (`idKorisnik`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable: Vestak (ISA podtip Korisnika)
CREATE TABLE `Vestak` (
    `idKorisnik` INTEGER NOT NULL,
    `idVestak` VARCHAR(191) NOT NULL,
    `specijalnost` VARCHAR(191) NULL,
    `sertifikatBr` VARCHAR(191) NULL,
    PRIMARY KEY (`idKorisnik`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable: ZahtevZaAnalizu
CREATE TABLE `ZahtevZaAnalizu` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `opis` TEXT NULL,
    `tipAnalize` ENUM('BALISTICKA','DNK','DIGITALNA','HEMIJSKA','TOKSIKOLOSKA','DOKUMENTOLOSKA','DRUGA') NOT NULL,
    `datumKreiranja` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `datumPocetka` DATETIME(3) NULL,
    `rok` DATETIME(3) NULL,
    `status` ENUM('KREIRAN','DODELJEN','U_TOKU','ZAVRSEN','PREKORACEN','ODBIJEN') NOT NULL DEFAULT 'KREIRAN',
    `pragUpozorenjaDana` INTEGER NOT NULL DEFAULT 3,
    `istraziteljId` INTEGER NOT NULL,
    `dokazId` INTEGER NOT NULL,
    `predmetId` INTEGER NOT NULL,
    `vestakId` INTEGER NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable: RezultatAnalize
CREATE TABLE `RezultatAnalize` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sadrzaj` TEXT NOT NULL,
    `datumUnosa` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `verifikovan` BOOLEAN NOT NULL DEFAULT false,
    `zahtevId` INTEGER NOT NULL,
    `uneaoId` INTEGER NOT NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable: IstorijaDodele
CREATE TABLE `IstorijaDodele` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `datumDodele` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `razlogPromene` TEXT NULL,
    `zahtevId` INTEGER NOT NULL,
    `vestakId` INTEGER NOT NULL,
    `dodeliooId` INTEGER NOT NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable: IstorijaStatusaAnalize
CREATE TABLE `IstorijaStatusaAnalize` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `stariStatus` ENUM('KREIRAN','DODELJEN','U_TOKU','ZAVRSEN','PREKORACEN','ODBIJEN') NULL,
    `noviStatus` ENUM('KREIRAN','DODELJEN','U_TOKU','ZAVRSEN','PREKORACEN','ODBIJEN') NOT NULL,
    `datumVreme` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `napomena` TEXT NULL,
    `zahtevId` INTEGER NOT NULL,
    `iniciraoId` INTEGER NOT NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable: LogBrisanjaZahteva
CREATE TABLE `LogBrisanjaZahteva` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `zahtevId` INTEGER NOT NULL,
    `datumBrisanja` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `razlog` TEXT NOT NULL,
    `obrisaoId` INTEGER NOT NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable: Obavestenje
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

-- CreateIndex: unique constraints
CREATE UNIQUE INDEX `Istrazitelj_brojZnacke_key` ON `Istrazitelj`(`brojZnacke`);
CREATE UNIQUE INDEX `Vestak_idVestak_key` ON `Vestak`(`idVestak`);
CREATE UNIQUE INDEX `RezultatAnalize_zahtevId_key` ON `RezultatAnalize`(`zahtevId`);

-- AddForeignKey: Istrazitelj → Korisnik
ALTER TABLE `Istrazitelj` ADD CONSTRAINT `Istrazitelj_idKorisnik_fkey`
    FOREIGN KEY (`idKorisnik`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey: Vestak → Korisnik
ALTER TABLE `Vestak` ADD CONSTRAINT `Vestak_idKorisnik_fkey`
    FOREIGN KEY (`idKorisnik`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey: ZahtevZaAnalizu → Istrazitelj, Dokaz, Predmet, Vestak
ALTER TABLE `ZahtevZaAnalizu` ADD CONSTRAINT `ZahtevZaAnalizu_istraziteljId_fkey`
    FOREIGN KEY (`istraziteljId`) REFERENCES `Istrazitelj`(`idKorisnik`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `ZahtevZaAnalizu` ADD CONSTRAINT `ZahtevZaAnalizu_dokazId_fkey`
    FOREIGN KEY (`dokazId`) REFERENCES `Dokaz`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `ZahtevZaAnalizu` ADD CONSTRAINT `ZahtevZaAnalizu_predmetId_fkey`
    FOREIGN KEY (`predmetId`) REFERENCES `Predmet`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `ZahtevZaAnalizu` ADD CONSTRAINT `ZahtevZaAnalizu_vestakId_fkey`
    FOREIGN KEY (`vestakId`) REFERENCES `Vestak`(`idKorisnik`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey: RezultatAnalize → ZahtevZaAnalizu, Korisnik
ALTER TABLE `RezultatAnalize` ADD CONSTRAINT `RezultatAnalize_zahtevId_fkey`
    FOREIGN KEY (`zahtevId`) REFERENCES `ZahtevZaAnalizu`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `RezultatAnalize` ADD CONSTRAINT `RezultatAnalize_uneaoId_fkey`
    FOREIGN KEY (`uneaoId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey: IstorijaDodele → ZahtevZaAnalizu, Vestak, Korisnik
ALTER TABLE `IstorijaDodele` ADD CONSTRAINT `IstorijaDodele_zahtevId_fkey`
    FOREIGN KEY (`zahtevId`) REFERENCES `ZahtevZaAnalizu`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `IstorijaDodele` ADD CONSTRAINT `IstorijaDodele_vestakId_fkey`
    FOREIGN KEY (`vestakId`) REFERENCES `Vestak`(`idKorisnik`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `IstorijaDodele` ADD CONSTRAINT `IstorijaDodele_dodeliooId_fkey`
    FOREIGN KEY (`dodeliooId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey: IstorijaStatusaAnalize → ZahtevZaAnalizu, Korisnik
ALTER TABLE `IstorijaStatusaAnalize` ADD CONSTRAINT `IstorijaStatusaAnalize_zahtevId_fkey`
    FOREIGN KEY (`zahtevId`) REFERENCES `ZahtevZaAnalizu`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `IstorijaStatusaAnalize` ADD CONSTRAINT `IstorijaStatusaAnalize_iniciraoId_fkey`
    FOREIGN KEY (`iniciraoId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey: LogBrisanjaZahteva → Korisnik
ALTER TABLE `LogBrisanjaZahteva` ADD CONSTRAINT `LogBrisanjaZahteva_obrisaoId_fkey`
    FOREIGN KEY (`obrisaoId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey: Obavestenje → Korisnik, ZahtevZaAnalizu
ALTER TABLE `Obavestenje` ADD CONSTRAINT `Obavestenje_korisnikId_fkey`
    FOREIGN KEY (`korisnikId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE `Obavestenje` ADD CONSTRAINT `Obavestenje_zahtevId_fkey`
    FOREIGN KEY (`zahtevId`) REFERENCES `ZahtevZaAnalizu`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
