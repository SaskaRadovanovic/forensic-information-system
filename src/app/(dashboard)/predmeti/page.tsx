// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Plus, FolderOpen, Eye } from "lucide-react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

// ─── Metadata stranice ───────────────────────────────────────────────────────

export const metadata = { title: "Predmeti — FIS" };

// ─── Helper: srpski naziv faze istrage ───────────────────────────────────────

function fazeLabel(faza: string): string {
  const mapa: Record<string, string> = {
    ISTRAGA: "Istraga",
    PRIKUPLJANJE_DOKAZA: "Prikupljanje dokaza",
    SUDJENJE: "Suđenje",
  };
  return mapa[faza] ?? faza;
}

// ─── Helper: CSS klasa za badge faze ────────────────────────────────────────

function fazaBadgeKlasa(faza: string): string {
  const mapa: Record<string, string> = {
    ISTRAGA: "bg-fis-blue/10 text-fis-blue border-0",
    PRIKUPLJANJE_DOKAZA: "bg-fis-yellow/10 text-fis-yellow border-0",
    SUDJENJE: "bg-fis-orange/10 text-fis-orange border-0",
  };
  return mapa[faza] ?? "bg-fis-surface3 text-fis-text2 border-0";
}

// ─── Helper: CSS klasa za badge statusa predmeta ─────────────────────────────

function statusBadgeKlasa(status: string): string {
  if (status === "ZATVOREN") return "bg-fis-red/10 text-fis-red border-0";
  return "bg-fis-green/10 text-fis-green border-0";
}

// ─── Stranica za prikaz liste predmeta ──────────────────────────────────────

// TODO [TIM-PREDMETI → TIM-ANALIZE]: Koordinacija — pred modul sada ima fazu predmeta.
// Modul za dokaze treba proveriti da je predmet u fazi PRIKUPLJANJE_DOKAZA pre unosa dokaza.
// Modul za analize: provera aktivnih analiza implementirana u akciji promeniPredmetFazu.

