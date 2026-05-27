"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { unesRezultat } from "@/app/(dashboard)/analize/actions";
import { TipAnalize } from "@prisma/client";

interface Sekcija {
  kljuc: string;
  naziv: string;
  placeholder: string;
  obavezno?: boolean;
}

const SEKCIJE_PO_TIPU: Record<TipAnalize, Sekcija[]> = {
  BALISTICKA: [
    { kljuc: "identifikacijaOruzja",   naziv: "Identifikacija oružja",       placeholder: "Vrsta, marka, model, serijski broj, karakteristike...", obavezno: true },
    { kljuc: "balistickaPodudarnost",  naziv: "Balistička podudarnost",      placeholder: "Rezultati poređenja projektila / čaura sa poznatim uzorkom..." },
    { kljuc: "tragoviBaruta",          naziv: "Tragovi baruta / GSR",         placeholder: "Prisustvo ili odsustvo gunshot residue, lokacija tragova..." },
    { kljuc: "metodologija",           naziv: "Primenjena metodologija",      placeholder: "Instrumenti i metode korišćene u analizi..." },
    { kljuc: "zakljucak",              naziv: "Zaključak",                    placeholder: "Forenzički zaključak analize...", obavezno: true },
  ],
  DNK: [
    { kljuc: "profilDNK",              naziv: "DNK profil",                   placeholder: "Dobijeni aleli po lokusima, tip profila (STR/Y-STR/mtDNK)...", obavezno: true },
    { kljuc: "podudarnost",            naziv: "Podudarnost sa uzorcima",      placeholder: "Rezultat poređenja sa referentnim uzorcima ili bazom podataka..." },
    { kljuc: "statistickiIskaz",       naziv: "Statistički iskaz",            placeholder: "LR vrednost, RMP, ili procenat verovatnoće podudarnosti..." },
    { kljuc: "zakljucak",              naziv: "Zaključak",                    placeholder: "Forenzički zaključak DNK analize...", obavezno: true },
  ],
  DIGITALNA: [
    { kljuc: "pronadjeniArtifakti",    naziv: "Pronađeni artefakti",          placeholder: "Fajlovi, komunikacije, fotografije, lozinke, nalozi...", obavezno: true },
    { kljuc: "rekuperisaniPodaci",     naziv: "Rekuperisani podaci",          placeholder: "Obrisani fajlovi ili podaci koji su uspešno oporavljeni..." },
    { kljuc: "hronologija",            naziv: "Digitalna hronologija",        placeholder: "Vremenski sled aktivnosti relevantnih za predmet..." },
    { kljuc: "metapodaci",             naziv: "Analiza metapodataka",         placeholder: "EXIF podaci, log fajlovi, sistemski događaji..." },
    { kljuc: "zakljucak",              naziv: "Zaključak",                    placeholder: "Forenzički zaključak digitalne analize...", obavezno: true },
  ],
  HEMIJSKA: [
    { kljuc: "identifikovaneSupstance",naziv: "Identifikovane supstance",     placeholder: "Hemijski sastav, identifikovana jedinjenja, čistoća...", obavezno: true },
    { kljuc: "rezultatiTestova",       naziv: "Rezultati analitičkih testova", placeholder: "Numerički rezultati, hromatogrami, spektrometrijski podaci..." },
    { kljuc: "metodologija",           naziv: "Primenjena metodologija",      placeholder: "GC-MS, HPLC, FTIR, XRF — opis korišćenih instrumenata..." },
    { kljuc: "zakljucak",              naziv: "Zaključak",                    placeholder: "Forenzički zaključak hemijske analize...", obavezno: true },
  ],
  TOKSIKOLOSKA: [
    { kljuc: "identifikovaniToksini",  naziv: "Identifikovane supstance",     placeholder: "Naziv supstance, hemijska grupa, nomenklatura...", obavezno: true },
    { kljuc: "koncentracije",          naziv: "Koncentracije",                placeholder: "Izmerene koncentracije u biološkim uzorcima (mg/L, ng/mL)..." },
    { kljuc: "medicinskiUticaj",       naziv: "Toksikološki / medicinski uticaj", placeholder: "Procena uticaja na organizam pri datim koncentracijama..." },
    { kljuc: "zakljucak",              naziv: "Zaključak",                    placeholder: "Forenzički zaključak toksikološke analize...", obavezno: true },
  ],
  DOKUMENTOLOSKA: [
    { kljuc: "autenticnost",           naziv: "Ocena autentičnosti",          placeholder: "Da li je dokument autentičan, sa objašnjenjem osnova za ocenu...", obavezno: true },
    { kljuc: "znaciKrivotvorenja",     naziv: "Znaci krivotvorenja / izmene", placeholder: "Identifikovane izmene teksta, falsifikovani potpisi, lažni pečati..." },
    { kljuc: "materijalnaAnaliza",     naziv: "Analiza materijala",           placeholder: "Papir, mastilo, štampač, datum nastanka dokumenta..." },
    { kljuc: "zakljucak",              naziv: "Zaključak",                    placeholder: "Forenzički zaključak dokumentološke analize...", obavezno: true },
  ],
  DRUGA: [
    { kljuc: "metodologija",           naziv: "Primenjena metodologija",      placeholder: "Opišite korišćene metode i tehnike analize...", obavezno: true },
    { kljuc: "nalaz",                  naziv: "Nalaz",                        placeholder: "Detaljan opis svih nalaza i opažanja...", obavezno: true },
    { kljuc: "zakljucak",              naziv: "Zaključak",                    placeholder: "Forenzički zaključak analize...", obavezno: true },
  ],
};

