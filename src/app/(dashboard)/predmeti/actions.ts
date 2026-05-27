"use server";

// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { revalidatePath } from "next/cache";

// ─── Tipovi rezultata ────────────────────────────────────────────────────────

export type Rezultat = { ok: true } | { ok: false; greska: string };
export type KreirajRezultat = { ok: true; id: number } | { ok: false; greska: string };

// ─── Redosled faza istrage ───────────────────────────────────────────────────

const REDOSLED_FAZA = ["OTVOREN_SLUCAJ", "PRIKUPLJANJE_DOKAZA", "ANALIZA_DOKAZA", "DONOSENJE_ZAKLJUCKA", "ZATVOREN_SLUCAJ"] as const;

// ─── Kreiranje novog predmeta ────────────────────────────────────────────────

export async function kreirajPredmet(formData: FormData): Promise<KreirajRezultat> {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  // Provera autorizacije — ADMINISTRATOR i ISTRAZITELJ mogu kreirati predmete
  const uloga = session.user.uloga;
  if (uloga !== "ADMINISTRATOR" && uloga !== "ISTRAZITELJ") {
    return { ok: false, greska: "Nemate dozvolu za kreiranje predmeta." };
  }

  // Ekstrakcija i validacija polja
  const naziv = (formData.get("naziv") as string | null)?.trim();
  const opis = (formData.get("opis") as string | null)?.trim();

  if (!naziv || naziv.length < 2) {
    return { ok: false, greska: "Naziv predmeta mora imati najmanje 2 karaktera." };
  }

  try {
    const predmet = await prisma.predmet.create({
      data: {
        naziv,
        opis: opis || null,
      },
    });
    revalidatePath("/predmeti");
    return { ok: true, id: predmet.id };
  } catch {
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Izmena predmeta ─────────────────────────────────────────────────────────

export async function izmeniPredmet(formData: FormData): Promise<Rezultat> {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  // Provera autorizacije
  const uloga = session.user.uloga;
  if (uloga !== "ADMINISTRATOR" && uloga !== "ISTRAZITELJ") {
    return { ok: false, greska: "Nemate dozvolu za izmenu predmeta." };
  }

  // Ekstrakcija i validacija polja
  const predmetIdStr = formData.get("predmetId") as string | null;
  const naziv = (formData.get("naziv") as string | null)?.trim();
  const opis = (formData.get("opis") as string | null)?.trim();

  if (!predmetIdStr) return { ok: false, greska: "ID predmeta nije naveden." };
  const predmetId = parseInt(predmetIdStr, 10);
  if (isNaN(predmetId)) return { ok: false, greska: "Nevažeći ID predmeta." };
  if (!naziv || naziv.length < 2) {
    return { ok: false, greska: "Naziv predmeta mora imati najmanje 2 karaktera." };
  }

  try {
    // Proveravamo da predmet postoji
    const predmet = await prisma.predmet.findUnique({ where: { id: predmetId } });
    if (!predmet) return { ok: false, greska: "Predmet nije pronađen." };

    // Zatvoren predmet može menjati samo administrator
    if (predmet.status === "ZATVOREN" && uloga !== "ADMINISTRATOR") {
      return { ok: false, greska: "Zatvoren predmet može menjati samo administrator." };
    }

    await prisma.predmet.update({
      where: { id: predmetId },
      data: { naziv, opis: opis || null },
    });

    revalidatePath("/predmeti");
    revalidatePath(`/predmeti/${predmetId}`);
    return { ok: true };
  } catch {
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Brisanje predmeta ───────────────────────────────────────────────────────

export async function obrisiPredmet(predmetId: number): Promise<Rezultat> {
  // Samo ADMINISTRATOR može brisati predmete
  const session = await getServerSession(authOptions);
  if (!session || session.user.uloga !== "ADMINISTRATOR") {
    return { ok: false, greska: "Samo administrator može brisati predmete." };
  }

  try {
    // Proveravamo vezane zapise — predmet sa vezanim podacima ne može se brisati
    const predmet = await prisma.predmet.findUnique({
      where: { id: predmetId },
      include: {
        _count: {
          select: { dokazi: true, dokumenti: true, zahteviZaAnalizu: true },
        },
      },
    });

    if (!predmet) return { ok: false, greska: "Predmet nije pronađen." };

    const ukupnoVezanih =
      predmet._count.dokazi +
      predmet._count.dokumenti +
      predmet._count.zahteviZaAnalizu;

    if (ukupnoVezanih > 0) {
      return {
        ok: false,
        greska:
          `Predmet ima vezane zapise (${predmet._count.dokazi} dokaz(a), ` +
          `${predmet._count.dokumenti} dokument(a), ` +
          `${predmet._count.zahteviZaAnalizu} zahtev(a) za analizu) i ne može se brisati.`,
      };
    }

    await prisma.predmet.delete({ where: { id: predmetId } });
    revalidatePath("/predmeti");
    return { ok: true };
  } catch {
    return { ok: false, greska: "Greška pri brisanju predmeta." };
  }
}

// ─── Zatvaranje predmeta ─────────────────────────────────────────────────────

export async function zatvoriPredmet(predmetId: number): Promise<Rezultat> {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  // ADMINISTRATOR i ISTRAZITELJ mogu zatvarati predmete
  const uloga = session.user.uloga;
  if (uloga !== "ADMINISTRATOR" && uloga !== "ISTRAZITELJ") {
    return { ok: false, greska: "Nemate dozvolu za zatvaranje predmeta." };
  }

  try {
    const predmet = await prisma.predmet.findUnique({ where: { id: predmetId } });
    if (!predmet) return { ok: false, greska: "Predmet nije pronađen." };
    if (predmet.status === "ZATVOREN") {
      return { ok: false, greska: "Predmet je već zatvoren." };
    }

    await prisma.predmet.update({
      where: { id: predmetId },
      data: { status: "ZATVOREN" },
    });

    revalidatePath("/predmeti");
    revalidatePath(`/predmeti/${predmetId}`);
    return { ok: true };
  } catch {
    return { ok: false, greska: "Greška pri zatvaranju predmeta." };
  }
}

// ─── Prelaz na sledeću fazu predmeta ────────────────────────────────────────

export async function promeniPredmetFazu(predmetId: number): Promise<Rezultat> {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  // ADMINISTRATOR i ISTRAZITELJ mogu menjati faze
  const uloga = session.user.uloga;
  if (uloga !== "ADMINISTRATOR" && uloga !== "ISTRAZITELJ") {
    return { ok: false, greska: "Nemate dozvolu za promenu faze predmeta." };
  }

  try {
    const predmet = await prisma.predmet.findUnique({ where: { id: predmetId } });
    if (!predmet) return { ok: false, greska: "Predmet nije pronađen." };
    if (predmet.status === "ZATVOREN") {
      return { ok: false, greska: "Zatvoren predmet ne može menjati fazu." };
    }

    // Nalazimo sledeću fazu po redosledu
    const trenutniIndeks = REDOSLED_FAZA.indexOf(predmet.faza as typeof REDOSLED_FAZA[number]);
    if (trenutniIndeks === -1 || trenutniIndeks === REDOSLED_FAZA.length - 1) {
      return { ok: false, greska: "Predmet je već u poslednjoj fazi." };
    }

    const sledecaFaza = REDOSLED_FAZA[trenutniIndeks + 1];

    // Provera aktivnih analiza pre prelaska u fazu donošenja zaključka
    if (sledecaFaza === "DONOSENJE_ZAKLJUCKA") {
      const aktivneAnalize = await prisma.zahtevZaAnalizu.count({
        where: {
          predmetId,
          status: { in: ["KREIRAN", "DODELJEN", "U_TOKU"] },
        },
      });
      if (aktivneAnalize > 0) {
        return {
          ok: false,
          greska: `Predmet ima ${aktivneAnalize} aktivnih forenzičkih analiza. Završite ih pre prelaska na fazu donošenja zaključka.`,
        };
      }
    }

    await prisma.predmet.update({
      where: { id: predmetId },
      data: { faza: sledecaFaza },
    });

    revalidatePath("/predmeti");
    revalidatePath(`/predmeti/${predmetId}`);
    return { ok: true };
  } catch {
    return { ok: false, greska: "Greška pri promeni faze predmeta." };
  }
}
