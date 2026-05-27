// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Plus, Microscope, Eye } from "lucide-react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

// ─── Metadata stranice ───────────────────────────────────────────────────────

export const metadata = { title: "Dokazi — FIS" };

// ─── Tipovi za search parametre (filteri i sortiranje) ───────────────────────

interface SearchParams {
  tipDokaza?: string;
  status?: string;
  predmetId?: string;
  sortBy?: string;
  sortDir?: string;
}

// ─── Helper: CSS klasa za badge statusa dokaza ───────────────────────────────
// Uključuje KOMPROMITOVAN status sa narandžastom bojom

function statusBadgeKlasa(status: string): string {
  const mapa: Record<string, string> = {
    U_SKLADISTU: "bg-fis-green/10 text-fis-green border-0",
    IZDATO_ZA_ANALIZU: "bg-fis-blue/10 text-fis-blue border-0",
    VRACENO: "bg-fis-yellow/10 text-fis-yellow border-0",
    ARHIVIRANO: "bg-fis-red/10 text-fis-red border-0",
    KOMPROMITOVAN: "bg-fis-orange/10 text-fis-orange border-0",
    PRIJEM: "bg-fis-surface3 text-fis-text2 border-0",
  };
  return mapa[status] ?? "bg-fis-surface3 text-fis-text2 border-0";
}

// ─── Helper: srpski naziv za status dokaza ───────────────────────────────────

function statusLabel(status: string): string {
  const mapa: Record<string, string> = {
    PRIJEM: "Prijem",
    U_SKLADISTU: "U skladištu",
    IZDATO_ZA_ANALIZU: "Na analizi",
    VRACENO: "Vraćeno",
    ARHIVIRANO: "Arhivirano",
    KOMPROMITOVAN: "Kompromitovan",
  };
  return mapa[status] ?? status;
}

// ─── Helper: CSS klasa za badge tipa dokaza ──────────────────────────────────

function tipBadgeKlasa(tip: string): string {
  const mapa: Record<string, string> = {
    BIOLOSKI_TRAG: "bg-fis-green/10 text-fis-green border-0",
    ORUZJE: "bg-fis-red/10 text-fis-red border-0",
    DOKUMENT: "bg-fis-blue/10 text-fis-blue border-0",
    ODECA: "bg-fis-yellow/10 text-fis-yellow border-0",
    UZORAK: "bg-fis-orange/10 text-fis-orange border-0",
  };
  return mapa[tip] ?? "bg-fis-surface3 text-fis-text2 border-0";
}

// ─── Helper: srpski naziv za tip dokaza ──────────────────────────────────────

function tipLabel(tip: string): string {
  const mapa: Record<string, string> = {
    BIOLOSKI_TRAG: "Biološki trag",
    ORUZJE: "Oružje",
    DOKUMENT: "Dokument",
    ODECA: "Odeća",
    UZORAK: "Uzorak",
  };
  return mapa[tip] ?? tip;
}

// ─── Stranica za pregled liste dokaza ────────────────────────────────────────

