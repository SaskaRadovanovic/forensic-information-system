"use server";

// ─── Uvoz zavisnosti ──────────────────────────────────────────────────────────
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { TipAnalize, StatusAnalize } from "@prisma/client";

// ─── Helperi (interne funkcije) ───────────────────────────────────────────────

// Loguje promenu statusa analize u istoriju
// eslint-disable-next-line @typescript-eslint/no-explicit-any
async function logujStatus(tx: any, zahtevId: number, stariStatus: StatusAnalize | null, noviStatus: StatusAnalize, iniciraoId: number, napomena?: string) {
  await tx.istorijaStatusaAnalize.create({
    data: { zahtevId, stariStatus, noviStatus, iniciraoId, napomena: napomena ?? null },
  });
}

// Kreira obaveštenje za korisnika
// eslint-disable-next-line @typescript-eslint/no-explicit-any
async function kreirajObavestenje(tx: any, korisnikId: number, sadrzaj: string, tip: string, zahtevId?: number) {
  await tx.obavestenje.create({
    data: { korisnikId, sadrzaj, tip, zahtevId: zahtevId ?? null },
  });
}

// ─── Tipovi povratnih vrednosti ───────────────────────────────────────────────

export type AkcijaBezPodataka = { ok: true } | { ok: false; greska: string };
export type AkcijaKreiraj = { ok: true; id: number } | { ok: false; greska: string };

// ─── Kreiranje zahteva za analizu ─────────────────────────────────────────────

