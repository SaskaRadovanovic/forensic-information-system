// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { notFound, redirect } from "next/navigation";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import {
  ArrowLeft, Calendar, MapPin, User, FolderOpen, Pencil, Clock, Search,
} from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { ArhivirajDokazDugme } from "@/components/dokazi/ArhivirajDokazDugme";
import { LanacCuvanjaVremenskaLinija } from "@/components/dokazi/LanacCuvanjaVremenskaLinija";

// ─── Helperi za badge boje statusa (uključuje KOMPROMITOVAN) ────────────────

function statusBadgeKlasa(status: string): string {
  const mapa: Record<string, string> = {
    U_SKLADISTU: "text-fis-green border-fis-green/40",
    IZDATO_ZA_ANALIZU: "text-fis-blue border-fis-blue/40",
    VRACENO: "text-fis-yellow border-fis-yellow/40",
    ARHIVIRANO: "text-fis-red border-fis-red/40",
    KOMPROMITOVAN: "text-fis-orange border-fis-orange/40",
    PRIJEM: "text-fis-text2 border-fis-border",
  };
  return mapa[status] ?? "text-fis-text2 border-fis-border";
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

// ─── Stranica za detalje dokaza ──────────────────────────────────────────────

export default async function DokazDetaljiPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  // Provera autentifikacije — preusmeravanje na login ako nije ulogovan
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Parsiramo ID iz URL parametra
  const { id } = await params;
  const dokazId = parseInt(id, 10);
  if (isNaN(dokazId)) notFound();

  // ── Fetch dokaza sa svim relacijama ───────────────────────────────────────
  const dokaz = await prisma.dokaz.findUnique({
    where: { id: dokazId },
    include: {
      predmet: true,
      tehnicar: {
        include: { korisnik: { select: { ime: true, prezime: true } } },
      },
      // Lanac čuvanja sortiran od novijeg ka starijem
      lanacCuvanja: {
        include: {
          tehnicar: {
            include: { korisnik: { select: { ime: true, prezime: true } } },
          },
        },
        orderBy: { datumVreme: "desc" },
      },
      // Specifična polja za svaki tip dokaza
      bioloskiTrag: true,
      oruzje: true,
      dokumentDokaz: true,
      odeca: true,
      uzorak: true,
    },
  });

  if (!dokaz) notFound();

  // ── Provera uloge za prikaz akcijskih dugmadi ─────────────────────────────
  const uloga = session.user.uloga;
  const mozeMenjati =
    (uloga === "TEHNICAR" || uloga === "ADMINISTRATOR") &&
    dokaz.status !== "ARHIVIRANO";
  const mozeArhivirati =
    uloga === "ADMINISTRATOR" && dokaz.status !== "ARHIVIRANO";

  return (
    <div className="max-w-3xl mx-auto space-y-6">

      {/* ── Breadcrumb navigacija i dugmad za akcije ─────────────────────── */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm">
          <Link
            href="/dokazi"
            className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Dokazi
          </Link>
          <span className="text-fis-text3">/</span>
          <span className="text-fis-text1 font-medium">{dokaz.sifraDokaza}</span>
        </div>

        <div className="flex items-center gap-2">
          {mozeMenjati && (
            <Link href={`/dokazi/${dokaz.id}/izmeni`}>
              <button
                style={{
                  fontFamily: "var(--font-mono), monospace",
                  fontSize: 11,
                  textTransform: "uppercase",
                  letterSpacing: 1,
                  padding: "7px 14px",
                  border: "1px solid #3a3a3a",
                  background: "transparent",
                  color: "#999999",
                  cursor: "pointer",
                  display: "inline-flex",
                  alignItems: "center",
                  gap: 6,
                }}
              >
                <Pencil className="h-3.5 w-3.5" />
                Izmeni
              </button>
            </Link>
          )}
          {/* Dugme za arhiviranje — samo ADMINISTRATOR */}
          {mozeArhivirati && (
            <ArhivirajDokazDugme dokazId={dokaz.id} />
          )}
        </div>
      </div>

      {/* ── Kartica sa osnovnim podacima dokaza ──────────────────────────── */}
      <Card>
        <CardHeader className="pb-4">
          <div className="flex items-start justify-between gap-4">
            <div>
              {/* Šifra dokaza u monospajs fontu */}
              <p className="text-sm font-mono text-fis-text2">{dokaz.sifraDokaza}</p>
              <CardTitle className="text-xl mt-1">{dokaz.naziv}</CardTitle>
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
              <span className={`fis-badge ${statusBadgeKlasa(dokaz.status)}`}>
                {statusLabel(dokaz.status)}
              </span>
            </div>
          </div>
        </CardHeader>

        <Separator />

        <CardContent className="pt-6 space-y-4">
          {/* Mrežni prikaz metapodataka dokaza */}
          <div className="grid grid-cols-2 gap-4">

            {/* Predmet kome dokaz pripada */}
            <div className="flex items-start gap-2">
              <FolderOpen className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
              <div>
                <p className="text-xs text-fis-text2 uppercase tracking-wide">Predmet</p>
                <p className="text-sm font-medium text-fis-text1">{dokaz.predmet.naziv}</p>
              </div>
            </div>

            {/* Tehničar koji je primio dokaz */}
            <div className="flex items-start gap-2">
              <User className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
              <div>
                <p className="text-xs text-fis-text2 uppercase tracking-wide">Tehničar</p>
                <p className="text-sm font-medium text-fis-text1">
                  {dokaz.tehnicar.korisnik.ime} {dokaz.tehnicar.korisnik.prezime}
                </p>
              </div>
            </div>

            {/* Datum prijema dokaza */}
            <div className="flex items-start gap-2">
              <Calendar className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
              <div>
                <p className="text-xs text-fis-text2 uppercase tracking-wide">Datum prijema</p>
                <p className="text-sm font-medium text-fis-text1">
                  {new Date(dokaz.datumPrijema).toLocaleDateString("sr-RS", {
                    day: "2-digit", month: "2-digit", year: "numeric",
                  })}
                </p>
              </div>
            </div>

            {/* Lokacija gde se dokaz čuva — prikazujemo samo ako postoji */}
            {dokaz.lokacijaSkladistenja && (
              <div className="flex items-start gap-2">
                <MapPin className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
                <div>
                  <p className="text-xs text-fis-text2 uppercase tracking-wide">Lokacija skladištenja</p>
                  <p className="text-sm font-medium text-fis-text1">{dokaz.lokacijaSkladistenja}</p>
                </div>
              </div>
            )}

            {/* Datum pronalaska — novo opciono polje */}
            {dokaz.datumPronalaska && (
              <div className="flex items-start gap-2">
                <Search className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
                <div>
                  <p className="text-xs text-fis-text2 uppercase tracking-wide">Datum pronalaska</p>
                  <p className="text-sm font-medium text-fis-text1">
                    {new Date(dokaz.datumPronalaska).toLocaleDateString("sr-RS", {
                      day: "2-digit", month: "2-digit", year: "numeric",
                    })}
                  </p>
                </div>
              </div>
            )}

            {/* Lokacija pronalaska — novo opciono polje */}
            {dokaz.lokacijaPronalaska && (
              <div className="flex items-start gap-2">
                <MapPin className="h-4 w-4 text-fis-text2 mt-0.5 flex-shrink-0" />
                <div>
                  <p className="text-xs text-fis-text2 uppercase tracking-wide">Lokacija pronalaska</p>
                  <p className="text-sm font-medium text-fis-text1">{dokaz.lokacijaPronalaska}</p>
                </div>
              </div>
            )}
          </div>

          {/* Opis dokaza — prikazujemo samo ako postoji */}
          {dokaz.opis && (
            <>
              <Separator />
              <div>
                <p className="text-xs text-fis-text2 uppercase tracking-wide mb-1">Opis</p>
                <p className="text-sm text-fis-text1">{dokaz.opis}</p>
              </div>
            </>
          )}

          {/* Specifična polja po tipu dokaza */}
          <Separator />
          <div>
            <p className="text-xs text-fis-text2 uppercase tracking-wide mb-3">
              Tip: {tipLabel(dokaz.tipDokaza)}
            </p>
            <SpecificnaPoljaDisplay dokaz={dokaz} />
          </div>
        </CardContent>
      </Card>

      {/* ── Kartica sa lancem čuvanja — prikazujemo samo ako postoje zapisi ─ */}
      {dokaz.lanacCuvanja.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Clock className="h-4 w-4" />
              Lanac čuvanja ({dokaz.lanacCuvanja.length})
            </CardTitle>
          </CardHeader>
          <CardContent>
            <LanacCuvanjaVremenskaLinija zapisi={dokaz.lanacCuvanja} />
          </CardContent>
        </Card>
      )}
    </div>
  );
}