export default async function PredmetiPage({
  searchParams,
}: {
  searchParams: Promise<{ status?: string; faza?: string }>;
}) {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const params = await searchParams;

  // Proveravamo ulogu za prikazivanje dugmeta "Novi predmet"
  const uloga = session.user.uloga;
  const mozeKreirati = uloga === "ADMINISTRATOR" || uloga === "ISTRAZITELJ";

  // ── Fetch predmeta sa filterima ───────────────────────────────────────────
  const predmeti = await prisma.predmet.findMany({
    where: {
      ...(params.status ? { status: params.status as any } : {}),
      ...(params.faza ? { faza: params.faza as any } : {}),
    },
    include: {
      _count: {
        select: { dokazi: true, dokumenti: true, zahteviZaAnalizu: true },
      },
    },
    orderBy: { datumOtvaranja: "desc" },
  });

  return (
    <div className="space-y-6">

      {/* ── Header sa naslovom i dugmetom za kreiranje ───────────────────── */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-fis-text1">Predmeti</h1>
          <p className="mt-0.5 text-sm text-fis-text2">
            Upravljanje forenzičkim predmetima istrage
          </p>
        </div>
        {mozeKreirati && (
          <Link href="/predmeti/novi">
            <Button className="gap-2 bg-fis-yellow text-black font-semibold hover:bg-fis-yellow-dim">
              <Plus className="h-4 w-4" />
              Novi predmet
            </Button>
          </Link>
        )}
      </div>

      {/* ── Filteri ───────────────────────────────────────────────────────── */}
      <form method="GET" className="flex flex-wrap gap-3">

        {/* Filter po statusu predmeta */}
        <select
          name="status"
          defaultValue={params.status ?? ""}
          className="h-9 rounded-lg border border-fis-surface3 bg-fis-surface2 text-fis-text1 px-2.5 py-1 text-sm focus:outline-none focus:border-fis-yellow [&>option]:bg-fis-surface2"
        >
          <option value="">Svi statusi</option>
          <option value="AKTIVAN">Aktivan</option>
          <option value="ZATVOREN">Zatvoren</option>
        </select>

        {/* Filter po fazi istrage */}
        <select
          name="faza"
          defaultValue={params.faza ?? ""}
          className="h-9 rounded-lg border border-fis-surface3 bg-fis-surface2 text-fis-text1 px-2.5 py-1 text-sm focus:outline-none focus:border-fis-yellow [&>option]:bg-fis-surface2"
        >
          <option value="">Sve faze</option>
          <option value="ISTRAGA">Istraga</option>
          <option value="PRIKUPLJANJE_DOKAZA">Prikupljanje dokaza</option>
          <option value="SUDJENJE">Suđenje</option>
        </select>

        <Button type="submit" variant="outline" size="sm" className="h-9">
          Filtriraj
        </Button>
        <Link href="/predmeti">
          <Button type="button" variant="ghost" size="sm" className="h-9">
            Resetuj
          </Button>
        </Link>
      </form>

      {/* ── Prazno stanje ili tabela sa predmetima ───────────────────────── */}
      {predmeti.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-fis-surface3 bg-fis-surface py-20 text-center">
          <FolderOpen className="h-12 w-12 text-fis-text3 mb-4" />
          <h3 className="text-lg font-medium text-fis-text2">Nema predmeta</h3>
          <p className="text-sm text-fis-text3 mt-1 mb-4">
            {params.status || params.faza
              ? "Nema rezultata za zadate filtere."
              : "Kreirajte prvi predmet klikom na dugme iznad."}
          </p>
        </div>
      ) : (
        <div className="rounded-xl border border-fis-surface3 bg-fis-surface overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-fis-surface2 hover:bg-fis-surface2 border-b border-fis-surface3">
                <TableHead className="text-xs font-semibold uppercase tracking-wider text-fis-text3">
                  ID
                </TableHead>
                <TableHead className="text-xs font-semibold uppercase tracking-wider text-fis-text3">
                  Naziv
                </TableHead>
                <TableHead className="text-xs font-semibold uppercase tracking-wider text-fis-text3">
                  Status
                </TableHead>
                <TableHead className="text-xs font-semibold uppercase tracking-wider text-fis-text3">
                  Faza
                </TableHead>
                <TableHead className="text-xs font-semibold uppercase tracking-wider text-fis-text3">
                  Datum otvaranja
                </TableHead>
                <TableHead className="text-xs font-semibold uppercase tracking-wider text-fis-text3">
                  Dokazi / Dokumenti
                </TableHead>
                <TableHead className="w-12" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {predmeti.map((predmet) => (
                <TableRow
                  key={predmet.id}
                  className="border-b border-fis-surface3 hover:bg-fis-surface2"
                >
                  {/* ID predmeta u monospajs fontu */}
                  <TableCell className="font-mono text-sm text-fis-text3">
                    #{predmet.id}
                  </TableCell>

                  {/* Naziv predmeta */}
                  <TableCell className="font-medium text-fis-text1">
                    {predmet.naziv}
                  </TableCell>

                  {/* Badge statusa */}
                  <TableCell>
                    <Badge className={`text-xs ${statusBadgeKlasa(predmet.status)}`}>
                      {predmet.status === "AKTIVAN" ? "Aktivan" : "Zatvoren"}
                    </Badge>
                  </TableCell>

                  {/* Badge faze istrage */}
                  <TableCell>
                    <Badge className={`text-xs ${fazaBadgeKlasa(predmet.faza)}`}>
                      {fazeLabel(predmet.faza)}
                    </Badge>
                  </TableCell>

                  {/* Datum otvaranja predmeta */}
                  <TableCell className="text-fis-text2 text-sm">
                    {new Date(predmet.datumOtvaranja).toLocaleDateString("sr-RS")}
                  </TableCell>

                  {/* Broj vezanih zapisa */}
                  <TableCell className="text-fis-text2 text-sm">
                    {predmet._count.dokazi} / {predmet._count.dokumenti}
                  </TableCell>

                  {/* Link ka detaljima */}
                  <TableCell>
                    <Link href={`/predmeti/${predmet.id}`}>
                      <Button variant="ghost" size="sm" className="h-7 w-7 p-0">
                        <Eye className="h-4 w-4" />
                      </Button>
                    </Link>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  );
}
