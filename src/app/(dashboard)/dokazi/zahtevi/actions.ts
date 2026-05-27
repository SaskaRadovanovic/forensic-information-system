"use server";

import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { revalidatePath } from "next/cache";

// ─── Tipovi za rezultat ─────────────────────────────────────────────────────

type Result = { ok: true } | { ok: false; greska: string };

// ─── Server action: obrada zahteva (odobrenje ili odbijanje) ────────────────

export async function obradiZahtev(
  zahtevId: number,
  odluka: "ODOBREN" | "ODBIJEN",
  napomena?: string
): Promise<Result> {
  // 1. Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  // 2. Provera autorizacije — samo TEHNICAR i ADMINISTRATOR
  const uloga = session.user.uloga;
  if (uloga !== "TEHNICAR" && uloga !== "ADMINISTRATOR") {
    return { ok: false, greska: "Nemate dozvolu za obradu zahteva." };
  }

  // 3. Pronalaženje tehničar profila
  const korisnikId = parseInt(session.user.id, 10);
  const tehnicarProfil = await prisma.tehnicarZaDokaze.findUnique({
    where: { idKorisnik: korisnikId },
  });
  if (!tehnicarProfil) {
    return { ok: false, greska: "Korisnik nema tehničar profil." };
  }

  try {
    // 4. Fetch zahteva
    const zahtev = await prisma.zahtevZaDokaz.findUnique({
      where: { id: zahtevId },
      include: { dokaz: { select: { id: true, sifraDokaza: true, status: true } } },
    });
    if (!zahtev) return { ok: false, greska: "Zahtev nije pronađen." };
    if (zahtev.status !== "NA_CEKANJU") {
      return { ok: false, greska: "Zahtev je već obrađen." };
    }

    // 5. Obrada u transakciji
    await prisma.$transaction(async (tx) => {
      // 5a. Ažuriranje statusa zahteva
      await tx.zahtevZaDokaz.update({
        where: { id: zahtevId },
        data: {
          status: odluka,
          datumObrade: new Date(),
          tehnicarId: korisnikId,
          napomena: napomena?.trim() || null,
        },
      });

      // 5b. Ako je odobren, menjamo status dokaza i pišemo lanac čuvanja
      if (odluka === "ODOBREN") {
        const noviStatus = zahtev.tip === "PREDAJA" ? "IZDATO_ZA_ANALIZU" : "VRACENO";
        const akcija = zahtev.tip === "PREDAJA" ? "Izdavanje dokaza" : "Povraćaj dokaza";

        await tx.dokaz.update({
          where: { id: zahtev.dokazId },
          data: { status: noviStatus as any },
        });

        await tx.lanacCuvanja.create({
          data: {
            dokazId: zahtev.dokazId,
            tehnicarId: korisnikId,
            akcija,
            datumVreme: new Date(),
            napomena: napomena?.trim() || `Zahtev #${zahtevId} odobren`,
          },
        });
      }
    });

    // 6. Revalidacija
    revalidatePath("/dokazi/zahtevi");
    revalidatePath(`/dokazi/${zahtev.dokazId}`);
    revalidatePath("/dokazi");
    return { ok: true };
  } catch (error) {
    console.error("Greška pri obradi zahteva:", error);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}
