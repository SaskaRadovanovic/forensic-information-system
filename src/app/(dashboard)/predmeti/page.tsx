import Link from "next/link";
import { prisma } from "@/lib/prisma";
import { getServerSession } from "next-auth";
import { authOptions } from "@/lib/auth";
import { redirect } from "next/navigation";

export const metadata = { title: "Predmeti — FIS" };

// ─── Helper: srpski naziv faze ───────────────────────────────────────────────

function fazeLabel(faza: string): string {
  const m: Record<string, string> = {
    OTVOREN_SLUCAJ:      "Otvoren slučaj",
    PRIKUPLJANJE_DOKAZA: "Prikupljanje dokaza",
    ANALIZA_DOKAZA:      "Analiza dokaza",
    DONOSENJE_ZAKLJUCKA: "Donošenje zaključka",
    ZATVOREN_SLUCAJ:     "Zatvoren slučaj",
  };
  return m[faza] ?? faza;
}

// ─── Helper: badge klasa za fazu ─────────────────────────────────────────────

function fazaBadge(faza: string): string {
  const m: Record<string, string> = {
    OTVOREN_SLUCAJ:      "fis-badge fis-badge-blue",
    PRIKUPLJANJE_DOKAZA: "fis-badge fis-badge-yellow",
    ANALIZA_DOKAZA:      "fis-badge fis-badge-orange",
    DONOSENJE_ZAKLJUCKA: "fis-badge fis-badge-purple",
    ZATVOREN_SLUCAJ:     "fis-badge fis-badge-green",
  };
  return m[faza] ?? "fis-badge fis-badge-gray";
}

// ─── Stranica za prikaz liste predmeta ──────────────────────────────────────

export default async function PredmetiPage({
  searchParams,
}: {
  searchParams: Promise<{ status?: string; faza?: string; q?: string }>;
}) {
  const session = await getServerSession(authOptions);
  if (!session) redirect("/login");

  const params = await searchParams;
  const q = params.q?.trim();
  const qAsNum = q && !isNaN(parseInt(q, 10)) ? parseInt(q, 10) : undefined;

  const uloga = session.user.uloga;
  const mozeKreirati = uloga === "ADMINISTRATOR" || uloga === "ISTRAZITELJ";

  // ── Fetch predmeta sa opcionalnim filterima ───────────────────────────────
  const predmeti = await prisma.predmet.findMany({
    where: {
      ...(q
        ? { OR: [{ naziv: { contains: q } }, ...(qAsNum ? [{ id: qAsNum }] : [])] }
        : {}),
      ...(params.status ? { status: params.status as any } : {}),
      ...(params.faza   ? { faza:   params.faza   as any } : {}),
    },
    orderBy: { datumOtvaranja: "desc" },
  });

  return (
    <div>
      {/* ── Naslov stranice ─────────────────────────────────────────────── */}
      <div className="page-eyebrow">Upravljanje istragama</div>
      <div className="page-title">Predmeti</div>

      {/* ── Filter traka ────────────────────────────────────────────────── */}
      <form method="GET" className="fis-filter-bar">
        <input
          type="text"
          name="q"
          defaultValue={q ?? ""}
          placeholder="Pretraga po nazivu ili ID-u…"
          className="fis-input"
        />
        <select name="faza" defaultValue={params.faza ?? ""} className="fis-select">
          <option value="">Sve faze</option>
          <option value="OTVOREN_SLUCAJ">Otvoren slučaj</option>
          <option value="PRIKUPLJANJE_DOKAZA">Prikupljanje dokaza</option>
          <option value="ANALIZA_DOKAZA">Analiza dokaza</option>
          <option value="DONOSENJE_ZAKLJUCKA">Donošenje zaključka</option>
          <option value="ZATVOREN_SLUCAJ">Zatvoren slučaj</option>
        </select>
        <select name="status" defaultValue={params.status ?? ""} className="fis-select">
          <option value="">Svi statusi</option>
          <option value="AKTIVAN">Aktivan</option>
          <option value="ZATVOREN">Zatvoren</option>
        </select>
        <button type="submit" className="fis-btn fis-btn-ghost">
          Filtriraj
        </button>
        {(q || params.faza || params.status) && (
          <Link href="/predmeti" className="fis-btn fis-btn-ghost">
            Resetuj
          </Link>
        )}
        {mozeKreirati && (
          <Link href="/predmeti/novi" className="fis-btn fis-btn-primary">
            + Novi predmet
          </Link>
        )}
      </form>

      {/* ── Tabela ili prazno stanje ─────────────────────────────────────── */}
      {predmeti.length === 0 ? (
        <div className="fis-empty" style={{ border: "1px dashed var(--color-fis-border)", padding: "60px 40px" }}>
          {q || params.faza || params.status
            ? "Nema rezultata za zadate filtere."
            : "Kreirajte prvi predmet klikom na dugme iznad."}
        </div>
      ) : (
        <div className="fis-card">
          <table className="fis-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Naziv predmeta</th>
                <th>Faza</th>
                <th>Otvoreno</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {predmeti.map((p) => {
                const zatvoren = p.status === "ZATVOREN";
                return (
                  <tr key={p.id}>
                    <td>{p.id}</td>
                    <td style={{ color: "var(--color-fis-text1)" }}>
                      {p.naziv}
                      {zatvoren && (
                        <span className="fis-badge fis-badge-gray" style={{ marginLeft: 10 }}>
                          Zatvoren
                        </span>
                      )}
                    </td>
                    <td>
                      <span className={fazaBadge(p.faza)}>{fazeLabel(p.faza)}</span>
                    </td>
                    <td>
                      {new Date(p.datumOtvaranja).toLocaleDateString("sr-RS", {
                        day: "2-digit",
                        month: "2-digit",
                        year: "numeric",
                      })}
                    </td>
                    <td>
                      <Link
                        href={`/predmeti/${p.id}`}
                        className={`fis-btn fis-btn-sm ${zatvoren ? "fis-btn-ghost" : "fis-btn-outline"}`}
                      >
                        {zatvoren ? "Arhiva →" : "Detalji →"}
                      </Link>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
