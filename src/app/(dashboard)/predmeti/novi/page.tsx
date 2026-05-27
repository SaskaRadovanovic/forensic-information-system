// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { PredmetForma } from "@/components/predmeti/PredmetForma";

// ─── Metadata stranice ───────────────────────────────────────────────────────

export const metadata = { title: "Novi predmet — FIS" };

// ─── Stranica za kreiranje novog predmeta ────────────────────────────────────

export default async function NoviPredmetPage() {
  // Provera autentifikacije
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  // Samo ADMINISTRATOR i ISTRAZITELJ mogu kreirati predmete
  const uloga = session.user.uloga;
  if (uloga !== "ADMINISTRATOR" && uloga !== "ISTRAZITELJ") {
    redirect("/predmeti");
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
        <span className="text-fis-text1 font-medium">Novi predmet</span>
      </div>

      {/* ── Forma za kreiranje predmeta ──────────────────────────────────── */}
      <PredmetForma mode="create" />

    </div>
  );
}
