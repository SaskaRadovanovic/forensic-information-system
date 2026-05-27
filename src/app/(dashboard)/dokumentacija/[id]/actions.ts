"use server";

import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { savePdf, validatePdf, copyPdf } from "@/lib/uploads";
import { revalidatePath } from "next/cache";

type Result = { ok: true; id?: number } | { ok: false; greska: string };

// ─── SCRUM-41 + SCRUM-42: Izmena dokumenta sa snapshot-om ────────────────────

export async function izmeniDokument(formData: FormData): Promise<Result> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  const dokumentIdStr = formData.get("dokumentId") as string | null;
  const naziv = (formData.get("naziv") as string | null)?.trim();
  const predmetIdStr = formData.get("predmetId") as string | null;
  const tipDokumenta = formData.get("tipDokumenta") as string | null;
  const opis = formData.get("opis") as string | null;
  const razlogIzmene = formData.get("razlogIzmene") as string | null;
  const pdfFile = formData.get("pdf") as File | null;

  if (!naziv) return { ok: false, greska: "Naziv je obavezan." };
  if (!predmetIdStr) return { ok: false, greska: "Predmet je obavezan." };
  if (!tipDokumenta) return { ok: false, greska: "Tip dokumenta je obavezan." };

  const dokumentId = parseInt(dokumentIdStr ?? "", 10);
  const predmetId = parseInt(predmetIdStr, 10);
  if (isNaN(dokumentId) || isNaN(predmetId)) {
    return { ok: false, greska: "Nevažeći podaci." };
  }

  // Validacija i čuvanje novog PDF-a (opciono pri izmeni)
  let novaPutanja: string | null = null;
  if (pdfFile && pdfFile.size > 0) {
    const validacija = validatePdf(pdfFile);
    if (!validacija.ok) return { ok: false, greska: validacija.error };
    novaPutanja = await savePdf(pdfFile);
  }

  try {
    const trenutni = await prisma.dokument.findUnique({
      where: { id: dokumentId },
      select: { putanja: true, verzija: true, status: true },
    });
    if (!trenutni) return { ok: false, greska: "Dokument nije pronađen." };
    if (trenutni.status === "ARHIVIRAN") {
      return { ok: false, greska: "Arhivirani dokument se ne može izmeniti." };
    }

    const sacuvaoId = parseInt(session.user.id, 10);

    // SCRUM-42: Čuvamo snapshot stare verzije pre izmene
    const arhivskaPutanja = await copyPdf(trenutni.putanja, dokumentId);

    await prisma.$transaction(async (tx) => {
      // 1. Arhivska kopija stare verzije
      await tx.dokumentArhiva.create({
        data: {
          dokumentId,
          putanjaStaraVerzija: arhivskaPutanja,
          verzija: trenutni.verzija,
          razlogIzmene: razlogIzmene?.trim() || "Izmena dokumenta",
          sacuvaoId,
        },
      });

      // 2. Brišemo stare metapodatke (kreiraćemo nove)
      await tx.metapodatak.deleteMany({ where: { dokumentId } });

      // 3. Ažuriramo dokument + povećavamo verziju
      await tx.dokument.update({
        where: { id: dokumentId },
        data: {
          naziv,
          predmetId,
          verzija: { increment: 1 },
          ...(novaPutanja ? { putanja: novaPutanja } : {}),
        },
      });

      // 4. Kreiramo nove metapodatke
      await tx.metapodatak.createMany({
        data: [
          { dokumentId, kljuc: "tipDokumenta", vrednost: tipDokumenta },
          ...(opis?.trim()
            ? [{ dokumentId, kljuc: "opis", vrednost: opis.trim() }]
            : []),
        ],
      });
    });

    revalidatePath(`/dokumentacija/${dokumentId}`);
    revalidatePath("/dokumentacija");
    return { ok: true, id: dokumentId };
  } catch (error) {
    console.error("Greška pri izmeni dokumenta:", error);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── SCRUM-39 + SCRUM-40: Arhiviranje dokumenta ──────────────────────────────

export async function arhivirajDokument(dokumentId: number): Promise<Result> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  const sacuvaoId = parseInt(session.user.id, 10);

  try {
    const dok = await prisma.dokument.findUnique({
      where: { id: dokumentId },
      select: { putanja: true, verzija: true, status: true },
    });

    if (!dok) return { ok: false, greska: "Dokument nije pronađen." };
    if (dok.status === "ARHIVIRAN") {
      return { ok: false, greska: "Dokument je već arhiviran." };
    }

    // SCRUM-40: Kopiramo fajl na arhivsku lokaciju
    const arhivskaPutanja = await copyPdf(dok.putanja, dokumentId);

    await prisma.$transaction(async (tx) => {
      // Zapis u arhivu
      await tx.dokumentArhiva.create({
        data: {
          dokumentId,
          putanjaStaraVerzija: arhivskaPutanja,
          verzija: dok.verzija,
          razlogIzmene: "Arhiviranje dokumenta",
          sacuvaoId,
        },
      });

      // Status → ARHIVIRAN
      await tx.dokument.update({
        where: { id: dokumentId },
        data: { status: "ARHIVIRAN" },
      });
    });

    revalidatePath(`/dokumentacija/${dokumentId}`);
    revalidatePath("/dokumentacija");
    return { ok: true };
  } catch (error) {
    console.error("Greška pri arhiviranju:", error);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}