interface RezultatFormProps {
  zahtevId: number;
  tipAnalize: TipAnalize;
}

function inputStil(): React.CSSProperties {
  return {
    width: "100%",
    background: "#181818",
    border: "1px solid #2a2a2a",
    color: "#f0ede8",
    fontFamily: "var(--font-mono), monospace",
    fontSize: 13,
    padding: "9px 12px",
    outline: "none",
    resize: "vertical" as const,
    minHeight: 90,
  };
}

function labelStil(): React.CSSProperties {
  return {
    display: "block",
    fontFamily: "var(--font-mono), monospace",
    fontSize: 10,
    textTransform: "uppercase" as const,
    letterSpacing: "1.5px",
    color: "#555555",
    marginBottom: 6,
  };
}

export function RezultatForm({ zahtevId, tipAnalize }: RezultatFormProps) {
  const router = useRouter();
  const sekcije = SEKCIJE_PO_TIPU[tipAnalize] ?? SEKCIJE_PO_TIPU.DRUGA;

  const [polja, setPolja] = useState<Record<string, string>>(
    Object.fromEntries(sekcije.map((s) => [s.kljuc, ""]))
  );
  const [greska, setGreska] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  function formirajSadrzaj(): string {
    return sekcije
      .map((s) => {
        const vrednost = (polja[s.kljuc] ?? "").trim();
        return `=== ${s.naziv.toUpperCase()} ===\n${vrednost || "(nije uneto)"}`;
      })
      .join("\n\n");
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();

    const obaveznaPolja = sekcije.filter((s) => s.obavezno);
    for (const p of obaveznaPolja) {
      if (!(polja[p.kljuc] ?? "").trim()) {
        setGreska(`Polje "${p.naziv}" je obavezno.`);
        return;
      }
    }

    setLoading(true);
    setGreska(null);

    const sadrzaj = formirajSadrzaj();
    const formData = new FormData();
    formData.append("zahtevId", String(zahtevId));
    formData.append("sadrzaj", sadrzaj);

    const rezultat = await unesRezultat(formData);

    if (!rezultat.ok) {
      setGreska(rezultat.greska);
      setLoading(false);
      return;
    }

    router.push(`/analize/${zahtevId}`);
  }

  return (
    <div style={{ border: "1px solid #2a2a2a", background: "#111111" }}>
      {/* Header */}
      <div style={{ padding: "20px 24px", borderBottom: "1px solid #2a2a2a" }}>
        <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 9, textTransform: "uppercase", letterSpacing: "2px", color: "#555555", marginBottom: 4 }}>
          Unos rezultata
        </p>
        <h2 style={{ fontFamily: "var(--font-display), 'Bebas Neue', sans-serif", fontSize: 24, letterSpacing: 2, color: "#f0ede8", lineHeight: 1 }}>
          Izveštaj veštačenja
        </h2>
      </div>

      <form onSubmit={onSubmit} style={{ padding: "24px" }}>

        {greska && (
          <div style={{ border: "1px solid rgba(239,68,68,0.4)", background: "rgba(239,68,68,0.08)", padding: "10px 14px", marginBottom: 20, fontFamily: "var(--font-mono), monospace", fontSize: 12, color: "#ef4444" }}>
            {greska}
          </div>
        )}

        <div style={{ display: "flex", flexDirection: "column", gap: 18 }}>
          {sekcije.map((sekcija) => (
            <div key={sekcija.kljuc}>
              <label style={labelStil()}>
                {sekcija.naziv}
                {sekcija.obavezno && <span style={{ color: "#ef4444", marginLeft: 4 }}>*</span>}
              </label>
              <textarea
                value={polja[sekcija.kljuc] ?? ""}
                onChange={(e) => setPolja((prev) => ({ ...prev, [sekcija.kljuc]: e.target.value }))}
                disabled={loading}
                placeholder={sekcija.placeholder}
                rows={4}
                style={inputStil()}
              />
            </div>
          ))}
        </div>

        <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 10, color: "#555555", marginTop: 14 }}>
          Polja označena sa <span style={{ color: "#ef4444" }}>*</span> su obavezna. Rezultat se čuva trajno i ne može biti izmenjen.
        </p>

        <div style={{ display: "flex", justifyContent: "flex-end", gap: 10, paddingTop: 16, borderTop: "1px solid #2a2a2a", marginTop: 16 }}>
          <button
            type="button"
            onClick={() => router.back()}
            disabled={loading}
            style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, textTransform: "uppercase", letterSpacing: 1, padding: "9px 18px", border: "1px solid #3a3a3a", background: "transparent", color: "#999999", cursor: "pointer" }}
          >
            Otkaži
          </button>
          <button
            type="submit"
            disabled={loading}
            style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, textTransform: "uppercase", letterSpacing: 1, padding: "9px 18px", border: "none", background: loading ? "#c9a015" : "#f5c518", color: "#000", cursor: loading ? "not-allowed" : "pointer" }}
          >
            {loading ? "Čuvanje..." : "Predaj nalaz"}
          </button>
        </div>
      </form>
    </div>
  );
}
