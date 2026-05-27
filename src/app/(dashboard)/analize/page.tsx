// ─── Lista zahteva za analizu sa filterima ────────────────────────────────────
import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Plus, FlaskConical, Eye } from "lucide-react";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/components/ui/table";
import { StatusBadge, tipAnalizeNaziv } from "@/components/analize/StatusBadge";

export const metadata = { title: "Analize — FIS" };

interface SearchParams {
  predmetId?: string;
  vestakId?: string;
  tipAnalize?: string;
  status?: string;
  rokOd?: string;
  rokDo?: string;
}

// ─── Automatsko ažuriranje statusa po rokovima (poziva se pri svakom GET-u) ──
async function azurirajRokove(adminId: number) {
  const sada = new Date();
  const zahtevi = await prisma.zahtevZaAnalizu.findMany({
    where: { rok: { not: null, lt: sada }, status: { notIn: ["ZAVRSEN", "ODBIJEN", "PREKORACEN"] } },
    select: { id: true, status: true, istraziteljId: true },
  });
  for (const z of zahtevi) {
    await prisma.$transaction(async (tx) => {
      await tx.zahtevZaAnalizu.update({ where: { id: z.id }, data: { status: "PREKORACEN" } });
      await tx.istorijaStatusaAnalize.create({
        data: { zahtevId: z.id, stariStatus: z.status, noviStatus: "PREKORACEN", iniciraoId: adminId, napomena: "Automatsko ažuriranje sistema — rok je istekao." },
      });
    });
  }
}