export async function kreirajZahtev(formData: FormData): Promise<AkcijaKreiraj> {
  // Provera autentifikacije i autorizacije
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };
  if (session.user.uloga !== "ISTRAZITELJ")
    return { ok: false, greska: "Samo istražitelji mogu kreirati zahteve za analizu." };

  const korisnikId = parseInt(session.user.id, 10);

  // Proveravamo da li istražitelj ima profil
  const istraziteljProfil = await prisma.istrazitelj.findUnique({ where: { idKorisnik: korisnikId } });
  if (!istraziteljProfil)
    return { ok: false, greska: "Vaš profil istražitelja nije pronađen. Kontaktirajte administratora." };

  // Čitanje polja iz forme
  const predmetIdStr = formData.get("predmetId") as string;
  const dokazIdStr = formData.get("dokazId") as string;
  const tipAnalize = formData.get("tipAnalize") as TipAnalize;
  const opis = formData.get("opis") as string;
  const datumPocetkaStr = formData.get("datumPocetka") as string;
  const rokStr = formData.get("rok") as string;
  const pragStr = formData.get("pragUpozorenjaDana") as string;

  // Validacija obaveznih polja
  if (!predmetIdStr || !dokazIdStr || !tipAnalize || !rokStr)
    return { ok: false, greska: "Predmet, dokaz, tip analize i rok su obavezni." };

  const predmetId = parseInt(predmetIdStr, 10);
  const dokazId = parseInt(dokazIdStr, 10);
  if (isNaN(predmetId) || isNaN(dokazId))
    return { ok: false, greska: "Nevažeći ID predmeta ili dokaza." };

  const rok = new Date(rokStr);
  if (isNaN(rok.getTime())) return { ok: false, greska: "Nevažeći datum roka." };

  const pragUpozorenjaDana = pragStr ? parseInt(pragStr, 10) : 3;

  // Proveravamo da predmet postoji
  const predmet = await prisma.predmet.findUnique({ where: { id: predmetId } });
  if (!predmet) return { ok: false, greska: "Odabrani predmet nije pronađen." };

  // Proveravamo da dokaz postoji i pripada predmetu
  const dokaz = await prisma.dokaz.findUnique({ where: { id: dokazId } });
  if (!dokaz) return { ok: false, greska: "Odabrani dokaz nije pronađen." };
  if (dokaz.predmetId !== predmetId)
    return { ok: false, greska: "Odabrani dokaz ne pripada ovom predmetu." };

  // TODO [Integracija podsistema za analize]: Pre dozvole kreiranja zahteva, validirati da je
  // predmet u fazi ANALIZA_DOKAZA. Kada tim za predmete implementira polje `faza` na modelu
  // `Predmet`, dodati: if (predmet.faza !== 'ANALIZA_DOKAZA') return { ok: false, greska: "..." };
  // Za sada kreiranje je dozvoljeno bez obzira na fazu predmeta.

  try {
    const zahtev = await prisma.$transaction(async (tx) => {
      // Kreiranje zahteva
      const z = await tx.zahtevZaAnalizu.create({
        data: {
          predmetId,
          dokazId,
          tipAnalize,
          opis: opis?.trim() || null,
          datumPocetka: datumPocetkaStr ? new Date(datumPocetkaStr) : null,
          rok,
          pragUpozorenjaDana: isNaN(pragUpozorenjaDana) ? 3 : pragUpozorenjaDana,
          istraziteljId: korisnikId,
          status: "KREIRAN",
        },
      });
      // Početni unos u istoriju statusa
      await logujStatus(tx, z.id, null, "KREIRAN", korisnikId);
      return z;
    });
    return { ok: true, id: zahtev.id };
  } catch (e) {
    console.error("Greška pri kreiranju zahteva za analizu:", e);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Izmena zahteva za analizu ────────────────────────────────────────────────

export async function izmeniZahtev(formData: FormData): Promise<AkcijaBezPodataka> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };
  if (session.user.uloga !== "ISTRAZITELJ" && session.user.uloga !== "ADMINISTRATOR")
    return { ok: false, greska: "Nemate ovlašćenje za izmenu zahteva." };

  const zahtevId = parseInt(formData.get("zahtevId") as string, 10);
  if (isNaN(zahtevId)) return { ok: false, greska: "Nevažeći ID zahteva." };

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) return { ok: false, greska: "Zahtev nije pronađen." };

  // Izmena je dozvoljena samo ako analiza nije još počela
  if (zahtev.status !== "KREIRAN" && zahtev.status !== "DODELJEN")
    return { ok: false, greska: "Zahtev se može izmeniti samo dok analiza nije započeta." };

  const tipAnalize = formData.get("tipAnalize") as TipAnalize;
  const rokStr = formData.get("rok") as string;
  if (!tipAnalize || !rokStr) return { ok: false, greska: "Tip analize i rok su obavezni." };

  const rok = new Date(rokStr);
  if (isNaN(rok.getTime())) return { ok: false, greska: "Nevažeći datum roka." };

  const opis = formData.get("opis") as string;
  const datumPocetkaStr = formData.get("datumPocetka") as string;
  const pragStr = formData.get("pragUpozorenjaDana") as string;

  const novaPrag = pragStr ? parseInt(pragStr, 10) : zahtev.pragUpozorenjaDana;
  const noviOpis = opis?.trim() || null;
  const noviDatumPocetka = datumPocetkaStr ? new Date(datumPocetkaStr) : null;

  // ── Prikupljanje promena polje-po-polje ───────────────────────────────────
  type Promena = { polje: string; staraVrednost: string | null; novaVrednost: string };
  const promene: Promena[] = [];

  if (zahtev.tipAnalize !== tipAnalize) {
    promene.push({ polje: "tipAnalize", staraVrednost: zahtev.tipAnalize, novaVrednost: tipAnalize });
  }
  if ((zahtev.opis ?? null) !== noviOpis) {
    promene.push({ polje: "opis", staraVrednost: zahtev.opis ?? null, novaVrednost: noviOpis ?? "" });
  }
  const staraDP = zahtev.datumPocetka ? zahtev.datumPocetka.toISOString() : null;
  const novaDP  = noviDatumPocetka    ? noviDatumPocetka.toISOString()    : null;
  if (staraDP !== novaDP) {
    promene.push({ polje: "datumPocetka", staraVrednost: staraDP, novaVrednost: novaDP ?? "" });
  }
  const staraRok = zahtev.rok ? zahtev.rok.toISOString() : null;
  const noviRok  = rok.toISOString();
  if (staraRok !== noviRok) {
    promene.push({ polje: "rok", staraVrednost: staraRok, novaVrednost: noviRok });
  }
  if (zahtev.pragUpozorenjaDana !== novaPrag) {
    promene.push({ polje: "pragUpozorenjaDana", staraVrednost: String(zahtev.pragUpozorenjaDana), novaVrednost: String(novaPrag) });
  }

  // Nema promena — vrati ok bez pisanja u bazu
  if (promene.length === 0) return { ok: true };

  try {
    await prisma.$transaction(async (tx) => {
      await tx.zahtevZaAnalizu.update({
        where: { id: zahtevId },
        data: {
          tipAnalize,
          opis: noviOpis,
          datumPocetka: noviDatumPocetka,
          rok,
          pragUpozorenjaDana: novaPrag,
        },
      });
      await tx.istorijaIzmeneZahteva.createMany({
        data: promene.map((p) => ({
          zahtevId,
          korisnikId: parseInt(session.user.id, 10),
          polje: p.polje,
          staraVrednost: p.staraVrednost,
          novaVrednost: p.novaVrednost,
        })),
      });
    });
    return { ok: true };
  } catch (e) {
    console.error("Greška pri izmeni zahteva:", e);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Brisanje zahteva za analizu ──────────────────────────────────────────────

export async function obrisiZahtev(zahtevId: number, razlog: string): Promise<AkcijaBezPodataka> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };
  if (session.user.uloga !== "ADMINISTRATOR")
    return { ok: false, greska: "Samo administrator može brisati zahteve za analizu." };
  if (!razlog?.trim()) return { ok: false, greska: "Razlog brisanja je obavezan." };

  const korisnikId = parseInt(session.user.id, 10);

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) return { ok: false, greska: "Zahtev nije pronađen." };
  if (zahtev.status === "U_TOKU" || zahtev.status === "ZAVRSEN")
    return { ok: false, greska: "Nije moguće obrisati zahtev koji je u toku ili završen." };

  try {
    await prisma.$transaction(async (tx) => {
      // Čuvamo log brisanja (zahtevId je samo vrednost, ne FK — zahtev će biti obrisan)
      await tx.logBrisanjaZahteva.create({
        data: { zahtevId, razlog: razlog.trim(), obrisaoId: korisnikId },
      });
      // Brisanje zahteva (cascade briše IstorijaDodele i IstorijaStatusa)
      await tx.zahtevZaAnalizu.delete({ where: { id: zahtevId } });
    });
    return { ok: true };
  } catch (e) {
    console.error("Greška pri brisanju zahteva:", e);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Dodela / preraspodela veštaka ────────────────────────────────────────────

export async function dodeliVestaka(zahtevId: number, vestakKorisnikId: number, razlog?: string): Promise<AkcijaBezPodataka> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };
  if (session.user.uloga !== "ISTRAZITELJ" && session.user.uloga !== "ADMINISTRATOR")
    return { ok: false, greska: "Samo istražitelji i administratori mogu dodeljivati veštake." };

  const korisnikId = parseInt(session.user.id, 10);

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) return { ok: false, greska: "Zahtev nije pronađen." };
  if (zahtev.status === "U_TOKU" || zahtev.status === "ZAVRSEN" || zahtev.status === "ODBIJEN")
    return { ok: false, greska: "Veštak se ne može dodeliti u ovom statusu analize." };

  const vestakProfil = await prisma.vestak.findUnique({ where: { idKorisnik: vestakKorisnikId } });
  if (!vestakProfil) return { ok: false, greska: "Odabrani veštak nije pronađen u sistemu." };

  const jePrerasporedba = zahtev.vestakId !== null;
  if (jePrerasporedba && !razlog?.trim())
    return { ok: false, greska: "Razlog preraspodele je obavezan." };

  try {
    await prisma.$transaction(async (tx) => {
      // Ažuriranje zahteva sa novim veštakom i statusom DODELJEN
      await tx.zahtevZaAnalizu.update({
        where: { id: zahtevId },
        data: { vestakId: vestakKorisnikId, status: "DODELJEN" },
      });

      // Beleška o dodeli u istoriji dodela
      await tx.istorijaDodele.create({
        data: {
          zahtevId,
          vestakId: vestakKorisnikId,
          dodeliooId: korisnikId,
          razlogPromene: jePrerasporedba ? razlog!.trim() : null,
        },
      });

      // Beleška u istoriji statusa (samo ako se status menja)
      if (zahtev.status !== "DODELJEN") {
        await logujStatus(tx, zahtevId, zahtev.status, "DODELJEN", korisnikId);
      }

      // Obaveštenje za novog veštaka
      await kreirajObavestenje(
        tx,
        vestakKorisnikId,
        jePrerasporedba
          ? `Preraspoređeni ste na zahtev za analizu #${zahtevId}.`
          : `Dodeljeni ste kao veštak na zahtev za analizu #${zahtevId}.`,
        jePrerasporedba ? "PRERASPOREDJEN" : "DODELJEN",
        zahtevId
      );

      // Obaveštenje za prethodnog veštaka (ako je preraspodela)
      if (jePrerasporedba && zahtev.vestakId && zahtev.vestakId !== vestakKorisnikId) {
        await kreirajObavestenje(
          tx,
          zahtev.vestakId,
          `Uklonjeni ste sa zahteva za analizu #${zahtevId}. Razlog: ${razlog?.trim()}.`,
          "PRERASPOREDJEN",
          zahtevId
        );
      }
    });
    return { ok: true };
  } catch (e) {
    console.error("Greška pri dodeli veštaka:", e);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Pokretanje analize (DODELJEN → U_TOKU) ──────────────────────────────────

export async function zapocniAnalizu(zahtevId: number): Promise<AkcijaBezPodataka> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };
  if (session.user.uloga !== "VESTAK")
    return { ok: false, greska: "Samo veštak može pokrenuti analizu." };

  const korisnikId = parseInt(session.user.id, 10);

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) return { ok: false, greska: "Zahtev nije pronađen." };
  if (zahtev.vestakId !== korisnikId)
    return { ok: false, greska: "Niste dodeljeni veštak za ovu analizu." };
  if (zahtev.status !== "DODELJEN")
    return { ok: false, greska: "Analiza mora biti u statusu 'Dodeljen' da bi mogla da počne." };

  try {
    await prisma.$transaction(async (tx) => {
      await tx.zahtevZaAnalizu.update({ where: { id: zahtevId }, data: { status: "U_TOKU", datumPocetka: new Date() } });
      await logujStatus(tx, zahtevId, "DODELJEN", "U_TOKU", korisnikId);
      // Obaveštenje za istražitelja
      await kreirajObavestenje(tx, zahtev.istraziteljId, `Veštak je pokrenuo analizu #${zahtevId}.`, "ZAPOCETA", zahtevId);
    });
    return { ok: true };
  } catch (e) {
    console.error("Greška pri pokretanju analize:", e);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Odbijanje zahteva ────────────────────────────────────────────────────────

export async function odbijiZahtev(zahtevId: number, napomena?: string): Promise<AkcijaBezPodataka> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };
  if (session.user.uloga !== "ADMINISTRATOR" && session.user.uloga !== "ISTRAZITELJ")
    return { ok: false, greska: "Nemate ovlašćenje za odbijanje zahteva." };

  const korisnikId = parseInt(session.user.id, 10);

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({ where: { id: zahtevId } });
  if (!zahtev) return { ok: false, greska: "Zahtev nije pronađen." };
  if (zahtev.status === "ZAVRSEN" || zahtev.status === "ODBIJEN")
    return { ok: false, greska: "Ovaj zahtev ne može biti odbijen." };

  try {
    await prisma.$transaction(async (tx) => {
      await tx.zahtevZaAnalizu.update({ where: { id: zahtevId }, data: { status: "ODBIJEN" } });
      await logujStatus(tx, zahtevId, zahtev.status, "ODBIJEN", korisnikId, napomena);
      // Obaveštenje za veštaka ako je bio dodeljen
      if (zahtev.vestakId) {
        await kreirajObavestenje(tx, zahtev.vestakId, `Zahtev za analizu #${zahtevId} je odbijen.`, "ODBIJEN", zahtevId);
      }
    });
    return { ok: true };
  } catch (e) {
    console.error("Greška pri odbijanju zahteva:", e);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Unos rezultata analize ───────────────────────────────────────────────────

export async function unesRezultat(formData: FormData): Promise<AkcijaBezPodataka> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };
  if (session.user.uloga !== "VESTAK")
    return { ok: false, greska: "Samo veštak može unositi rezultate analize." };

  const korisnikId = parseInt(session.user.id, 10);
  const zahtevId = parseInt(formData.get("zahtevId") as string, 10);
  const sadrzaj = formData.get("sadrzaj") as string;

  if (isNaN(zahtevId)) return { ok: false, greska: "Nevažeći ID zahteva." };
  if (!sadrzaj?.trim()) return { ok: false, greska: "Sadržaj rezultata je obavezan." };

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({
    where: { id: zahtevId },
    include: { rezultat: true },
  });
  if (!zahtev) return { ok: false, greska: "Zahtev nije pronađen." };
  if (zahtev.vestakId !== korisnikId) return { ok: false, greska: "Niste dodeljeni veštak za ovu analizu." };
  if (zahtev.status !== "U_TOKU") return { ok: false, greska: "Rezultat se može uneti samo dok je analiza u toku." };
  if (zahtev.rezultat) return { ok: false, greska: "Rezultat je već unet za ovu analizu." };

  try {
    await prisma.$transaction(async (tx) => {
      await tx.rezultatAnalize.create({
        data: { zahtevId, sadrzaj: sadrzaj.trim(), uneaoId: korisnikId },
      });
      // Obaveštenje za istražitelja da unese verifikaciju
      await kreirajObavestenje(
        tx,
        zahtev.istraziteljId,
        `Veštak je uneo rezultate za analizu #${zahtevId}. Čeka se vaša verifikacija.`,
        "REZULTAT_UNET",
        zahtevId
      );
    });
    return { ok: true };
  } catch (e) {
    console.error("Greška pri unosu rezultata:", e);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Verifikacija rezultata (U_TOKU → ZAVRSEN) ───────────────────────────────

export async function verifikujRezultat(zahtevId: number): Promise<AkcijaBezPodataka> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };
  if (session.user.uloga !== "ISTRAZITELJ" && session.user.uloga !== "ADMINISTRATOR")
    return { ok: false, greska: "Samo istražitelji mogu verifikovati rezultate." };

  const korisnikId = parseInt(session.user.id, 10);

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({
    where: { id: zahtevId },
    include: { rezultat: true },
  });
  if (!zahtev) return { ok: false, greska: "Zahtev nije pronađen." };
  if (!zahtev.rezultat) return { ok: false, greska: "Rezultat još nije unet." };
  if (zahtev.rezultat.verifikovan) return { ok: false, greska: "Rezultat je već verifikovan." };

  try {
    await prisma.$transaction(async (tx) => {
      // Označavamo rezultat kao verifikovan
      await tx.rezultatAnalize.update({ where: { zahtevId }, data: { verifikovan: true } });
      // Menjamo status analize na ZAVRSEN
      await tx.zahtevZaAnalizu.update({ where: { id: zahtevId }, data: { status: "ZAVRSEN" } });
      await logujStatus(tx, zahtevId, zahtev.status, "ZAVRSEN", korisnikId, "Rezultat verifikovan od strane istražitelja.");
      // Obaveštenje za veštaka
      if (zahtev.vestakId) {
        await kreirajObavestenje(
          tx, zahtev.vestakId,
          `Vaši rezultati za analizu #${zahtevId} su verifikovani. Analiza je uspešno završena.`,
          "VERIFIKOVAN",
          zahtevId
        );
      }
    });
    return { ok: true };
  } catch (e) {
    console.error("Greška pri verifikaciji rezultata:", e);
    return { ok: false, greska: "Greška na serveru. Pokušajte ponovo." };
  }
}