// ─── Komponenta za prikaz specifičnih polja po tipu dokaza ──────────────────

function SpecificnaPoljaDisplay({ dokaz }: { dokaz: any }) {
  // Gradimo niz parova labela-vrednost u zavisnosti od tipa
  const polja: { label: string; vrednost: string | number | null }[] = [];

  if (dokaz.bioloskiTrag) {
    // Specifična polja za biološki trag
    const bt = dokaz.bioloskiTrag;
    if (bt.vrstaTraga) polja.push({ label: "Vrsta traga", vrednost: bt.vrstaTraga });
    if (bt.nacinUzorkovanja) polja.push({ label: "Način uzorkovanja", vrednost: bt.nacinUzorkovanja });
    if (bt.usloviCuvanja) polja.push({ label: "Uslovi čuvanja", vrednost: bt.usloviCuvanja });
    if (bt.kolicina) polja.push({ label: "Količina", vrednost: bt.kolicina });
  } else if (dokaz.oruzje) {
    // Specifična polja za oružje
    const o = dokaz.oruzje;
    if (o.vrstaOruzja) polja.push({ label: "Vrsta oružja", vrednost: o.vrstaOruzja });
    if (o.marka) polja.push({ label: "Marka", vrednost: o.marka });
    if (o.model) polja.push({ label: "Model", vrednost: o.model });
    if (o.kalibar) polja.push({ label: "Kalibar", vrednost: o.kalibar });
    if (o.serijskiBr) polja.push({ label: "Serijski broj", vrednost: o.serijskiBr });
  } else if (dokaz.dokumentDokaz) {
    // Specifična polja za dokument
    const d = dokaz.dokumentDokaz;
    if (d.vrstaDokumenta) polja.push({ label: "Vrsta dokumenta", vrednost: d.vrstaDokumenta });
    if (d.jezik) polja.push({ label: "Jezik", vrednost: d.jezik });
    if (d.brojStranica) polja.push({ label: "Broj stranica", vrednost: d.brojStranica });
  } else if (dokaz.odeca) {
    // Specifična polja za odeću
    const od = dokaz.odeca;
    if (od.vrstaOdevnogPredmeta) polja.push({ label: "Vrsta", vrednost: od.vrstaOdevnogPredmeta });
    if (od.velicina) polja.push({ label: "Veličina", vrednost: od.velicina });
    if (od.boja) polja.push({ label: "Boja", vrednost: od.boja });
    if (od.stanje) polja.push({ label: "Stanje", vrednost: od.stanje });
  } else if (dokaz.uzorak) {
    // Specifična polja za uzorak
    const u = dokaz.uzorak;
    if (u.vrstaUzorka) polja.push({ label: "Vrsta uzorka", vrednost: u.vrstaUzorka });
    if (u.kolicina) polja.push({ label: "Količina", vrednost: u.kolicina });
    if (u.jedinicaMere) polja.push({ label: "Jedinica mere", vrednost: u.jedinicaMere });
    if (u.nacinUzorkovanja) polja.push({ label: "Način uzorkovanja", vrednost: u.nacinUzorkovanja });
    if (u.usloviCuvanja) polja.push({ label: "Uslovi čuvanja", vrednost: u.usloviCuvanja });
  }

  // Ako nema specifičnih polja prikazujemo poruku
  if (polja.length === 0) {
    return <p className="text-sm text-fis-text3 italic">Nema specifičnih podataka</p>;
  }

  // Mrežni prikaz svih parova labela-vrednost
  return (
    <div className="grid grid-cols-2 gap-3">
      {polja.map((p) => (
        <div key={p.label}>
          <p className="text-xs text-fis-text2">{p.label}</p>
          <p className="text-sm font-medium text-fis-text1">{p.vrednost}</p>
        </div>
      ))}
    </div>
  );
}
