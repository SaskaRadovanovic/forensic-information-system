import { notFound, redirect } from "next/navigation";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft, Download, FileText, Calendar, User, FolderOpen, Tag, Clock, Pencil } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { TagBadge } from "@/components/ui/tag-badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { ArhivirajDugme } from "@/components/dokumenti/ArhivirajDugme";

export default async function DokumentDetaljiPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const { id } = await params;
  const dokumentId = parseInt(id, 10);
  if (isNaN(dokumentId)) notFound();

  // ── Fetch dokumenta sa svim relacijama ────────────────────────────────────
  const dok = await prisma.dokument.findUnique({
    where: { id: dokumentId },
    include: {
      predmet: true,
      autor: { select: { ime: true, prezime: true, uloga: true } },
      metapodaci: true,
      tagovi: { include: { tag: true } },
      arhiva: {
        include: { sacuvao: { select: { ime: true, prezime: true } } },
        orderBy: { datumArhiviranja: "desc" },
      },
    },
  });

  if (!dok) notFound();

  // ── Helpers ───────────────────────────────────────────────────────────────
  function getMeta(kljuc: string) {
    return dok!.metapodaci.find((m) => m.kljuc === kljuc)?.vrednost ?? "—";
  }

  const tipDokumenta = getMeta("tipDokumenta");
  const opis = getMeta("opis");

  return (
    <div className="max-w-3xl mx-auto space-y-6">

      {/* ── Breadcrumb + akcije ──────────────────────────────────────────── */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm">
          <Link
            href="/dokumentacija"
            className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Dokumentacija
          </Link>
          <span className="text-fis-text3">/</span>
          <span className="text-fis-text1 font-medium truncate max-w-xs">
            {dok.naziv}
          </span>
        </div>

        <div className="flex items-center gap-2 flex-wrap">
          {/* Dugmad dostupna samo za AKTIVNE dokumente */}
          {dok.status === "AKTIVAN" && (
            <>
              {/* Izmeni (SCRUM-41) */}
              <Link href={`/dokumentacija/${dok.id}/izmeni`}>
                <Button variant="outline" size="sm">
                  <Pencil className="h-4 w-4 mr-2" />
                  Izmeni
                </Button>
              </Link>
              {/* Arhiviraj (SCRUM-39) */}
              <ArhivirajDugme dokumentId={dok.id} />
            </>
          )}
          {/* Tagovi (SCRUM-34) */}
          <Link href={`/dokumentacija/${dok.id}/tagovi`}>
            <Button variant="outline" size="sm">
              <Tag className="h-4 w-4 mr-2" />
              Tagovi
            </Button>
          </Link>
          {/* PDF Download (SCRUM-46) */}
          <a href={`/api/dokumenti/${dok.id}/preuzmi`} download>
            <Button variant="outline" size="sm">
              <Download className="h-4 w-4 mr-2" />
              Preuzmi PDF
            </Button>
          </a>
        </div>
      </div>

      {/* ── Osnovna kartica ──────────────────────────────────────────────── */}
      <Card>
        <CardHeader className="pb-4">
          <div className="flex items-start justify-between gap-4">
            <div className="flex items-start gap-3">
              <FileText className="h-8 w-8 text-red-500 flex-shrink-0 mt-0.5" />
              <div>
                <CardTitle className="text-xl">{dok.naziv}</CardTitle>
                <p className="text-sm text-fis-text2 mt-1">{tipDokumenta}</p>
              </div>
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
              <Badge variant="outline" className="text-xs">
                v{dok.verzija}
              </Badge>
              <Badge
                variant={dok.status === "AKTIVAN" ? "default" : "secondary"}
              >
                {dok.status === "AKTIVAN" ? "Aktivan" : "Arhiviran"}
              </Badge>
            </div>
          </div>
        </CardHeader>

        <Separator />

        <CardContent className="pt-6 space-y-4">
          {/* Metapodaci u mrežu */}
          <div className="grid grid-cols-2 gap-4">
            <div className="flex items-start gap-2">
              <FolderOpen className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
              <div>
                <p className="text-xs text-fis-text2 uppercase tracking-wide">Predmet</p>
                <p className="text-sm font-medium text-fis-text1">{dok.predmet.naziv}</p>
              </div>
            </div>

            <div className="flex items-start gap-2">
              <User className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
              <div>
                <p className="text-xs text-fis-text2 uppercase tracking-wide">Autor</p>
                <p className="text-sm font-medium text-fis-text1">
                  {dok.autor.ime} {dok.autor.prezime}
                </p>
              </div>
            </div>

            <div className="flex items-start gap-2">
              <Calendar className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
              <div>
                <p className="text-xs text-fis-text2 uppercase tracking-wide">Datum kreiranja</p>
                <p className="text-sm font-medium text-fis-text1">
                  {new Date(dok.datumKreiranja).toLocaleDateString("sr-RS", {
                    day: "2-digit",
                    month: "2-digit",
                    year: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                  })}
                </p>
              </div>
            </div>

            <div className="flex items-start gap-2">
              <FileText className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
              <div>
                <p className="text-xs text-fis-text2 uppercase tracking-wide">Putanja fajla</p>
                <p className="text-sm font-medium text-fis-text1 font-mono break-all">
                  {dok.putanja}
                </p>
              </div>
            </div>
          </div>

          {/* Opis */}
          {opis !== "—" && (
            <>
              <Separator />
              <div>
                <p className="text-xs text-fis-text2 uppercase tracking-wide mb-1">Opis</p>
                <p className="text-sm text-fis-text1">{opis}</p>
              </div>
            </>
          )}

          {/* Tagovi */}
          {dok.tagovi.length > 0 && (
            <>
              <Separator />
              <div className="flex items-start gap-2">
                <Tag className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
                <div>
                  <p className="text-xs text-fis-text2 uppercase tracking-wide mb-2">Tagovi</p>
                  <div className="flex flex-wrap gap-1.5">
                    {dok.tagovi.map(({ tag }) => (
                      <TagBadge key={tag.id} naziv={tag.naziv} boja={tag.boja} />
                    ))}
                  </div>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>

      {/* ── Istorija verzija (SCRUM-46) ───────────────────────────────────── */}
      {dok.arhiva.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Clock className="h-4 w-4" />
              Istorija verzija ({dok.arhiva.length})
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {dok.arhiva.map((entry) => (
              <div
                key={entry.id}
                className="flex items-start justify-between rounded-md border border-fis-surface3 bg-fis-surface2 px-4 py-3"
              >
                <div>
                  <p className="text-sm font-medium text-fis-text1">
                    Verzija {entry.verzija}
                  </p>
                  <p className="text-xs text-fis-text2 mt-0.5">
                    Arhivirao: {entry.sacuvao.ime} {entry.sacuvao.prezime} —{" "}
                    {new Date(entry.datumArhiviranja).toLocaleDateString("sr-RS")}
                  </p>
                  {entry.razlogIzmene && (
                    <p className="text-xs text-fis-text2 mt-1 italic">
                      {entry.razlogIzmene}
                    </p>
                  )}
                </div>
                <Badge variant="outline" className="text-xs flex-shrink-0">
                  v{entry.verzija}
                </Badge>
              </div>
            ))}
          </CardContent>
        </Card>
      )}

    </div>
  );
}
