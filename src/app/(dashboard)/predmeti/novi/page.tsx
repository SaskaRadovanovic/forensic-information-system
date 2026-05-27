import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import Link from "next/link";
import { PredmetForma } from "@/components/predmeti/PredmetForma";

export const metadata = { title: "Novi predmet — FIS" };

export default async function NoviPredmetPage() {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const uloga = session.user.uloga;
  if (uloga !== "ADMINISTRATOR" && uloga !== "ISTRAZITELJ") {
    redirect("/predmeti");
  }

  return (
    <div>
      {/* ── Breadcrumb ─────────────────────────────────────────────────── */}
      <div className="fis-breadcrumb">
        <Link href="/predmeti" className="fis-btn fis-btn-ghost fis-btn-sm">
          ← Predmeti
        </Link>
      </div>

      {/* ── Naslov stranice ─────────────────────────────────────────────── */}
      <div className="page-eyebrow">Kreiranje</div>
      <div className="page-title">Novi predmet</div>

      {/* ── Forma ───────────────────────────────────────────────────────── */}
      <PredmetForma mode="create" />
    </div>
  );
}
