"use server";

import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { savePdf, validatePdf } from "@/lib/uploads";

export type KreirajDokumentResult =
  | { ok: true; id: number }
  | { ok: false; greska: string };

export async function kreirajDokument(
  formData: FormData
): Promise<KreirajDokumentResult> {
  // 1. Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) {
    return { ok: false, greska: "Niste prijavljeni." };
  }

  // 2. Ekstrakcija vrednosti iz forme
  const naziv = formData.get("naziv") as string | null;
  const predmetIdStr = formData.get("predmetId") as string | null;
  const tipDokumenta = formData.get("tipDokumenta") as string | null;
  const opis = formData.get("opis") as string | null;
  const pdfFile = formData.get("pdf") as File | null;

  // 3. Validacija obaveznih polja
  if (!naziv?.trim()) {
    return { ok: false, greska: "Naziv dokumenta je obavezan." };
  }
  if (!predmetIdStr) {
    return { ok: false, greska: "Predmet je obavezan." };
  }
  if (!tipDokumenta) {
    return { ok: false, greska: "Tip dokumenta je obavezan." };
  }
  if (!pdfFile || pdfFile.size === 0) {
    return { ok: false, greska: "PDF fajl je obavezan." };
  }

  // 4. Validacija PDF fajla
  const validacija = validatePdf(pdfFile);
  if (!validacija.ok) {
    return { ok: false, greska: validacija.error };
  }

  const predmetId = parseInt(predmetIdStr, 10);
  if (isNaN(predmetId)) {
    return { ok: false, greska: "Nevažeći predmet." };
  }

  const autorId = parseInt(session.user.id, 10);

  try {
    // 5. Čuvanje PDF fajla na disk
    const putanja = await savePdf(pdfFile);

    // 6. Kreiranje dokumenta i metapodataka u jednoj transakciji
    const dokument = await prisma.dokument.create({
      data: {
        naziv: naziv.trim(),
        putanja,
        predmetId,
        autorId,
        metapodaci: {
          create: [
            { kljuc: "tipDokumenta", vrednost: tipDokumenta },
            ...(opis?.trim()
              ? [{ kljuc: "opis", vrednost: opis.trim() }]
              : []),
          ],
        },
      },
    });

    return { ok: true, id: dokument.id };
  } catch (error) {
    console.error("Greška pri kreiranju dokumenta:", error);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}
