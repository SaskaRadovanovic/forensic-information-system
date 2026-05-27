// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { notFound, redirect } from "next/navigation";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import {
  ArrowLeft,
  Calendar,
  FileText,
  Microscope,
  Pencil,
  FolderOpen,
  Lock,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { ObrisiPredmetDugme } from "@/components/predmeti/ObrisiPredmetDugme";
import { ZatvoriPredmetDugme } from "@/components/predmeti/ZatvoriPredmetDugme";
import { PromeniPredmetFazuDugme } from "@/components/predmeti/PromeniPredmetFazuDugme";

// ─── Helper: srpski naziv faze ───────────────────────────────────────────────

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

// ─── Stranica sa detaljima predmeta ─────────────────────────────────────────

export default async function PredmetDetaljiPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Parsiramo ID iz URL parametra
  const { id } = await params;
  const predmetId = parseInt(id, 10);
  if (isNaN(predmetId)) notFound();

  // ── Fetch predmeta sa vezanim podacima ────────────────────────────────────
  const predmet = await prisma.predmet.findUnique({
    where: { id: predmetId },
    include: {
      // Poslednja 3 dokaza
      dokazi: {
        select: { id: true, sifraDokaza: true, naziv: true, tipDokaza: true, status: true },
        orderBy: { datumPrijema: "desc" },
        take: 3,
      },
      // Poslednja 3 dokumenta
      dokumenti: {
        select: { id: true, naziv: true, verzija: true, datumKreiranja: true },
        orderBy: { datumKreiranja: "desc" },
        take: 3,
      },
      _count: {
        select: { dokazi: true, dokumenti: true, zahteviZaAnalizu: true },
      },
    },
  });

  if (!predmet) notFound();

  // ── Određivanje dozvola po ulozi ──────────────────────────────────────────
  const uloga = session.user.uloga;
  const mozeMenjati =
    (uloga === "ADMINISTRATOR" || uloga === "ISTRAZITELJ") &&
    predmet.status !== "ZATVOREN";
  const mozeZatvoriti =
    (uloga === "ADMINISTRATOR" || uloga === "ISTRAZITELJ") &&
    predmet.status !== "ZATVOREN";
  const mozeMenjatiFazu =
    (uloga === "ADMINISTRATOR" || uloga === "ISTRAZITELJ") &&
    predmet.status !== "ZATVOREN";
  const mozeObrisati = uloga === "ADMINISTRATOR";

  return (
    <div className="max-w-3xl mx-auto space-y-6">

      {/* ── Breadcrumb i akcijska dugmad ──────────────────────────────────── */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm">
          <Link
            href="/predmeti"
            className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Predmeti
          </Link>
          <span className="text-fis-text3">/</span>
          <span className="text-fis-text1 font-medium">#{predmet.id}</span>
        </div>

        <div className="flex items-center gap-2 flex-wrap justify-end">
          {/* Dugme za izmenu — dostupno samo za aktivne predmete */}
          {mozeMenjati && (
            <Link href={`/predmeti/${predmet.id}/izmeni`}>
              <Button variant="outline" size="sm">
                <Pencil className="h-4 w-4 mr-2" />
                Izmeni
              </Button>
            </Link>
          )}
          {/* Dugme za promenu faze — nije prikazano ako smo u poslednjoj fazi */}
          {mozeMenjatiFazu && (
            <PromeniPredmetFazuDugme
              predmetId={predmet.id}
              trenutnaFaza={predmet.faza}
            />
          )}
          {/* Dugme za zatvaranje predmeta */}
          {mozeZatvoriti && (
            <ZatvoriPredmetDugme predmetId={predmet.id} naziv={predmet.naziv} />
          )}
          {/* Dugme za brisanje — samo administrator */}
          {mozeObrisati && (
            <ObrisiPredmetDugme predmetId={predmet.id} naziv={predmet.naziv} />
          )}
        </div>
      </div>

      {/* ── Osnovna kartica predmeta ──────────────────────────────────────── */}
      <Card>
        <CardHeader className="pb-4">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="text-sm font-mono text-fis-text3">#{predmet.id}</p>
              <CardTitle className="text-xl mt-1">{predmet.naziv}</CardTitle>
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
              {/* Badge statusa predmeta */}
              {predmet.status === "ZATVOREN" ? (
                <Badge className="bg-fis-red/10 text-fis-red border-0 text-xs flex items-center gap-1">
                  <Lock className="h-3 w-3" />
                  Zatvoren
                </Badge>
              ) : (
                <Badge className="bg-fis-green/10 text-fis-green border-0 text-xs">
                  Aktivan
                </Badge>
              )}
              {/* Badge faze istrage */}
              <Badge className={`text-xs ${fazaBadgeKlasa(predmet.faza)}`}>
                {fazeLabel(predmet.faza)}
              </Badge>
            </div>
          </div>
        </CardHeader>

        <Separator />

        <CardContent className="pt-6 space-y-4">
          {/* Datum otvaranja predmeta */}
          <div className="flex items-start gap-2">
            <Calendar className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
            <div>
              <p className="text-xs text-fis-text2 uppercase tracking-wide">Datum otvaranja</p>
              <p className="text-sm font-medium text-fis-text1">
                {new Date(predmet.datumOtvaranja).toLocaleDateString("sr-RS", {
                  day: "2-digit",
                  month: "2-digit",
                  year: "numeric",
                })}
              </p>
            </div>
          </div>

          {/* Opis predmeta — prikazujemo samo ako postoji */}
          {predmet.opis && (
            <>
              <Separator />
              <div>
                <p className="text-xs text-fis-text2 uppercase tracking-wide mb-1">Opis</p>
                <p className="text-sm text-fis-text1 leading-relaxed">{predmet.opis}</p>
              </div>
            </>
          )}

          {/* Statistika vezanih zapisa */}
          <Separator />
          <div className="grid grid-cols-3 gap-4">
            <div className="text-center p-3 rounded-lg bg-fis-surface2">
              <p className="text-2xl font-bold text-fis-text1">{predmet._count.dokazi}</p>
              <p className="text-xs text-fis-text2 mt-1">Dokaz(a)</p>
            </div>
            <div className="text-center p-3 rounded-lg bg-fis-surface2">
              <p className="text-2xl font-bold text-fis-text1">{predmet._count.dokumenti}</p>
              <p className="text-xs text-fis-text2 mt-1">Dokument(a)</p>
            </div>
            <div className="text-center p-3 rounded-lg bg-fis-surface2">
              <p className="text-2xl font-bold text-fis-text1">{predmet._count.zahteviZaAnalizu}</p>
              <p className="text-xs text-fis-text2 mt-1">Analiz(a)</p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* ── Poslednji dokazi ──────────────────────────────────────────────── */}
      {predmet.dokazi.length > 0 && (
        <Card>
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between">
              <CardTitle className="text-base flex items-center gap-2">
                <Microscope className="h-4 w-4" />
                Dokazi ({predmet._count.dokazi})
              </CardTitle>
              <Link href={`/dokazi?predmetId=${predmet.id}`}>
                <Button variant="ghost" size="sm" className="text-xs text-fis-text2">
                  Svi dokazi →
                </Button>
              </Link>
            </div>
          </CardHeader>
          <CardContent className="pt-0">
            <div className="space-y-2">
              {predmet.dokazi.map((dokaz) => (
                <div
                  key={dokaz.id}
                  className="flex items-center justify-between py-2 border-b border-fis-surface3 last:border-0"
                >
                  <div>
                    <p className="text-sm font-medium text-fis-text1">{dokaz.naziv}</p>
                    <p className="text-xs font-mono text-fis-text3">{dokaz.sifraDokaza}</p>
                  </div>
                  <Link href={`/dokazi/${dokaz.id}`}>
                    <Button variant="ghost" size="sm" className="h-7 text-xs text-fis-text2">
                      Detalji
                    </Button>
                  </Link>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* ── Poslednji dokumenti ───────────────────────────────────────────── */}
      {predmet.dokumenti.length > 0 && (
        <Card>
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between">
              <CardTitle className="text-base flex items-center gap-2">
                <FileText className="h-4 w-4" />
                Dokumenti ({predmet._count.dokumenti})
              </CardTitle>
              <Link href={`/dokumentacija?predmetId=${predmet.id}`}>
                <Button variant="ghost" size="sm" className="text-xs text-fis-text2">
                  Svi dokumenti →
                </Button>
              </Link>
            </div>
          </CardHeader>
          <CardContent className="pt-0">
            <div className="space-y-2">
              {predmet.dokumenti.map((dok) => (
                <div
                  key={dok.id}
                  className="flex items-center justify-between py-2 border-b border-fis-surface3 last:border-0"
                >
                  <div>
                    <p className="text-sm font-medium text-fis-text1">{dok.naziv}</p>
                    <p className="text-xs text-fis-text3">
                      v{dok.verzija} ·{" "}
                      {new Date(dok.datumKreiranja).toLocaleDateString("sr-RS")}
                    </p>
                  </div>
                  <Link href={`/dokumentacija/${dok.id}`}>
                    <Button variant="ghost" size="sm" className="h-7 text-xs text-fis-text2">
                      Detalji
                    </Button>
                  </Link>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* ── Prazno stanje ako nema vezanih zapisa ────────────────────────── */}
      {predmet._count.dokazi === 0 && predmet._count.dokumenti === 0 && (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-fis-surface3 bg-fis-surface py-10 text-center">
          <FolderOpen className="h-8 w-8 text-fis-text3 mb-3" />
          <p className="text-sm text-fis-text2">Predmet nema vezanih dokaza ni dokumenata.</p>
        </div>
      )}
    </div>
  );
}
