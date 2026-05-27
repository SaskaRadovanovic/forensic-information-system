// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { notFound, redirect } from "next/navigation";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { PredmetForma } from "@/components/predmeti/PredmetForma";

// ─── Metadata stranice ───────────────────────────────────────────────────────

export const metadata = { title: "Izmena predmeta — FIS" };

// ─── Stranica za izmenu predmeta ─────────────────────────────────────────────

export default async function IzmeniPredmetPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Samo ADMINISTRATOR i ISTRAZITELJ mogu menjati predmete
  const uloga = session.user.uloga;
  if (uloga !== "ADMINISTRATOR" && uloga !== "ISTRAZITELJ") {
    redirect("/predmeti");
  }

  // Parsiramo ID iz URL parametra
  const { id } = await params;
  const predmetId = parseInt(id, 10);
  if (isNaN(predmetId)) notFound();

  // Učitavamo predmet iz baze
  const predmet = await prisma.predmet.findUnique({
    where: { id: predmetId },
    select: { id: true, naziv: true, opis: true, status: true },
  });

  if (!predmet) notFound();

  // Zatvoren predmet može menjati samo administrator
  if (predmet.status === "ZATVOREN" && uloga !== "ADMINISTRATOR") {
    redirect(`/predmeti/${predmetId}`);
  }

  return (
    <div className="max-w-2xl mx-auto space-y-6">

      {/* ── Breadcrumb navigacija ────────────────────────────────────────── */}
      <div className="flex items-center gap-2 text-sm">
        <Link
          href="/predmeti"
          className="flex items-center gap-1 text-fis-text2 hover:text-fis-text1 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Predmeti
        </Link>
        <span className="text-fis-text3">/</span>
        <Link
          href={`/predmeti/${predmet.id}`}
          className="text-fis-text2 hover:text-fis-text1 transition-colors"
        >
          #{predmet.id}
        </Link>
        <span className="text-fis-text3">/</span>
        <span className="text-fis-text1 font-medium">Izmena</span>
      </div>

      {/* ── Forma za izmenu predmeta ─────────────────────────────────────── */}
      <PredmetForma
        mode="edit"
        pocetneVrednosti={{
          id: predmet.id,
          naziv: predmet.naziv,
          opis: predmet.opis,
        }}
      />

    </div>
  );
}
