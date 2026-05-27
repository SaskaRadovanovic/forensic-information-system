// ─── Centar obaveštenja ────────────────────────────────────────────────────────
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/prisma";
import Link from "next/link";
import { Bell } from "lucide-react";
import { oznaciProitano as _oznaciProitano, oznaciSvaProitana as _oznaciSvaProitana } from "@/app/(dashboard)/analize/actions";

type FormAction = (formData: FormData) => Promise<void>;
const oznaciSvaProitana = _oznaciSvaProitana as unknown as FormAction;
function oznaciProitano(id: number) { return _oznaciProitano.bind(null, id) as unknown as FormAction; }

export const metadata = { title: "Obaveštenja — FIS" };

function tipNaziv(tip: string): string {
  const mapa: Record<string, string> = {
    DODELJEN:        "Dodeljen zahtev",
    PRERASPOREDJEN:  "Preraspoređen zahtev",
    ROK_BLIZI:       "Rok se bliži",
    REZULTAT_UNET:   "Rezultat unet",
    VERIFIKOVAN:     "Rezultat verifikovan",
    ZAVRSEN:         "Analiza završena",
    ODBIJEN:         "Zahtev odbijen",
    ZAPOCETA:        "Analiza započeta",
  };
  return mapa[tip] ?? tip;
}

export default async function ObavestenjaPage() {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const korisnikId = parseInt(session.user.id, 10);

  const obavestenja = await prisma.obavestenje.findMany({
    where:   { korisnikId },
    orderBy: { datumVreme: "desc" },
    include: { zahtev: { select: { id: true } } },
  });

  const brojNeprocitanih = obavestenja.filter((o) => !o.procitano).length;

  function fmtVreme(datum: Date) {
    return new Date(datum).toLocaleString("sr-RS");
  }

  return (
    <div style={{ maxWidth: 720, margin: "0 auto" }}>

      {/* ── Zaglavlje ─────────────────────────────────────────────────────── */}
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 24 }}>
        <div>
          <p className="page-eyebrow">Sistem</p>
          <h1 className="page-title" style={{ marginBottom: 0 }}>Obaveštenja</h1>
          {brojNeprocitanih > 0 && (
            <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#999999", marginTop: 4 }}>
              {brojNeprocitanih} nepročitan{brojNeprocitanih === 1 ? "o" : "ih"}
            </p>
          )}
        </div>

        {brojNeprocitanih > 0 && (
          <form action={oznaciSvaProitana}>
            <button
              type="submit"
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
              }}
            >
              Označi sve pročitano
            </button>
          </form>
        )}
      </div>

      {/* ── Lista obaveštenja ─────────────────────────────────────────────── */}
      {obavestenja.length === 0 ? (
        <div style={{ border: "1px solid #2a2a2a", background: "#111111", padding: "60px 32px", textAlign: "center" }}>
          <Bell style={{ width: 32, height: 32, color: "#555555", margin: "0 auto 12px" }} />
          <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 12, color: "#555555", textTransform: "uppercase", letterSpacing: 1 }}>
            Nema obaveštenja
          </p>
        </div>
      ) : (
        <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
          {obavestenja.map((o) => {
            const oznaciAction = oznaciProitano(o.id);
            return (
              <div
                key={o.id}
                style={{
                  border: `1px solid ${o.procitano ? "#2a2a2a" : "rgba(245,197,24,0.25)"}`,
                  background: o.procitano ? "#111111" : "rgba(245,197,24,0.04)",
                  padding: "14px 18px",
                  display: "flex",
                  alignItems: "flex-start",
                  justifyContent: "space-between",
                  gap: 16,
                }}
              >
                <div style={{ flex: 1, minWidth: 0 }}>
                  {/* Tip + novo badge */}
                  <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 4 }}>
                    <span style={{ fontFamily: "var(--font-mono), monospace", fontSize: 9, textTransform: "uppercase", letterSpacing: "1.5px", color: "#555555" }}>
                      {tipNaziv(o.tip)}
                    </span>
                    {!o.procitano && (
                      <span className="fis-badge" style={{ borderColor: "rgba(245,197,24,0.4)", color: "#f5c518", fontSize: 9, padding: "1px 5px" }}>
                        Novo
                      </span>
                    )}
                  </div>

                  {/* Sadržaj */}
                  <p style={{ fontSize: 13, color: o.procitano ? "#999999" : "#f0ede8", marginBottom: 6 }}>
                    {o.sadrzaj}
                  </p>

                  {/* Meta */}
                  <div style={{ display: "flex", alignItems: "center", gap: 16 }}>
                    <span style={{ fontFamily: "var(--font-mono), monospace", fontSize: 10, color: "#555555" }}>
                      {fmtVreme(o.datumVreme)}
                    </span>
                    {o.zahtev && (
                      <Link
                        href={`/analize/${o.zahtev.id}`}
                        style={{ fontFamily: "var(--font-mono), monospace", fontSize: 10, color: "#3b82f6", textDecoration: "none" }}
                      >
                        Zahtev #{o.zahtev.id} →
                      </Link>
                    )}
                  </div>
                </div>

                {/* Označi kao pročitano */}
                {!o.procitano && (
                  <form action={oznaciAction}>
                    <button
                      type="submit"
                      title="Označi kao pročitano"
                      style={{
                        flexShrink: 0,
                        background: "transparent",
                        border: "1px solid #2a2a2a",
                        color: "#555555",
                        cursor: "pointer",
                        padding: "5px 8px",
                        fontFamily: "var(--font-mono), monospace",
                        fontSize: 10,
                        textTransform: "uppercase",
                        letterSpacing: 1,
                      }}
                    >
                      ✓
                    </button>
                  </form>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
