import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { TagBadge } from "@/components/ui/tag-badge";
import { Plus, FileText, Eye } from "lucide-react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

export const metadata = {
  title: "Dokumentacija — FIS",
};

// ─── Tipovi za filtere i sortiranje (SCRUM-44, SCRUM-45) ──────────────────────

interface SearchParams {
  predmetId?: string;
  tipDokumenta?: string;
  status?: string;
  sortBy?: string;
  sortDir?: string;
}

// ─── Stranica ─────────────────────────────────────────────────────────────────

export default async function DokumentacijaPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const params = await searchParams;
  const sortBy = params.sortBy ?? "datumKreiranja";
  const sortDir = (params.sortDir === "asc" ? "asc" : "desc") as "asc" | "desc";

  // ── Fetch dokumenata sa metapodacima, predmetom i autorom (SCRUM-43) ─────────
  const dokumenti = await prisma.dokument.findMany({
    where: {
      ...(params.predmetId ? { predmetId: parseInt(params.predmetId) } : {}),
      ...(params.status ? { status: params.status as "AKTIVAN" | "ARHIVIRAN" } : {}),
      ...(params.tipDokumenta
        ? {
            metapodaci: {
              some: { kljuc: "tipDokumenta", vrednost: params.tipDokumenta },
            },
          }
        : {}),
    },
    include: {
      predmet: { select: { naziv: true } },
      autor: { select: { ime: true, prezime: true } },
      metapodaci: true,
      tagovi: { include: { tag: true } },
    },
    orderBy:
      sortBy === "naziv"
        ? { naziv: sortDir }
        : sortBy === "predmet"
        ? { predmet: { naziv: sortDir } }
        : { datumKreiranja: sortDir },
  });

  // ── Predmeti za filter dropdown ───────────────────────────────────────────────
  const predmeti = await prisma.predmet.findMany({
    select: { id: true, naziv: true },
    orderBy: { naziv: "asc" },
  });

  // ── Helper: izvuci metapodatak po ključu ──────────────────────────────────────
  function getMeta(metas: { kljuc: string; vrednost: string }[], kljuc: string) {
    return metas.find((m) => m.kljuc === kljuc)?.vrednost ?? "—";
  }

  // ── Helper: link za sortiranje ────────────────────────────────────────────────
  function sortLink(field: string) {
    const newDir =
      sortBy === field && sortDir === "desc" ? "asc" : "desc";
    const p = new URLSearchParams({
      ...params,
      sortBy: field,
      sortDir: newDir,
    });
    return `?${p.toString()}`;
  }

  function sortArrow(field: string) {
    if (sortBy !== field) return " ↕";
    return sortDir === "asc" ? " ↑" : " ↓";
  }

  return (
    <div>
      {/* ── Header ─────────────────────────────────────────────────────────── */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-fis-text1">Dokumentacija</h1>
          <p className="text-sm text-fis-text2 mt-1">
            {dokumenti.length === 0
              ? "Nema dokumenata"
              : `${dokumenti.length} dokument${dokumenti.length === 1 ? "" : dokumenti.length < 5 ? "a" : "a"}`}
          </p>
        </div>
        <Link href="/dokumentacija/novi">
          <Button>
            <Plus className="h-4 w-4 mr-2" />
            Novi dokument
          </Button>
        </Link>
      </div>

      {/* ── Filteri (SCRUM-44) ──────────────────────────────────────────────── */}
      <form method="GET" className="flex flex-wrap gap-3 mb-4">
        {/* Filter: predmet */}
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

        {/* Filter: tip dokumenta */}
        <select
          name="tipDokumenta"
          defaultValue={params.tipDokumenta ?? ""}
          className="h-9 rounded-lg border border-fis-surface3 bg-fis-surface2 text-fis-text1 px-2.5 py-1 text-sm focus:outline-none focus:border-fis-yellow [&>option]:bg-fis-surface2"
        >
          <option value="">Svi tipovi</option>
          {["Izveštaj", "Fotografija", "Zapisnik", "Veštačenje", "Zbirni izveštaj", "Ostalo"].map(
            (t) => (
              <option key={t} value={t}>
                {t}
              </option>
            )
          )}
        </select>

        {/* Filter: status */}
        <select
          name="status"
          defaultValue={params.status ?? ""}
          className="h-9 rounded-lg border border-fis-surface3 bg-fis-surface2 text-fis-text1 px-2.5 py-1 text-sm focus:outline-none focus:border-fis-yellow [&>option]:bg-fis-surface2"
        >
          <option value="">Svi statusi</option>
          <option value="AKTIVAN">Aktivan</option>
          <option value="ARHIVIRAN">Arhiviran</option>
        </select>

        {/* Sačuvaj sortiranje kroz filter submit */}
        <input type="hidden" name="sortBy" value={sortBy} />
        <input type="hidden" name="sortDir" value={sortDir} />

        <Button type="submit" variant="outline" size="sm" className="h-9">
          Filtriraj
        </Button>

        {/* Reset filtera */}
        <Link href="/dokumentacija">
          <Button type="button" variant="ghost" size="sm" className="h-9">
            Resetuj
          </Button>
        </Link>
      </form>

      {/* ── Tabela (SCRUM-43) ───────────────────────────────────────────────── */}
      {dokumenti.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-fis-surface3 bg-fis-surface py-20 text-center">
          <FileText className="h-12 w-12 text-fis-text3 mb-4" />
          <h3 className="text-lg font-medium text-fis-text2">Nema dokumenata</h3>
          <p className="text-sm text-fis-text3 mt-1 mb-4">
            {params.predmetId || params.tipDokumenta || params.status
              ? "Nema rezultata za zadate filtere."
              : "Kreirajte prvi dokument klikom na dugme iznad."}
          </p>
        </div>
      ) : (
        <div className="rounded-xl border border-fis-surface3 bg-fis-surface overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow className="bg-fis-surface2 hover:bg-fis-surface2 border-b border-fis-surface3">
                {/* Sortabilne kolone (SCRUM-45) */}
                <TableHead>
                  <Link href={sortLink("naziv")} className="hover:text-fis-text1">
                    Naziv{sortArrow("naziv")}
                  </Link>
                </TableHead>
                <TableHead>Tip</TableHead>
                <TableHead>
                  <Link href={sortLink("predmet")} className="hover:text-fis-text1">
                    Predmet{sortArrow("predmet")}
                  </Link>
                </TableHead>
                <TableHead>Autor</TableHead>
                <TableHead>
                  <Link href={sortLink("datumKreiranja")} className="hover:text-fis-text1">
                    Datum{sortArrow("datumKreiranja")}
                  </Link>
                </TableHead>
                <TableHead>Verzija</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Tagovi</TableHead>
                <TableHead className="w-12" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {dokumenti.map((dok) => (
                <TableRow key={dok.id} className="border-b border-fis-surface3 hover:bg-fis-surface2">
                  <TableCell className="font-medium text-fis-text1">{dok.naziv}</TableCell>
                  <TableCell className="text-fis-text2 text-sm">
                    {getMeta(dok.metapodaci, "tipDokumenta")}
                  </TableCell>
                  <TableCell className="text-fis-text2 text-sm">
                    {dok.predmet.naziv}
                  </TableCell>
                  <TableCell className="text-fis-text2 text-sm">
                    {dok.autor.ime} {dok.autor.prezime}
                  </TableCell>
                  <TableCell className="text-fis-text2 text-sm">
                    {new Date(dok.datumKreiranja).toLocaleDateString("sr-RS")}
                  </TableCell>
                  <TableCell className="text-center text-sm text-fis-text2">
                    v{dok.verzija}
                  </TableCell>
                  <TableCell>
                    <Badge
                      variant={dok.status === "AKTIVAN" ? "default" : "secondary"}
                      className="text-xs"
                    >
                      {dok.status === "AKTIVAN" ? "Aktivan" : "Arhiviran"}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-wrap gap-1">
                      {dok.tagovi.map(({ tag }) => (
                        <TagBadge key={tag.id} naziv={tag.naziv} boja={tag.boja} />
                      ))}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Link href={`/dokumentacija/${dok.id}`}>
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
