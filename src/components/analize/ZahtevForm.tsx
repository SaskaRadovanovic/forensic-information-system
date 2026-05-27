"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { kreirajZahtev, izmeniZahtev } from "@/app/(dashboard)/analize/actions";

// ─── Tipovi analize ───────────────────────────────────────────────────────────

const TIPOVI_ANALIZE = [
  { vrednost: "BALISTICKA",     naziv: "Balistička"          },
  { vrednost: "DNK",            naziv: "DNK"                 },
  { vrednost: "DIGITALNA",      naziv: "Digitalna forenzika" },
  { vrednost: "HEMIJSKA",       naziv: "Hemijska"            },
  { vrednost: "TOKSIKOLOSKA",   naziv: "Toksikološka"        },
  { vrednost: "DOKUMENTOLOSKA", naziv: "Dokumentološka"      },
  { vrednost: "DRUGA",          naziv: "Druga"               },
] as const;

// ─── Predefinisana polja po tipu analize ─────────────────────────────────────

interface TipPolje {
  kljuc: string;
  naziv: string;
  placeholder: string;
  viseLinijsko?: boolean;
}

const POLJA_PO_TIPU: Record<string, TipPolje[]> = {
  BALISTICKA: [
    { kljuc: "vrstaOruzja",      naziv: "Vrsta oružja",             placeholder: "npr. pištolj, puška, sačmarica, automatsko..." },
    { kljuc: "kalibar",          naziv: "Kalibar",                  placeholder: "npr. 9mm Parabellum, .38 Special, 7.62×39..." },
    { kljuc: "brojProjektila",   naziv: "Broj projektila / čaura",  placeholder: "npr. 3 projektila, 2 čaure..." },
    { kljuc: "zahtevaneAnalize", naziv: "Zahtevane analize",        placeholder: "Identifikacija oružja, balistička podudarnost, tragovi baruta, GSR...", viseLinijsko: true },
  ],
  DNK: [
    { kljuc: "vrstaUzorka",      naziv: "Vrsta uzorka",             placeholder: "npr. bris sluzokože, krv, kosa sa korenom, tkivo..." },
    { kljuc: "brojUzoraka",      naziv: "Broj uzoraka",             placeholder: "npr. 1 referentni + 3 tragovna uzorka" },
    { kljuc: "metodaAnalize",    naziv: "Metoda / baza poređenja",  placeholder: "npr. STR profil (autosomal), Y-STR, mtDNK, CODIS..." },
    { kljuc: "zahtevaneAnalize", naziv: "Zahtevane analize",        placeholder: "Podudarnost profila, pretraga baze, identifikacija osobe...", viseLinijsko: true },
  ],
  DIGITALNA: [
    { kljuc: "vrstaUredjaja",    naziv: "Vrsta uređaja",            placeholder: "npr. mobilni telefon, laptop, SSD disk, USB, DVR..." },
    { kljuc: "operativniSistem", naziv: "OS / platforma",           placeholder: "npr. Windows 10, Android 12, iOS 16, Linux..." },
    { kljuc: "stanje",           naziv: "Stanje uređaja",           placeholder: "npr. funkcionalan, oštećen, zaključan, šifrovan..." },
    { kljuc: "zahtevaneAnalize", naziv: "Zahtevane analize",        placeholder: "Oporavak obrisanih fajlova, analiza komunikacija, GPS istorija, lozinke...", viseLinijsko: true },
  ],
  HEMIJSKA: [
    { kljuc: "vrstaUzorka",      naziv: "Vrsta uzorka",             placeholder: "npr. bela supstanca u prahu, tečnost, gas, eksploziv..." },
    { kljuc: "kolicina",         naziv: "Količina / zapremina",     placeholder: "npr. ~2g, 50ml, nepoznato..." },
    { kljuc: "metodaAnalize",    naziv: "Predložena metodologija",  placeholder: "npr. GC-MS, HPLC, ATR-FTIR, XRF spektrometrija..." },
    { kljuc: "zahtevaneAnalize", naziv: "Zahtevane analize",        placeholder: "Identifikacija supstance, čistoća, koncentracija, poreklo...", viseLinijsko: true },
  ],
  TOKSIKOLOSKA: [
    { kljuc: "vrstaUzorka",            naziv: "Vrsta biološkog uzorka",   placeholder: "npr. venozna krv, urin, gastični sadržaj, tkivo jetre..." },
    { kljuc: "supstanceZaTestiranje",  naziv: "Grupe supstanci za test",  placeholder: "npr. opiati, benzodiazepini, alkohol, organofosfati, teški metali..." },
    { kljuc: "zahtevaneAnalize",       naziv: "Zahtevane analize",        placeholder: "Preliminarni imunohemijski skrining, kvantitativna GC-MS, LC-MS/MS analiza...", viseLinijsko: true },
  ],
  DOKUMENTOLOSKA: [
    { kljuc: "vrstaDokumenta",   naziv: "Vrsta dokumenta",          placeholder: "npr. lična karta, ugovor, testamenat, ček, diploma..." },
    { kljuc: "sumnjaNa",         naziv: "Sumnja na",                placeholder: "npr. falsifikovanje potpisa, izmena teksta, lažan pečat, krivotvoreni dokument..." },
    { kljuc: "zahtevaneAnalize", naziv: "Zahtevane analize",        placeholder: "Veštačenje potpisa, analiza mastila, analiza papira, UV/IR ispitivanje...", viseLinijsko: true },
  ],
  DRUGA: [
    { kljuc: "opisPredmeta",     naziv: "Opis predmeta analize",    placeholder: "Opišite šta je potrebno analizirati..." },
    { kljuc: "zahtevaneAnalize", naziv: "Zahtevane analize",        placeholder: "Navedite šta očekujete od veštačenja...", viseLinijsko: true },
  ],
};

