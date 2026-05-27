import { notFound, redirect } from "next/navigation";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { PredmetForma } from "@/components/predmeti/PredmetForma";

export const metadata = { title: "Izmena predmeta — FIS" };

export default async function IzmeniPredmetPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const uloga = session.user.uloga;
  if (uloga !== "ADMINISTRATOR" && uloga !== "ISTRAZITELJ") {
    redirect("/predmeti");
  }

  const { id } = await params;
  const predmetId = parseInt(id, 10);
  if (isNaN(predmetId)) notFound();

  const predmet = await prisma.predmet.findUnique({
    where: { id: predmetId },
    select: { id: true, naziv: true, opis: true, status: true },
  });

  if (!predmet) notFound();

  if (predmet.status === "ZATVOREN" && uloga !== "ADMINISTRATOR") {
    redirect(`/predmeti/${predmetId}`);
  }

  return (
    <div>
      {/* ── Breadcrumb ─────────────────────────────────────────────────── */}
      <div className="fis-breadcrumb">
        <Link href="/predmeti" className="fis-btn fis-btn-ghost fis-btn-sm">
          ← Predmeti
        </Link>
        <span style={{ color: "var(--color-fis-text3)", fontFamily: "var(--font-mono)", fontSize: 11 }}>/</span>
        <Link href={`/predmeti/${predmet.id}`} className="fis-btn fis-btn-ghost fis-btn-sm">
          #{predmet.id}
        </Link>
      </div>

      {/* ── Naslov stranice ─────────────────────────────────────────────── */}
      <div className="page-eyebrow">Izmena</div>
      <div className="page-title">Izmeni predmet</div>

      {/* ── Forma ───────────────────────────────────────────────────────── */}
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