export default async function AnalizePage({ searchParams }: { searchParams: Promise<SearchParams> }) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const params = await searchParams;

  // Automatsko ažuriranje rokova
  const admin = await prisma.korisnik.findFirst({ where: { uloga: "ADMINISTRATOR" } });
  if (admin) await azurirajRokove(admin.id);

  // Provera role za prikaz dugmeta "Novi zahtev"
  const mozeKreirati = session.user.uloga === "ISTRAZITELJ";
  const korisnikId = parseInt(session.user.id, 10);

  // ── Fetch zahteva sa filterima ────────────────────────────────────────────
  const zahtevi = await prisma.zahtevZaAnalizu.findMany({
    where: {
      ...(params.predmetId ? { predmetId: parseInt(params.predmetId) } : {}),
      ...(params.vestakId  ? { vestakId:  parseInt(params.vestakId)  } : {}),
      ...(params.tipAnalize ? { tipAnalize: params.tipAnalize as any } : {}),
      ...(params.status    ? { status:    params.status as any       } : {}),
      ...(params.rokOd || params.rokDo ? {
        rok: {
          ...(params.rokOd ? { gte: new Date(params.rokOd) } : {}),
          ...(params.rokDo ? { lte: new Date(params.rokDo) } : {}),
        },
      } : {}),
      // Veštak vidi samo svoje zahteve
      ...(session.user.uloga === "VESTAK" ? { vestakId: korisnikId } : {}),
    },
    include: {
      predmet:  { select: { naziv: true } },
      dokaz:    { select: { naziv: true, sifraDokaza: true } },
      vestak:   { include: { korisnik: { select: { ime: true, prezime: true } } } },
    },
    orderBy: { datumKreiranja: "desc" },
  });

  // ── Podaci za filtere ─────────────────────────────────────────────────────
  const predmeti = await prisma.predmet.findMany({ select: { id: true, naziv: true }, orderBy: { naziv: "asc" } });
  const vestaci  = await prisma.vestak.findMany({ include: { korisnik: { select: { ime: true, prezime: true } } } });

  const tipovi = ["BALISTICKA","DNK","DIGITALNA","HEMIJSKA","TOKSIKOLOSKA","DOKUMENTOLOSKA","DRUGA"];
  const statusi = ["KREIRAN","DODELJEN","U_TOKU","ZAVRSEN","PREKORACEN","ODBIJEN"];

  function selectKlasa() {
    return "h-9 rounded-lg border border-fis-surface3 bg-fis-surface2 text-fis-text1 px-2.5 py-1 text-sm focus:outline-none focus:border-fis-yellow [&>option]:bg-fis-surface2";
  }

  return (
    <div className="space-y-6">

      {/* ── Header ────────────────────────────────────────────────────────── */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-fis-text1">Analize</h1>
          <p className="text-sm text-fis-text2 mt-1">
            {zahtevi.length === 0 ? "Nema zahteva" : `${zahtevi.length} zahtev${zahtevi.length === 1 ? "" : "a"}`}
          </p>
        </div>
        {mozeKreirati && (
          <Link href="/analize/novi">
            <Button>
              <Plus className="h-4 w-4 mr-2" />
              Novi zahtev
            </Button>
          </Link>
        )}
      </div>

      {/* ── Filteri ───────────────────────────────────────────────────────── */}
      <form method="GET" className="flex flex-wrap gap-3">

        {/* Filter: predmet */}
        <select name="predmetId" defaultValue={params.predmetId ?? ""} className={selectKlasa()}>
          <option value="">Svi predmeti</option>
          {predmeti.map((p) => <option key={p.id} value={String(p.id)}>{p.naziv}</option>)}
        </select>

        {/* Filter: veštak — skriveno za veštake */}
        {session.user.uloga !== "VESTAK" && (
          <select name="vestakId" defaultValue={params.vestakId ?? ""} className={selectKlasa()}>
            <option value="">Svi veštaci</option>
            {vestaci.map((v) => (
              <option key={v.idKorisnik} value={String(v.idKorisnik)}>
                {v.korisnik.ime} {v.korisnik.prezime}
              </option>
            ))}
          </select>
        )}

        {/* Filter: tip analize */}
        <select name="tipAnalize" defaultValue={params.tipAnalize ?? ""} className={selectKlasa()}>
          <option value="">Svi tipovi</option>
          {tipovi.map((t) => <option key={t} value={t}>{tipAnalizeNaziv(t)}</option>)}
        </select>

        {/* Filter: status */}
        <select name="status" defaultValue={params.status ?? ""} className={selectKlasa()}>
          <option value="">Svi statusi</option>
          {statusi.map((s) => <option key={s} value={s}>{s.replace("_", " ")}</option>)}
        </select>

        {/* Filter: rok od - do */}
        <input type="date" name="rokOd" defaultValue={params.rokOd ?? ""} className={`${selectKlasa()} w-36`} placeholder="Rok od" />
        <input type="date" name="rokDo" defaultValue={params.rokDo ?? ""} className={`${selectKlasa()} w-36`} placeholder="Rok do" />

        <Button type="submit" variant="outline" size="sm" className="h-9">Filtriraj</Button>
        <Link href="/analize">
          <Button type="button" variant="ghost" size="sm" className="h-9">Resetuj</Button>
        </Link>
      </form>

      {/* ── Tabela ili prazno stanje ──────────────────────────────────────── */}
      {zahtevi.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-fis-surface3 bg-fis-surface py-20 text-center">
          <FlaskConical className="h-12 w-12 text-fis-text3 mb-4" />
          <h3 className="text-lg font-medium text-fis-text2">Nema zahteva za analizu</h3>
          <p className="text-sm text-fis-text3 mt-1">
            {Object.values(params).some(Boolean)
              ? "Nema rezultata za zadate filtere."
              : "Kreirajte prvi zahtev klikom na dugme iznad."}
          </p>
        </div>
      ) : (
        <div className="rounded-xl border border-fis-surface3 bg-fis-surface overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-fis-surface2 hover:bg-fis-surface2 border-b border-fis-surface3">
                <TableHead className="w-12">#</TableHead>
                <TableHead>Predmet / Dokaz</TableHead>
                <TableHead>Tip</TableHead>
                <TableHead>Veštak</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Rok</TableHead>
                <TableHead className="w-12" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {zahtevi.map((z) => {
                const rokProslost = z.rok && z.rok < new Date();
                return (
                  <TableRow key={z.id} className="border-b border-fis-surface3 hover:bg-fis-surface2">
                    <TableCell className="text-fis-text3 text-sm font-mono">#{z.id}</TableCell>
                    <TableCell>
                      <p className="font-medium text-fis-text1 text-sm">{z.predmet.naziv}</p>
                      <p className="text-xs text-fis-text3">{z.dokaz.sifraDokaza} — {z.dokaz.naziv}</p>
                    </TableCell>
                    <TableCell className="text-sm text-fis-text2">{tipAnalizeNaziv(z.tipAnalize)}</TableCell>
                    <TableCell className="text-sm text-fis-text2">
                      {z.vestak
                        ? `${z.vestak.korisnik.ime} ${z.vestak.korisnik.prezime}`
                        : <span className="text-fis-text3 italic">Nije dodeljeno</span>}
                    </TableCell>
                    <TableCell><StatusBadge status={z.status} /></TableCell>
                    <TableCell className={`text-sm ${rokProslost && z.status !== "ZAVRSEN" && z.status !== "ODBIJEN" ? "text-fis-red font-medium" : "text-fis-text2"}`}>
                      {z.rok ? new Date(z.rok).toLocaleDateString("sr-RS") : "—"}
                    </TableCell>
                    <TableCell>
                      <Link href={`/analize/${z.id}`}>
                        <Button variant="ghost" size="sm" className="h-7 w-7 p-0">
                          <Eye className="h-4 w-4" />
                        </Button>
                      </Link>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  );
}