// ─── Označavanje obaveštenja kao pročitanog ───────────────────────────────────

export async function oznaciProitano(obavestenjeId: number): Promise<AkcijaBezPodataka> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  const korisnikId = parseInt(session.user.id, 10);
  const ob = await prisma.obavestenje.findUnique({ where: { id: obavestenjeId } });
  if (!ob) return { ok: false, greska: "Obaveštenje nije pronađeno." };
  if (ob.korisnikId !== korisnikId) return { ok: false, greska: "Niste vlasnik ovog obaveštenja." };

  try {
    await prisma.obavestenje.update({ where: { id: obavestenjeId }, data: { procitano: true } });
    return { ok: true };
  } catch {
    return { ok: false, greska: "Greška na serveru." };
  }
}

// ─── Označavanje svih obaveštenja kao pročitanih ─────────────────────────────

export async function oznaciSvaProitana(): Promise<AkcijaBezPodataka> {
  const session = await getServerSession(authOptions);
  if (!session) return { ok: false, greska: "Niste prijavljeni." };

  const korisnikId = parseInt(session.user.id, 10);
  try {
    await prisma.obavestenje.updateMany({
      where: { korisnikId, procitano: false },
      data: { procitano: true },
    });
    return { ok: true };
  } catch {
    return { ok: false, greska: "Greška na serveru." };
  }
}
