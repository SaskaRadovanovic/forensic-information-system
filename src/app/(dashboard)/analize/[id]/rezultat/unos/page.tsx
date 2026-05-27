// ─── Unos rezultata analize (veštak) ─────────────────────────────────────────
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect, notFound } from "next/navigation";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { RezultatForm } from "@/components/analize/RezultatForm";

export const metadata = { title: "Unos rezultata — FIS" };

type Params = { params: Promise<{ id: string }> };

export default async function UnosRezultataPage({ params }: Params) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  if (session.user.uloga !== "VESTAK") redirect("/analize");

  const { id } = await params;
  const zahtevId = parseInt(id, 10);
  if (isNaN(zahtevId)) notFound();

  const korisnikId = parseInt(session.user.id, 10);

  const zahtev = await prisma.zahtevZaAnalizu.findUnique({
    where: { id: zahtevId },
    select: { id: true, status: true, vestakId: true, rezultat: true, tipAnalize: true },
  });
  if (!zahtev) notFound();

  if (zahtev.vestakId !== korisnikId || zahtev.status !== "U_TOKU" || zahtev.rezultat)
    redirect(`/analize/${zahtevId}`);

  return (
    <div className="max-w-3xl mx-auto">
      <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 24, fontFamily: "var(--font-mono), monospace", fontSize: 12 }}>
        <Link href={`/analize/${zahtevId}`} style={{ display: "flex", alignItems: "center", gap: 6, color: "#999999", textDecoration: "none" }}>
          <ArrowLeft style={{ width: 14, height: 14 }} />
          Zahtev #{zahtevId}
        </Link>
        <span style={{ color: "#3a3a3a" }}>/</span>
        <span style={{ color: "#f0ede8" }}>Unos rezultata</span>
      </div>

      <RezultatForm zahtevId={zahtevId} tipAnalize={zahtev.tipAnalize} />
    </div>
  );
}