// ─── Zod shema ────────────────────────────────────────────────────────────────

const shema = z.object({
  predmetId:          z.string().min(1, "Predmet je obavezan."),
  dokazId:            z.string().min(1, "Dokaz je obavezan."),
  tipAnalize:         z.string().min(1, "Tip analize je obavezan."),
  rok:                z.string().min(1, "Rok je obavezan."),
  datumPocetka:       z.string().optional(),
  pragUpozorenjaDana: z.string().optional(),
});

type FormPodaci = z.infer<typeof shema>;

// ─── Tipovi propa ─────────────────────────────────────────────────────────────

interface Predmet { id: number; naziv: string; }
interface Dokaz   { id: number; naziv: string; sifraDokaza: string; predmetId: number; }

interface ZahtevFormProps {
  mode: "create" | "edit";
  predmeti: Predmet[];
  dokazi: Dokaz[];
  pocetnePodaci?: {
    zahtevId: number;
    predmetId: number;
    dokazId: number;
    tipAnalize: string;
    opis?: string | null;
    datumPocetka?: Date | null;
    rok?: Date | null;
    pragUpozorenjaDana: number;
  };
}

function datumZaInput(datum?: Date | null): string {
  if (!datum) return "";
  return datum.toISOString().split("T")[0];
}

function inputStil(disabled?: boolean): React.CSSProperties {
  return {
    width: "100%",
    background: disabled ? "#141414" : "#181818",
    border: "1px solid #2a2a2a",
    color: disabled ? "#555555" : "#f0ede8",
    fontFamily: "var(--font-mono), monospace",
    fontSize: 13,
    padding: "9px 12px",
    outline: "none",
    cursor: disabled ? "not-allowed" : "text",
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

// ─── Komponenta ───────────────────────────────────────────────────────────────

export function ZahtevForm({ mode, predmeti, dokazi, pocetnePodaci }: ZahtevFormProps) {
  const router = useRouter();
  const [serverGreska, setServerGreska] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const [odabranPredmetId, setOdabranPredmetId] = useState<number | null>(pocetnePodaci?.predmetId ?? null);
  const [odabranTip, setOdabranTip] = useState<string>(pocetnePodaci?.tipAnalize ?? "");

  // Parsiramo postojeći opis u polja (za edit mode)
  function parseOpisUPolja(opis: string | null | undefined): Record<string, string> {
    if (!opis) return {};
    const rezultat: Record<string, string> = {};
    const linije = opis.split("\n");
    for (const linija of linije) {
      const idx = linija.indexOf(": ");
      if (idx > 0) {
        const kljuc = linija.substring(0, idx).trim().toLowerCase().replace(/\s+/g, "");
        const vrednost = linija.substring(idx + 2).trim();
        rezultat[kljuc] = vrednost;
      }
    }
    return rezultat;
  }

  const pocetnaPolja = parseOpisUPolja(pocetnePodaci?.opis);
  const [dodatnaPolja, setDodatnaPolja] = useState<Record<string, string>>(pocetnaPolja);

  const filtriranDokazi = dokazi.filter((d) => d.predmetId === odabranPredmetId);

  const { register, handleSubmit, formState: { errors }, setValue } = useForm<FormPodaci>({
    resolver: zodResolver(shema),
    defaultValues: {
      predmetId:          pocetnePodaci ? String(pocetnePodaci.predmetId) : "",
      dokazId:            pocetnePodaci ? String(pocetnePodaci.dokazId) : "",
      tipAnalize:         pocetnePodaci?.tipAnalize ?? "",
      rok:                datumZaInput(pocetnePodaci?.rok),
      datumPocetka:       datumZaInput(pocetnePodaci?.datumPocetka),
      pragUpozorenjaDana: String(pocetnePodaci?.pragUpozorenjaDana ?? 3),
    },
  });

  function handlePredmetChange(e: React.ChangeEvent<HTMLSelectElement>) {
    const id = parseInt(e.target.value, 10);
    setOdabranPredmetId(isNaN(id) ? null : id);
    setValue("dokazId", "");
  }

  function handleTipChange(e: React.ChangeEvent<HTMLSelectElement>) {
    const noviTip = e.target.value;
    setOdabranTip(noviTip);
    setDodatnaPolja({});
    register("tipAnalize").onChange(e);
  }

  function formirajOpis(): string {
    const polja = POLJA_PO_TIPU[odabranTip] ?? [];
    const linije: string[] = [];
    for (const polje of polja) {
      const vrednost = (dodatnaPolja[polje.kljuc] ?? "").trim();
      if (vrednost) {
        linije.push(`${polje.naziv}: ${vrednost}`);
      }
    }
    return linije.join("\n");
  }

  async function onSubmit(podaci: FormPodaci) {
    setLoading(true);
    setServerGreska(null);

    const formData = new FormData();
    formData.append("predmetId", podaci.predmetId);
    formData.append("dokazId", podaci.dokazId);
    formData.append("tipAnalize", podaci.tipAnalize);
    formData.append("rok", podaci.rok);
    if (podaci.datumPocetka) formData.append("datumPocetka", podaci.datumPocetka);
    if (podaci.pragUpozorenjaDana) formData.append("pragUpozorenjaDana", podaci.pragUpozorenjaDana);

    const opis = formirajOpis();
    if (opis) formData.append("opis", opis);

    let rezultat;
    if (mode === "edit" && pocetnePodaci) {
      formData.append("zahtevId", String(pocetnePodaci.zahtevId));
      rezultat = await izmeniZahtev(formData);
    } else {
      rezultat = await kreirajZahtev(formData);
    }

    if (!rezultat.ok) {
      setServerGreska(rezultat.greska);
      setLoading(false);
      return;
    }

    if (mode === "edit" && pocetnePodaci) {
      router.push(`/analize/${pocetnePodaci.zahtevId}`);
    } else if ("id" in rezultat) {
      router.push(`/analize/${rezultat.id}`);
    }
  }

  const poljaZaTip = POLJA_PO_TIPU[odabranTip] ?? [];

  return (
    <div style={{ border: "1px solid #2a2a2a", background: "#111111" }}>
      {/* Header */}
      <div style={{ padding: "20px 24px", borderBottom: "1px solid #2a2a2a" }}>
        <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 9, textTransform: "uppercase", letterSpacing: "2px", color: "#555555", marginBottom: 4 }}>
          {mode === "edit" ? "Izmena zahteva" : "Novi zahtev"}
        </p>
        <h2 style={{ fontFamily: "var(--font-display), 'Bebas Neue', sans-serif", fontSize: 24, letterSpacing: 2, color: "#f0ede8", lineHeight: 1 }}>
          {mode === "edit" ? "Izmena zahteva za analizu" : "Kreiranje zahteva za analizu"}
        </h2>
      </div>

      {/* Body */}
      <form onSubmit={handleSubmit(onSubmit)} style={{ padding: "24px" }}>

        {serverGreska && (
          <div style={{ border: "1px solid rgba(239,68,68,0.4)", background: "rgba(239,68,68,0.08)", padding: "10px 14px", marginBottom: 20, fontFamily: "var(--font-mono), monospace", fontSize: 12, color: "#ef4444" }}>
            {serverGreska}
          </div>
        )}

        {/* Grid: predmet + dokaz */}
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 16 }}>
          <div>
            <label style={labelStil()}>Predmet <span style={{ color: "#ef4444" }}>*</span></label>
            <select
              {...register("predmetId")}
              disabled={loading || mode === "edit"}
              onChange={(e) => { register("predmetId").onChange(e); handlePredmetChange(e); }}
              style={{ ...inputStil(loading || mode === "edit"), cursor: loading || mode === "edit" ? "not-allowed" : "pointer" }}
            >
              <option value="">— Odaberi predmet —</option>
              {predmeti.map((p) => (
                <option key={p.id} value={String(p.id)}>{p.naziv}</option>
              ))}
            </select>
            {errors.predmetId && <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#ef4444", marginTop: 4 }}>{errors.predmetId.message}</p>}
          </div>

          <div>
            <label style={labelStil()}>Dokaz <span style={{ color: "#ef4444" }}>*</span></label>
            <select
              {...register("dokazId")}
              disabled={loading || !odabranPredmetId || mode === "edit"}
              style={{ ...inputStil(loading || !odabranPredmetId || mode === "edit"), cursor: loading || !odabranPredmetId || mode === "edit" ? "not-allowed" : "pointer" }}
            >
              <option value="">— Odaberi dokaz —</option>
              {filtriranDokazi.map((d) => (
                <option key={d.id} value={String(d.id)}>[{d.sifraDokaza}] {d.naziv}</option>
              ))}
            </select>
            {errors.dokazId && <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#ef4444", marginTop: 4 }}>{errors.dokazId.message}</p>}
            {!odabranPredmetId && mode === "create" && (
              <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 10, color: "#555555", marginTop: 4 }}>Najpre odaberite predmet.</p>
            )}
          </div>
        </div>

        {/* Tip analize */}
        <div style={{ marginBottom: 20 }}>
          <label style={labelStil()}>Tip analize <span style={{ color: "#ef4444" }}>*</span></label>
          <select
            {...register("tipAnalize")}
            disabled={loading}
            onChange={(e) => { handleTipChange(e); }}
            style={{ ...inputStil(loading), cursor: loading ? "not-allowed" : "pointer" }}
          >
            <option value="">— Odaberi tip —</option>
            {TIPOVI_ANALIZE.map((t) => (
              <option key={t.vrednost} value={t.vrednost}>{t.naziv}</option>
            ))}
          </select>
          {errors.tipAnalize && <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#ef4444", marginTop: 4 }}>{errors.tipAnalize.message}</p>}
        </div>

        {/* Predefinisana polja po tipu */}
        {poljaZaTip.length > 0 && (
          <div style={{ border: "1px solid #2a2a2a", background: "#0d0d0d", padding: "16px 18px", marginBottom: 20 }}>
            <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 9, textTransform: "uppercase", letterSpacing: "2px", color: "#f5c518", marginBottom: 14 }}>
              Parametri analize
            </p>
            <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              {poljaZaTip.map((polje) => (
                <div key={polje.kljuc}>
                  <label style={labelStil()}>{polje.naziv}</label>
                  {polje.viseLinijsko ? (
                    <textarea
                      value={dodatnaPolja[polje.kljuc] ?? ""}
                      onChange={(e) => setDodatnaPolja((prev) => ({ ...prev, [polje.kljuc]: e.target.value }))}
                      disabled={loading}
                      placeholder={polje.placeholder}
                      rows={3}
                      style={{ ...inputStil(loading), resize: "vertical", minHeight: 70 }}
                    />
                  ) : (
                    <input
                      type="text"
                      value={dodatnaPolja[polje.kljuc] ?? ""}
                      onChange={(e) => setDodatnaPolja((prev) => ({ ...prev, [polje.kljuc]: e.target.value }))}
                      disabled={loading}
                      placeholder={polje.placeholder}
                      style={inputStil(loading)}
                    />
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Datumi i rok */}
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 16, marginBottom: 16 }}>
          <div>
            <label style={labelStil()}>Datum početka</label>
            <input type="date" {...register("datumPocetka")} disabled={loading} style={inputStil(loading)} />
          </div>
          <div>
            <label style={labelStil()}>Rok <span style={{ color: "#ef4444" }}>*</span></label>
            <input type="date" {...register("rok")} disabled={loading} style={inputStil(loading)} />
            {errors.rok && <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#ef4444", marginTop: 4 }}>{errors.rok.message}</p>}
          </div>
          <div>
            <label style={labelStil()}>Prag upozorenja (dana)</label>
            <input type="number" min={1} max={30} {...register("pragUpozorenjaDana")} disabled={loading} style={inputStil(loading)} />
          </div>
        </div>

        {/* Dugmad */}
        <div style={{ display: "flex", justifyContent: "flex-end", gap: 10, paddingTop: 16, borderTop: "1px solid #2a2a2a", marginTop: 8 }}>
          <button
            type="button"
            onClick={() => router.back()}
            disabled={loading}
            style={{
              fontFamily: "var(--font-mono), monospace", fontSize: 11, textTransform: "uppercase", letterSpacing: 1,
              padding: "9px 18px", border: "1px solid #3a3a3a", background: "transparent", color: "#999999", cursor: "pointer",
            }}
          >
            Otkaži
          </button>
          <button
            type="submit"
            disabled={loading}
            style={{
              fontFamily: "var(--font-mono), monospace", fontSize: 11, textTransform: "uppercase", letterSpacing: 1,
              padding: "9px 18px", border: "none", background: loading ? "#c9a015" : "#f5c518", color: "#000", cursor: loading ? "not-allowed" : "pointer",
            }}
          >
            {loading ? "Čuvanje..." : mode === "edit" ? "Sačuvaj izmene" : "Kreiraj zahtev"}
          </button>
        </div>
      </form>
    </div>
  );
}
