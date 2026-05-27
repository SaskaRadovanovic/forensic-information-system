-- CreateTable
CREATE TABLE `Korisnik` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ime` VARCHAR(191) NOT NULL,
    `prezime` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `lozinkaHash` VARCHAR(191) NOT NULL,
    `uloga` ENUM('ADMINISTRATOR', 'ISTRAZITELJ', 'TEHNICAR', 'VESTAK') NOT NULL,
    `aktivan` BOOLEAN NOT NULL DEFAULT true,
    `datumKreiranja` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    UNIQUE INDEX `Korisnik_email_key`(`email`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Predmet` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `naziv` VARCHAR(191) NOT NULL,
    `opis` TEXT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Dokument` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `naziv` VARCHAR(191) NOT NULL,
    `putanja` VARCHAR(191) NOT NULL,
    `verzija` INTEGER NOT NULL DEFAULT 1,
    `status` ENUM('AKTIVAN', 'ARHIVIRAN') NOT NULL DEFAULT 'AKTIVAN',
    `datumKreiranja` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `predmetId` INTEGER NOT NULL,
    `autorId` INTEGER NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Metapodatak` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `kljuc` VARCHAR(191) NOT NULL,
    `vrednost` TEXT NOT NULL,
    `dokumentId` INTEGER NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `PravoPristupa` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `nivoPristupa` VARCHAR(191) NOT NULL,
    `datumDodele` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `korisnikId` INTEGER NOT NULL,
    `dokumentId` INTEGER NOT NULL,

    UNIQUE INDEX `PravoPristupa_korisnikId_dokumentId_key`(`korisnikId`, `dokumentId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Tag` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `naziv` VARCHAR(191) NOT NULL,

    UNIQUE INDEX `Tag_naziv_key`(`naziv`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `DokumentTag` (
    `dokumentId` INTEGER NOT NULL,
    `tagId` INTEGER NOT NULL,

    PRIMARY KEY (`dokumentId`, `tagId`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `DokumentArhiva` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `putanjaStaraVerzija` VARCHAR(191) NOT NULL,
    `verzija` INTEGER NOT NULL,
    `razlogIzmene` TEXT NULL,
    `datumArhiviranja` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `dokumentId` INTEGER NOT NULL,
    `sacuvaoId` INTEGER NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `Dokument` ADD CONSTRAINT `Dokument_predmetId_fkey` FOREIGN KEY (`predmetId`) REFERENCES `Predmet`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Dokument` ADD CONSTRAINT `Dokument_autorId_fkey` FOREIGN KEY (`autorId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Metapodatak` ADD CONSTRAINT `Metapodatak_dokumentId_fkey` FOREIGN KEY (`dokumentId`) REFERENCES `Dokument`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `PravoPristupa` ADD CONSTRAINT `PravoPristupa_korisnikId_fkey` FOREIGN KEY (`korisnikId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `PravoPristupa` ADD CONSTRAINT `PravoPristupa_dokumentId_fkey` FOREIGN KEY (`dokumentId`) REFERENCES `Dokument`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `DokumentTag` ADD CONSTRAINT `DokumentTag_dokumentId_fkey` FOREIGN KEY (`dokumentId`) REFERENCES `Dokument`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `DokumentTag` ADD CONSTRAINT `DokumentTag_tagId_fkey` FOREIGN KEY (`tagId`) REFERENCES `Tag`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `DokumentArhiva` ADD CONSTRAINT `DokumentArhiva_dokumentId_fkey` FOREIGN KEY (`dokumentId`) REFERENCES `Dokument`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `DokumentArhiva` ADD CONSTRAINT `DokumentArhiva_sacuvaoId_fkey` FOREIGN KEY (`sacuvaoId`) REFERENCES `Korisnik`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
