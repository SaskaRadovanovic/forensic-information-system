"use server";

import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { revalidatePath } from "next/cache";

// ─── Tipovi za rezultat ─────────────────────────────────────────────────────

export type KreirajDokazResult =
  | { ok: true; id: number }
  | { ok: false; greska: string };

// ─── Generisanje šifre dokaza ───────────────────────────────────────────────

async function generisiSifruDokaza(): Promise<string> {
  // Uzimamo tekuću godinu
  const godina = new Date().getFullYear();

  // Brojimo koliko dokaza je kreirano u ovoj godini
  const brojDokaza = await prisma.dokaz.count({
    where: {
      sifraDokaza: {
        startsWith: `DOK-${godina}-`,
      },
    },
  });

  // Generišemo šifru u formatu DOK-YYYY-NNNN
  const redniBroj = (brojDokaza + 1).toString().padStart(4, "0");
  return `DOK-${godina}-${redniBroj}`;
}

// ─── Server action: kreiranje novog dokaza ──────────────────────────────────

export async function kreirajDokaz(
  formData: FormData
): Promise<KreirajDokazResult> {
  // 1. Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) {
    return { ok: false, greska: "Niste prijavljeni." };
  }

  // 2. Provera autorizacije — samo TEHNICAR i ADMINISTRATOR
  const uloga = session.user.uloga;
  if (uloga !== "TEHNICAR" && uloga !== "ADMINISTRATOR") {
    return { ok: false, greska: "Nemate dozvolu za kreiranje dokaza." };
  }

  // 3. Ekstrakcija zajedničkih polja iz forme
  const naziv = (formData.get("naziv") as string | null)?.trim();
  const opis = (formData.get("opis") as string | null)?.trim();
  const tipDokaza = formData.get("tipDokaza") as string | null;
  const predmetIdStr = formData.get("predmetId") as string | null;
  const lokacijaSkladistenja = (formData.get("lokacijaSkladistenja") as string | null)?.trim();
  const datumPrijemaStr = formData.get("datumPrijema") as string | null;
  const datumPronalaskaStr = formData.get("datumPronalaska") as string | null;
  const lokacijaPronalaska = (formData.get("lokacijaPronalaska") as string | null)?.trim();

  // 4. Validacija obaveznih polja
  if (!naziv) return { ok: false, greska: "Naziv dokaza je obavezan." };
  if (!tipDokaza) return { ok: false, greska: "Tip dokaza je obavezan." };
  if (!predmetIdStr) return { ok: false, greska: "Predmet je obavezan." };
  if (!datumPrijemaStr) return { ok: false, greska: "Datum prijema je obavezan." };

  const predmetId = parseInt(predmetIdStr, 10);
  if (isNaN(predmetId)) return { ok: false, greska: "Nevažeći predmet." };

  const datumPrijema = new Date(datumPrijemaStr);
  if (isNaN(datumPrijema.getTime())) return { ok: false, greska: "Nevažeći datum prijema." };

  // Opcioni datum pronalaska
  const datumPronalaska = datumPronalaskaStr ? new Date(datumPronalaskaStr) : null;
  if (datumPronalaska && isNaN(datumPronalaska.getTime())) {
    return { ok: false, greska: "Nevažeći datum pronalaska." };
  }

  // 5. Pronalaženje tehničar profila za trenutnog korisnika
  const korisnikId = parseInt(session.user.id, 10);
  const tehnicarProfil = await prisma.tehnicarZaDokaze.findUnique({
    where: { idKorisnik: korisnikId },
  });

  if (!tehnicarProfil) {
    return { ok: false, greska: "Korisnik nema tehničar profil." };
  }

  // 6. Ekstrakcija specifičnih polja po tipu dokaza
  const specificnaPolja = ekstrakcijaPoljaPotTipu(formData, tipDokaza);

  // TODO: Provera faze predmeta — drugi tim implementira faze istrage
  // Kad bude dostupno, proveriti da je predmet u fazi PRIKUPLJANJE_DOKAZA

  try {
    // 7. Generisanje šifre dokaza
    const sifraDokaza = await generisiSifruDokaza();

    // 8. Kreiranje dokaza u transakciji
    const dokaz = await prisma.$transaction(async (tx) => {
      // 8a. Kreiranje glavnog zapisa dokaza
      const noviDokaz = await tx.dokaz.create({
        data: {
          sifraDokaza,
          naziv,
          opis: opis || null,
          tipDokaza: tipDokaza as any,
          datumPrijema,
          datumPronalaska,
          lokacijaPronalaska: lokacijaPronalaska || null,
          lokacijaSkladistenja: lokacijaSkladistenja || null,
          status: "U_SKLADISTU",
          predmetId,
          tehnicarId: korisnikId,
        },
      });

      // 8b. Kreiranje specifičnih polja po tipu
      await kreirajSpecificnaPolja(tx, noviDokaz.id, tipDokaza, specificnaPolja);

      // 8c. Automatski zapis u lanac čuvanja — prijem dokaza
      await tx.lanacCuvanja.create({
        data: {
          dokazId: noviDokaz.id,
          tehnicarId: korisnikId,
          akcija: "Prijem dokaza",
          datumVreme: new Date(),
          napomena: `Dokaz ${sifraDokaza} evidentiran u sistemu`,
        },
      });

      return noviDokaz;
    });

    // 9. Revalidacija keša stranica
    revalidatePath("/dokazi");
    return { ok: true, id: dokaz.id };
  } catch (error) {
    console.error("Greška pri kreiranju dokaza:", error);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Helper: ekstrakcija specifičnih polja po tipu ──────────────────────────

function ekstrakcijaPoljaPotTipu(
  formData: FormData,
  tipDokaza: string
): Record<string, string | null> {
  const polja: Record<string, string | null> = {};

  switch (tipDokaza) {
    case "BIOLOSKI_TRAG":
      polja.vrstaTraga = (formData.get("vrstaTraga") as string)?.trim() || null;
      polja.nacinUzorkovanja = (formData.get("nacinUzorkovanja") as string)?.trim() || null;
      polja.usloviCuvanja = (formData.get("usloviCuvanja") as string)?.trim() || null;
      polja.kolicina = (formData.get("kolicina") as string)?.trim() || null;
      break;
    case "ORUZJE":
      polja.vrstaOruzja = (formData.get("vrstaOruzja") as string)?.trim() || null;
      polja.marka = (formData.get("marka") as string)?.trim() || null;
      polja.model = (formData.get("model") as string)?.trim() || null;
      polja.kalibar = (formData.get("kalibar") as string)?.trim() || null;
      polja.serijskiBr = (formData.get("serijskiBr") as string)?.trim() || null;
      break;
    case "DOKUMENT":
      polja.vrstaDokumenta = (formData.get("vrstaDokumenta") as string)?.trim() || null;
      polja.jezik = (formData.get("jezik") as string)?.trim() || null;
      polja.brojStranica = (formData.get("brojStranica") as string)?.trim() || null;
      break;
    case "ODECA":
      polja.velicina = (formData.get("velicina") as string)?.trim() || null;
      polja.vrstaOdevnogPredmeta = (formData.get("vrstaOdevnogPredmeta") as string)?.trim() || null;
      polja.boja = (formData.get("boja") as string)?.trim() || null;
      polja.stanje = (formData.get("stanje") as string)?.trim() || null;
      break;
    case "UZORAK":
      polja.vrstaUzorka = (formData.get("vrstaUzorka") as string)?.trim() || null;
      polja.kolicina = (formData.get("kolicinaUzorka") as string)?.trim() || null;
      polja.jedinicaMere = (formData.get("jedinicaMere") as string)?.trim() || null;
      polja.nacinUzorkovanja = (formData.get("nacinUzorkovanjaUzorka") as string)?.trim() || null;
      polja.usloviCuvanja = (formData.get("usloviCuvanjaUzorka") as string)?.trim() || null;
      break;
  }

  return polja;
}

// ─── Helper: kreiranje specifičnih polja u bazi ─────────────────────────────

async function kreirajSpecificnaPolja(
  tx: any,
  dokazId: number,
  tipDokaza: string,
  polja: Record<string, string | null>
) {
  switch (tipDokaza) {
    case "BIOLOSKI_TRAG":
      await tx.bioloskiTrag.create({
        data: {
          idDokaz: dokazId,
          vrstaTraga: polja.vrstaTraga,
          nacinUzorkovanja: polja.nacinUzorkovanja,
          usloviCuvanja: polja.usloviCuvanja,
          kolicina: polja.kolicina,
        },
      });
      break;
    case "ORUZJE":
      await tx.oruzje.create({
        data: {
          idDokaz: dokazId,
          vrstaOruzja: polja.vrstaOruzja,
          marka: polja.marka,
          model: polja.model,
          kalibar: polja.kalibar,
          serijskiBr: polja.serijskiBr,
        },
      });
      break;
    case "DOKUMENT":
      await tx.dokumentDokaz.create({
        data: {
          idDokaz: dokazId,
          vrstaDokumenta: polja.vrstaDokumenta,
          jezik: polja.jezik,
          brojStranica: polja.brojStranica ? parseInt(polja.brojStranica, 10) : null,
        },
      });
      break;
    case "ODECA":
      await tx.odeca.create({
        data: {
          idDokaz: dokazId,
          velicina: polja.velicina,
          vrstaOdevnogPredmeta: polja.vrstaOdevnogPredmeta,
          boja: polja.boja,
          stanje: polja.stanje,
        },
      });
      break;
    case "UZORAK":
      await tx.uzorak.create({
        data: {
          idDokaz: dokazId,
          vrstaUzorka: polja.vrstaUzorka,
          kolicina: polja.kolicina,
          jedinicaMere: polja.jedinicaMere,
          nacinUzorkovanja: polja.nacinUzorkovanja,
          usloviCuvanja: polja.usloviCuvanja,
        },
      });
      break;
  }
}