export default async function DokaziPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Čitamo parametre za filtriranje i sortiranje
  const params = await searchParams;
  const sortBy = params.sortBy ?? "datumPrijema";
  const sortDir = (params.sortDir === "asc" ? "asc" : "desc") as "asc" | "desc";

  // Proveravamo ulogu za prikaz dugmeta "Novi dokaz"
  const uloga = session.user.uloga;
  const mozeKreirati = uloga === "TEHNICAR" || uloga === "ADMINISTRATOR";

  // ── Fetch dokaza sa primenjenim filterima ─────────────────────────────────
  const dokazi = await prisma.dokaz.findMany({
    where: {
      // Podrazumevano skrivamo arhivirane dokaze osim ako je eksplicitno tražen taj status
      status: params.status
        ? (params.status as any)
        : { not: "ARHIVIRANO" },
      ...(params.tipDokaza ? { tipDokaza: params.tipDokaza as any } : {}),
      ...(params.predmetId ? { predmetId: parseInt(params.predmetId) } : {}),
    },
    include: {
      predmet: { select: { naziv: true } },
      tehnicar: {
        include: { korisnik: { select: { ime: true, prezime: true } } },
      },
    },
    // Sortiranje po odabranom polju
    orderBy:
      sortBy === "sifraDokaza"
        ? { sifraDokaza: sortDir }
        : sortBy === "naziv"
        ? { naziv: sortDir }
        : { datumPrijema: sortDir },
  });

  // ── Predmeti za filter dropdown ───────────────────────────────────────────
  const predmeti = await prisma.predmet.findMany({
    select: { id: true, naziv: true },
    orderBy: { naziv: "asc" },
  });

  // ── Helper: generiše link za sortiranje sa toggleom smera ────────────────
  function sortLink(field: string) {
    const newDir = sortBy === field && sortDir === "desc" ? "asc" : "desc";
    const p = new URLSearchParams({
      ...params,
      sortBy: field,
      sortDir: newDir,
    });
    return `?${p.toString()}`;
  }

  // ── Helper: prikazuje strelicu za trenutni smer sortiranja ────────────────
  function sortArrow(field: string) {
    if (sortBy !== field) return " ↕";
    return sortDir === "asc" ? " ↑" : " ↓";
  }

  return (
    <div className="space-y-6">

      {/* ── Header sa naslovom i dugmetom za kreiranje ───────────────────── */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-fis-text1">Dokazi</h1>
        {mozeKreirati && (
          <Link href="/dokazi/novi">
            <Button>
              <Plus className="h-4 w-4 mr-2" />
              Novi dokaz
            </Button>
          </Link>
        )}
      </div>

      {/* ── Panel sa filterima ────────────────────────────────────────────── */}
      <form method="GET" className="flex flex-wrap gap-3">

        {/* Filter po tipu dokaza */}
        <select
          name="tipDokaza"
          defaultValue={params.tipDokaza ?? ""}
          className="h-9 rounded-lg border border-fis-surface3 bg-fis-surface2 text-fis-text1 px-2.5 py-1 text-sm focus:outline-none focus:border-fis-yellow [&>option]:bg-fis-surface2"
        >
          <option value="">Svi tipovi</option>
          <option value="BIOLOSKI_TRAG">Biološki trag</option>
          <option value="ORUZJE">Oružje</option>
          <option value="DOKUMENT">Dokument</option>
          <option value="ODECA">Odeća</option>
          <option value="UZORAK">Uzorak</option>
        </select>

        {/* Filter po statusu dokaza — uključuje KOMPROMITOVAN */}
        <select
          name="status"
          defaultValue={params.status ?? ""}
          className="h-9 rounded-lg border border-fis-surface3 bg-fis-surface2 text-fis-text1 px-2.5 py-1 text-sm focus:outline-none focus:border-fis-yellow [&>option]:bg-fis-surface2"
        >
          <option value="">Svi statusi</option>
          <option value="U_SKLADISTU">U skladištu</option>
          <option value="IZDATO_ZA_ANALIZU">Na analizi</option>
          <option value="VRACENO">Vraćeno</option>
          <option value="KOMPROMITOVAN">Kompromitovan</option>
          <option value="ARHIVIRANO">Arhivirano</option>
        </select>

        {/* Filter po predmetu */}
        <select
          name="predmetId"
          defaultValue={params.predmetId ?? ""}
          className="h-9 rounded-lg border border-fis-surface3 bg-fis-surface2 text-fis-text1 px-2.5 py-1 text-sm focus:outline-none focus:border-fis-yellow [&>option]:bg-fis-surface2"
        >
          <option value="">Svi predmeti</option>
          {predmeti.map((p) => (
            <option key={p.id} value={String(p.id)}>
              {p.naziv}
            </option>
          ))}
        </select>

        {/* Čuvamo parametre sortiranja pri filtriranju */}
        <input type="hidden" name="sortBy" value={sortBy} />
        <input type="hidden" name="sortDir" value={sortDir} />

        <Button type="submit" variant="outline" size="sm" className="h-9">
          Filtriraj
        </Button>

        <Link href="/dokazi">
          <Button type="button" variant="ghost" size="sm" className="h-9">
            Resetuj
          </Button>
        </Link>
      </form>

      {/* ── Prazno stanje ili tabela sa dokazima ─────────────────────────── */}
      {dokazi.length === 0 ? (
        // Prazno stanje — nema dokaza koji odgovaraju filterima
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-fis-surface3 bg-fis-surface py-20 text-center">
          <Microscope className="h-12 w-12 text-fis-text3 mb-4" />
          <h3 className="text-lg font-medium text-fis-text2">Nema dokaza</h3>
          <p className="text-sm text-fis-text3 mt-1 mb-4">
            {params.tipDokaza || params.status || params.predmetId
              ? "Nema rezultata za zadate filtere."
              : "Evidentirajte prvi dokaz klikom na dugme iznad."}
          </p>
        </div>
      ) : (
        // Tabela sa rezultatima
        <div className="rounded-xl border border-fis-surface3 bg-fis-surface overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-fis-surface2 hover:bg-fis-surface2 border-b border-fis-surface3">
                <TableHead>
                  <Link href={sortLink("sifraDokaza")} className="hover:text-fis-text1">
                    Šifra{sortArrow("sifraDokaza")}
                  </Link>
                </TableHead>
                <TableHead>
                  <Link href={sortLink("naziv")} className="hover:text-fis-text1">
                    Naziv{sortArrow("naziv")}
                  </Link>
                </TableHead>
                <TableHead>Tip</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Predmet</TableHead>
                <TableHead>
                  <Link href={sortLink("datumPrijema")} className="hover:text-fis-text1">
                    Datum prijema{sortArrow("datumPrijema")}
                  </Link>
                </TableHead>
                <TableHead className="w-12" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {dokazi.map((dokaz) => (
                <TableRow key={dokaz.id} className="border-b border-fis-surface3 hover:bg-fis-surface2">

                  {/* Šifra dokaza u monospajs fontu */}
                  <TableCell className="font-mono text-sm text-fis-text2">
                    {dokaz.sifraDokaza}
                  </TableCell>

                  {/* Naziv dokaza */}
                  <TableCell className="font-medium text-fis-text1">
                    {dokaz.naziv}
                  </TableCell>

                  {/* Badge tipa dokaza sa odgovarajućom bojom */}
                  <TableCell>
                    <Badge className={`text-xs ${tipBadgeKlasa(dokaz.tipDokaza)}`}>
                      {tipLabel(dokaz.tipDokaza)}
                    </Badge>
                  </TableCell>

                  {/* Badge statusa dokaza — KOMPROMITOVAN je narandžast */}
                  <TableCell>
                    <Badge className={`text-xs ${statusBadgeKlasa(dokaz.status)}`}>
                      {statusLabel(dokaz.status)}
                    </Badge>
                  </TableCell>

                  {/* Naziv predmeta */}
                  <TableCell className="text-fis-text2 text-sm">
                    {dokaz.predmet.naziv}
                  </TableCell>

                  {/* Datum prijema formatiran po srpskom standardu */}
                  <TableCell className="text-fis-text2 text-sm">
                    {new Date(dokaz.datumPrijema).toLocaleDateString("sr-RS")}
                  </TableCell>

                  {/* Link ka detaljima dokaza */}
                  <TableCell>
                    <Link href={`/dokazi/${dokaz.id}`}>
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
