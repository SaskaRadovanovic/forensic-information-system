"use server";

import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { revalidatePath } from "next/cache";

// ─── Tipovi za rezultat ─────────────────────────────────────────────────────

type Result = { ok: true; id?: number } | { ok: false; greska: string };

// ─── Server action: izmena dokaza (Story 3) ─────────────────────────────────

export async function izmeniDokaz(formData: FormData): Promise<Result> {
  // 1. Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  // 2. Provera autorizacije — samo TEHNICAR i ADMINISTRATOR
  const uloga = session.user.uloga;
  if (uloga !== "TEHNICAR" && uloga !== "ADMINISTRATOR") {
    return { ok: false, greska: "Nemate dozvolu za izmenu dokaza." };
  }

  // 3. Ekstrakcija podataka iz forme
  const dokazIdStr = formData.get("dokazId") as string | null;
  const naziv = (formData.get("naziv") as string | null)?.trim();
  const opis = (formData.get("opis") as string | null)?.trim();
  const predmetIdStr = formData.get("predmetId") as string | null;
  const lokacijaSkladistenja = (formData.get("lokacijaSkladistenja") as string | null)?.trim();
  const datumPrijemaStr = formData.get("datumPrijema") as string | null;
  const tipDokaza = formData.get("tipDokaza") as string | null;
  const datumPronalaskaStr = formData.get("datumPronalaska") as string | null;
  const lokacijaPronalaska = (formData.get("lokacijaPronalaska") as string | null)?.trim();

  // 4. Validacija obaveznih polja
  if (!naziv) return { ok: false, greska: "Naziv je obavezan." };
  if (!predmetIdStr) return { ok: false, greska: "Predmet je obavezan." };
  if (!datumPrijemaStr) return { ok: false, greska: "Datum prijema je obavezan." };

  const dokazId = parseInt(dokazIdStr ?? "", 10);
  const predmetId = parseInt(predmetIdStr, 10);
  if (isNaN(dokazId) || isNaN(predmetId)) {
    return { ok: false, greska: "Nevažeći podaci." };
  }

  const datumPrijema = new Date(datumPrijemaStr);
  if (isNaN(datumPrijema.getTime())) return { ok: false, greska: "Nevažeći datum." };

  // Opcioni datum pronalaska
  const datumPronalaska = datumPronalaskaStr ? new Date(datumPronalaskaStr) : null;

  try {
    // 5. Provera da dokaz postoji i da nije arhiviran
    const trenutni = await prisma.dokaz.findUnique({
      where: { id: dokazId },
      select: { status: true, tipDokaza: true },
    });
    if (!trenutni) return { ok: false, greska: "Dokaz nije pronađen." };
    if (trenutni.status === "ARHIVIRANO") {
      return { ok: false, greska: "Arhivirani dokaz se ne može menjati." };
    }

    // 6. Pronalaženje tehničar profila
    const korisnikId = parseInt(session.user.id, 10);
    const tehnicarProfil = await prisma.tehnicarZaDokaze.findUnique({
      where: { idKorisnik: korisnikId },
    });
    if (!tehnicarProfil) {
      return { ok: false, greska: "Korisnik nema tehničar profil." };
    }

    // 7. Ekstrakcija specifičnih polja po tipu
    const tip = tipDokaza ?? trenutni.tipDokaza;

    await prisma.$transaction(async (tx) => {
      // 7a. Ažuriranje zajedničkih podataka dokaza
      await tx.dokaz.update({
        where: { id: dokazId },
        data: {
          naziv,
          opis: opis || null,
          predmetId,
          lokacijaSkladistenja: lokacijaSkladistenja || null,
          datumPrijema,
          datumPronalaska,
          lokacijaPronalaska: lokacijaPronalaska || null,
        },
      });

      // 7b. Ažuriranje specifičnih polja po tipu
      await azurirajSpecificnaPolja(tx, dokazId, tip, formData);

      // 7c. Zapis u lanac čuvanja — izmena podataka
      await tx.lanacCuvanja.create({
        data: {
          dokazId,
          tehnicarId: korisnikId,
          akcija: "Izmena podataka",
          datumVreme: new Date(),
          napomena: "Podaci dokaza su izmenjeni",
        },
      });
    });

    // 8. Revalidacija keša
    revalidatePath(`/dokazi/${dokazId}`);
    revalidatePath("/dokazi");
    return { ok: true, id: dokazId };
  } catch (error) {
    console.error("Greška pri izmeni dokaza:", error);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Server action: arhiviranje dokaza (Story 4) ────────────────────────────

export async function arhivirajDokaz(dokazId: number): Promise<Result> {
  // 1. Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  // 2. Provera autorizacije — samo ADMINISTRATOR
  if (session.user.uloga !== "ADMINISTRATOR") {
    return { ok: false, greska: "Samo administrator može arhivirati dokaze." };
  }

  try {
    // 3. Provera da dokaz postoji i da nije već arhiviran
    const dokaz = await prisma.dokaz.findUnique({
      where: { id: dokazId },
      select: { status: true, sifraDokaza: true },
    });
    if (!dokaz) return { ok: false, greska: "Dokaz nije pronađen." };
    if (dokaz.status === "ARHIVIRANO") {
      return { ok: false, greska: "Dokaz je već arhiviran." };
    }

    // 4. Pronalaženje tehničar profila
    const korisnikId = parseInt(session.user.id, 10);
    let tehnicarId = korisnikId;
    const tehnicarProfil = await prisma.tehnicarZaDokaze.findUnique({
      where: { idKorisnik: korisnikId },
    });
    if (tehnicarProfil) {
      tehnicarId = korisnikId;
    }

    // 5. Arhiviranje u transakciji
    await prisma.$transaction(async (tx) => {
      // 5a. Promena statusa na ARHIVIRANO
      await tx.dokaz.update({
        where: { id: dokazId },
        data: { status: "ARHIVIRANO" },
      });

      // 5b. Zapis u lanac čuvanja — arhiviranje
      if (tehnicarProfil) {
        await tx.lanacCuvanja.create({
          data: {
            dokazId,
            tehnicarId,
            akcija: "Arhiviranje",
            datumVreme: new Date(),
            napomena: `Dokaz ${dokaz.sifraDokaza} arhiviran od strane administratora`,
          },
        });
      }
    });

    // 6. Revalidacija keša
    revalidatePath(`/dokazi/${dokazId}`);
    revalidatePath("/dokazi");
    return { ok: true };
  } catch (error) {
    console.error("Greška pri arhiviranju dokaza:", error);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Helper: ažuriranje specifičnih polja u bazi ────────────────────────────

async function azurirajSpecificnaPolja(
  tx: any,
  dokazId: number,
  tipDokaza: string,
  formData: FormData
) {
  switch (tipDokaza) {
    case "BIOLOSKI_TRAG":
      await tx.bioloskiTrag.upsert({
        where: { idDokaz: dokazId },
        create: {
          idDokaz: dokazId,
          vrstaTraga: (formData.get("vrstaTraga") as string)?.trim() || null,
          nacinUzorkovanja: (formData.get("nacinUzorkovanja") as string)?.trim() || null,
          usloviCuvanja: (formData.get("usloviCuvanja") as string)?.trim() || null,
          kolicina: (formData.get("kolicina") as string)?.trim() || null,
        },
        update: {
          vrstaTraga: (formData.get("vrstaTraga") as string)?.trim() || null,
          nacinUzorkovanja: (formData.get("nacinUzorkovanja") as string)?.trim() || null,
          usloviCuvanja: (formData.get("usloviCuvanja") as string)?.trim() || null,
          kolicina: (formData.get("kolicina") as string)?.trim() || null,
        },
      });
      break;
    case "ORUZJE":
      await tx.oruzje.upsert({
        where: { idDokaz: dokazId },
        create: {
          idDokaz: dokazId,
          vrstaOruzja: (formData.get("vrstaOruzja") as string)?.trim() || null,
          marka: (formData.get("marka") as string)?.trim() || null,
          model: (formData.get("model") as string)?.trim() || null,
          kalibar: (formData.get("kalibar") as string)?.trim() || null,
          serijskiBr: (formData.get("serijskiBr") as string)?.trim() || null,
        },
        update: {
          vrstaOruzja: (formData.get("vrstaOruzja") as string)?.trim() || null,
          marka: (formData.get("marka") as string)?.trim() || null,
          model: (formData.get("model") as string)?.trim() || null,
          kalibar: (formData.get("kalibar") as string)?.trim() || null,
          serijskiBr: (formData.get("serijskiBr") as string)?.trim() || null,
        },
      });
      break;
    case "DOKUMENT":
      await tx.dokumentDokaz.upsert({
        where: { idDokaz: dokazId },
        create: {
          idDokaz: dokazId,
          vrstaDokumenta: (formData.get("vrstaDokumenta") as string)?.trim() || null,
          jezik: (formData.get("jezik") as string)?.trim() || null,
          brojStranica: formData.get("brojStranica") ? parseInt(formData.get("brojStranica") as string, 10) : null,
        },
        update: {
          vrstaDokumenta: (formData.get("vrstaDokumenta") as string)?.trim() || null,
          jezik: (formData.get("jezik") as string)?.trim() || null,
          brojStranica: formData.get("brojStranica") ? parseInt(formData.get("brojStranica") as string, 10) : null,
        },
      });
      break;
    case "ODECA":
      await tx.odeca.upsert({
        where: { idDokaz: dokazId },
        create: {
          idDokaz: dokazId,
          velicina: (formData.get("velicina") as string)?.trim() || null,
          vrstaOdevnogPredmeta: (formData.get("vrstaOdevnogPredmeta") as string)?.trim() || null,
          boja: (formData.get("boja") as string)?.trim() || null,
          stanje: (formData.get("stanje") as string)?.trim() || null,
        },
        update: {
          velicina: (formData.get("velicina") as string)?.trim() || null,
          vrstaOdevnogPredmeta: (formData.get("vrstaOdevnogPredmeta") as string)?.trim() || null,
          boja: (formData.get("boja") as string)?.trim() || null,
          stanje: (formData.get("stanje") as string)?.trim() || null,
        },
      });
      break;
    case "UZORAK":
      await tx.uzorak.upsert({
        where: { idDokaz: dokazId },
        create: {
          idDokaz: dokazId,
          vrstaUzorka: (formData.get("vrstaUzorka") as string)?.trim() || null,
          kolicina: (formData.get("kolicinaUzorka") as string)?.trim() || null,
          jedinicaMere: (formData.get("jedinicaMere") as string)?.trim() || null,
          nacinUzorkovanja: (formData.get("nacinUzorkovanjaUzorka") as string)?.trim() || null,
          usloviCuvanja: (formData.get("usloviCuvanjaUzorka") as string)?.trim() || null,
        },
        update: {
          vrstaUzorka: (formData.get("vrstaUzorka") as string)?.trim() || null,
          kolicina: (formData.get("kolicinaUzorka") as string)?.trim() || null,
          jedinicaMere: (formData.get("jedinicaMere") as string)?.trim() || null,
          nacinUzorkovanja: (formData.get("nacinUzorkovanjaUzorka") as string)?.trim() || null,
          usloviCuvanja: (formData.get("usloviCuvanjaUzorka") as string)?.trim() || null,
        },
      });
      break;
  }
}
