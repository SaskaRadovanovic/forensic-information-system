import { prisma } from "@/lib/prisma";
import { NextResponse } from "next/server";

// ─── GET /api/cron/analize-rokovi — automatsko ažuriranje statusa po rokovima ─
// Ova ruta se poziva periodično (cron job) ili ručno
// Nije zaštićena autentifikacijom jer je namenjena za sistemsku automatizaciju

export async function GET() {
  const sada = new Date();

  // Nalazimo sistemskog korisnika (admin) za logovanje automatskih promena
  const admin = await prisma.korisnik.findFirst({ where: { uloga: "ADMINISTRATOR" } });
  if (!admin) {
    return NextResponse.json({ ok: false, greska: "Nije pronađen administrator za sistemsko logovanje." }, { status: 500 });
  }

  // ── Zahtevi kojima je rok istekao ─────────────────────────────────────────
  const prekoraceniZahtevi = await prisma.zahtevZaAnalizu.findMany({
    where: {
      rok: { lt: sada },
      status: { notIn: ["ZAVRSEN", "ODBIJEN", "PREKORACEN"] },
    },
    select: { id: true, status: true, istraziteljId: true, vestakId: true, pragUpozorenjaDana: true },
  });

  let azuriranoPrekoracenih = 0;

  for (const z of prekoraceniZahtevi) {
    await prisma.$transaction(async (tx) => {
      await tx.zahtevZaAnalizu.update({ where: { id: z.id }, data: { status: "PREKORACEN" } });
      await tx.istorijaStatusaAnalize.create({
        data: {
          zahtevId: z.id,
          stariStatus: z.status,
          noviStatus: "PREKORACEN",
          iniciraoId: admin.id,
          napomena: "Automatsko ažuriranje sistema — rok je istekao.",
        },
      });
      // Obaveštenje za istražitelja
      await tx.obavestenje.create({
        data: {
          korisnikId: z.istraziteljId,
          sadrzaj: `Rok za analizu #${z.id} je istekao. Status promenjen u Prekoračen.`,
          tip: "ROK_PREKORACEN",
          zahtevId: z.id,
        },
      });
      // Obaveštenje za veštaka (ako postoji)
      if (z.vestakId) {
        await tx.obavestenje.create({
          data: {
            korisnikId: z.vestakId,
            sadrzaj: `Rok za analizu #${z.id} je istekao!`,
            tip: "ROK_PREKORACEN",
            zahtevId: z.id,
          },
        });
      }
    });
    azuriranoPrekoracenih++;
  }

  // ── Zahtevi kojima rok uskoro ističe (upozorenje) ─────────────────────────
  const zahteviBliziRoka = await prisma.zahtevZaAnalizu.findMany({
    where: {
      rok: { not: null, gt: sada },
      status: { notIn: ["ZAVRSEN", "ODBIJEN", "PREKORACEN"] },
    },
    select: { id: true, rok: true, pragUpozorenjaDana: true, istraziteljId: true, vestakId: true },
  });

  let poslateUpozorenja = 0;

  for (const z of zahteviBliziRoka) {
    if (!z.rok) continue;
    const danaDo = Math.ceil((z.rok.getTime() - sada.getTime()) / (1000 * 60 * 60 * 24));

    if (danaDo <= z.pragUpozorenjaDana) {
      // Proveravamo da već nije poslato upozorenje danas
      const vecPoslato = await prisma.obavestenje.findFirst({
        where: {
          zahtevId: z.id,
          tip: "ROK_BLIZI",
          datumVreme: { gte: new Date(sada.getFullYear(), sada.getMonth(), sada.getDate()) },
        },
      });

      if (!vecPoslato) {
        // Obaveštenje za istražitelja
        await prisma.obavestenje.create({
          data: {
            korisnikId: z.istraziteljId,
            sadrzaj: `Rok za analizu #${z.id} ističe za ${danaDo} dan${danaDo === 1 ? "" : "a"}.`,
            tip: "ROK_BLIZI",
            zahtevId: z.id,
          },
        });
        // Obaveštenje za veštaka (ako postoji)
        if (z.vestakId) {
          await prisma.obavestenje.create({
            data: {
              korisnikId: z.vestakId,
              sadrzaj: `Rok za analizu #${z.id} ističe za ${danaDo} dan${danaDo === 1 ? "" : "a"}.`,
              tip: "ROK_BLIZI",
              zahtevId: z.id,
            },
          });
        }
        poslateUpozorenja++;
      }
    }
  }

  return NextResponse.json({
    ok: true,
    poruka: `Ažurirano: ${azuriranoPrekoracenih} prekoračenih, ${poslateUpozorenja} novih upozorenja.`,
    azuriranoPrekoracenih,
    poslateUpozorenja,
  });
}
