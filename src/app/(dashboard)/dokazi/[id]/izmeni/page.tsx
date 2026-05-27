import { notFound, redirect } from "next/navigation";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { DokazForma } from "@/components/dokazi/DokazForma";

// ─── Metadata ───────────────────────────────────────────────────────────────

export const metadata = { title: "Izmena dokaza — FIS" };

// ─── Stranica ───────────────────────────────────────────────────────────────

export default async function IzmeniDokazPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Provera autorizacije — samo TEHNICAR i ADMINISTRATOR
  const uloga = session.user.uloga;
  if (uloga !== "TEHNICAR" && uloga !== "ADMINISTRATOR") {
    redirect("/dokazi");
  }

  const { id } = await params;
  const dokazId = parseInt(id, 10);
  if (isNaN(dokazId)) notFound();

  // ── Fetch dokaza sa specifičnim poljima ────────────────────────────────────
  const dokaz = await prisma.dokaz.findUnique({
    where: { id: dokazId },
    include: {
      bioloskiTrag: true,
      oruzje: true,
      dokumentDokaz: true,
      odeca: true,
      uzorak: true,
    },
  });

  if (!dokaz) notFound();

  // Provera da dokaz nije arhiviran
  if (dokaz.status === "ARHIVIRANO") {
    redirect(`/dokazi/${dokazId}`);
  }

  // ── Učitavanje predmeta za dropdown ───────────────────────────────────────
  const predmeti = await prisma.predmet.findMany({
    select: { id: true, naziv: true },
    orderBy: { naziv: "asc" },
  });

  // ── Priprema specifičnih polja za formu ───────────────────────────────────
  const specificnaPolja: Record<string, any> = {};

  if (dokaz.bioloskiTrag) {
    specificnaPolja.vrstaTraga = dokaz.bioloskiTrag.vrstaTraga ?? "";
    specificnaPolja.nacinUzorkovanja = dokaz.bioloskiTrag.nacinUzorkovanja ?? "";
    specificnaPolja.usloviCuvanja = dokaz.bioloskiTrag.usloviCuvanja ?? "";
    specificnaPolja.kolicina = dokaz.bioloskiTrag.kolicina ?? "";
  } else if (dokaz.oruzje) {
    specificnaPolja.vrstaOruzja = dokaz.oruzje.vrstaOruzja ?? "";
    specificnaPolja.marka = dokaz.oruzje.marka ?? "";
    specificnaPolja.model = dokaz.oruzje.model ?? "";
    specificnaPolja.kalibar = dokaz.oruzje.kalibar ?? "";
    specificnaPolja.serijskiBr = dokaz.oruzje.serijskiBr ?? "";
  } else if (dokaz.dokumentDokaz) {
    specificnaPolja.vrstaDokumenta = dokaz.dokumentDokaz.vrstaDokumenta ?? "";
    specificnaPolja.jezik = dokaz.dokumentDokaz.jezik ?? "";
    specificnaPolja.brojStranica = dokaz.dokumentDokaz.brojStranica?.toString() ?? "";
  } else if (dokaz.odeca) {
    specificnaPolja.vrstaOdevnogPredmeta = dokaz.odeca.vrstaOdevnogPredmeta ?? "";
    specificnaPolja.velicina = dokaz.odeca.velicina ?? "";
    specificnaPolja.boja = dokaz.odeca.boja ?? "";
    specificnaPolja.stanje = dokaz.odeca.stanje ?? "";
  } else if (dokaz.uzorak) {
    specificnaPolja.vrstaUzorka = dokaz.uzorak.vrstaUzorka ?? "";
    specificnaPolja.kolicinaUzorka = dokaz.uzorak.kolicina ?? "";
    specificnaPolja.jedinicaMere = dokaz.uzorak.jedinicaMere ?? "";
    specificnaPolja.nacinUzorkovanjaUzorka = dokaz.uzorak.nacinUzorkovanja ?? "";
    specificnaPolja.usloviCuvanjaUzorka = dokaz.uzorak.usloviCuvanja ?? "";
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">
      {/* Breadcrumb navigacija */}
      <div className="flex items-center gap-2 text-sm">
        <Link
          href={`/dokazi/${dokazId}`}
          className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          {dokaz.sifraDokaza}
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">Izmena</span>
      </div>

      {/* Forma za izmenu sa prepopunjenim podacima */}
      <DokazForma
        mode="edit"
        predmeti={predmeti}
        pocetneVrednosti={{
          id: dokaz.id,
          naziv: dokaz.naziv,
          opis: dokaz.opis,
          tipDokaza: dokaz.tipDokaza,
          predmetId: dokaz.predmetId,
          lokacijaSkladistenja: dokaz.lokacijaSkladistenja,
          datumPrijema: dokaz.datumPrijema.toISOString(),
          datumPronalaska: dokaz.datumPronalaska?.toISOString() ?? null,
          lokacijaPronalaska: dokaz.lokacijaPronalaska,
          specificnaPolja,
        }}
      />
    </div>
  );
}
