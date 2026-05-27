"use client";

// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { PoljaPoTipu } from "./PoljaPoTipu";
import { kreirajDokaz } from "@/app/(dashboard)/dokazi/actions";
import { izmeniDokaz } from "@/app/(dashboard)/dokazi/[id]/actions";

// ─── Konstante: tipovi dokaza ───────────────────────────────────────────────

const TIPOVI_DOKAZA = [
  { value: "BIOLOSKI_TRAG", label: "Biološki trag" },
  { value: "ORUZJE", label: "Oružje" },
  { value: "DOKUMENT", label: "Dokument" },
  { value: "ODECA", label: "Odeća" },
  { value: "UZORAK", label: "Uzorak" },
] as const;

// ─── Zod šema za validaciju ─────────────────────────────────────────────────

const formSchema = z.object({
  naziv: z.string().min(2, "Naziv mora imati najmanje 2 karaktera."),
  opis: z.string().optional(),
  tipDokaza: z.string().min(1, "Tip dokaza je obavezan."),
  predmetId: z.string().min(1, "Predmet je obavezan."),
  lokacijaSkladistenja: z.string().optional(),
  datumPrijema: z.string().min(1, "Datum prijema je obavezan."),
  datumPronalaska: z.string().optional(),
  lokacijaPronalaska: z.string().optional(),
});

type FormValues = z.infer<typeof formSchema>;

// ─── Tipovi za props ────────────────────────────────────────────────────────

interface Predmet {
  id: number;
  naziv: string;
}

interface PocetneVrednosti {
  id: number;
  naziv: string;
  opis?: string | null;
  tipDokaza: string;
  predmetId: number;
  lokacijaSkladistenja?: string | null;
  datumPrijema: string;
  datumPronalaska?: string | null;
  lokacijaPronalaska?: string | null;
  specificnaPolja?: Record<string, any>;
}

interface DokazFormaProps {
  mode: "create" | "edit";
  predmeti: Predmet[];
  pocetneVrednosti?: PocetneVrednosti;
}

// ─── Stil helperi ───────────────────────────────────────────────────────────

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

// ─── Komponenta ─────────────────────────────────────────────────────────────

export function DokazForma({ mode, predmeti, pocetneVrednosti }: DokazFormaProps) {
  const router = useRouter();
  const isEdit = mode === "edit";

  // Stanje za server greške i loading indikator
  const [serverGreska, setServerGreska] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  // React Hook Form sa Zod validacijom — inicijalizacija sa podrazumevanim vrednostima
  const {
    register,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      naziv: pocetneVrednosti?.naziv ?? "",
      opis: pocetneVrednosti?.opis ?? "",
      tipDokaza: pocetneVrednosti?.tipDokaza ?? "",
      predmetId: pocetneVrednosti ? String(pocetneVrednosti.predmetId) : "",
      lokacijaSkladistenja: pocetneVrednosti?.lokacijaSkladistenja ?? "",
      // Izvlačimo samo deo datuma (bez vremena) iz ISO stringa
      datumPrijema: pocetneVrednosti?.datumPrijema
        ? pocetneVrednosti.datumPrijema.split("T")[0]
        : "",
      datumPronalaska: pocetneVrednosti?.datumPronalaska
        ? pocetneVrednosti.datumPronalaska.split("T")[0]
        : "",
      lokacijaPronalaska: pocetneVrednosti?.lokacijaPronalaska ?? "",
      // Širi specifična polja za dati tip dokaza
      ...pocetneVrednosti?.specificnaPolja,
    },
  });

  // Pratimo odabrani tip za dinamičko renderovanje specifičnih polja
  const odabraniTip = watch("tipDokaza");

  // ─── Submit handler ─────────────────────────────────────────────────────

  async function onSubmit(values: FormValues) {
    setLoading(true);
    setServerGreska(null);

    // Gradimo FormData sa svim zajedničkim i specifičnim poljima
    const formData = new FormData();
    formData.append("naziv", values.naziv);
    formData.append("tipDokaza", values.tipDokaza);
    formData.append("predmetId", values.predmetId);
    formData.append("datumPrijema", values.datumPrijema);

    // Dodajemo opciona polja samo ako imaju vrednost
    if (values.opis?.trim()) formData.append("opis", values.opis.trim());
    if (values.lokacijaSkladistenja?.trim()) {
      formData.append("lokacijaSkladistenja", values.lokacijaSkladistenja.trim());
    }
    if (values.datumPronalaska?.trim()) {
      formData.append("datumPronalaska", values.datumPronalaska.trim());
    }
    if (values.lokacijaPronalaska?.trim()) {
      formData.append("lokacijaPronalaska", values.lokacijaPronalaska.trim());
    }

    // Dodajemo specifična polja po tipu — sve ključeve koji nisu zajednički
    const svaPolja = watch();
    const zajednickaPolja = [
      "naziv", "opis", "tipDokaza", "predmetId",
      "lokacijaSkladistenja", "datumPrijema", "datumPronalaska", "lokacijaPronalaska",
    ];
    for (const [kljuc, vrednost] of Object.entries(svaPolja)) {
      if (!zajednickaPolja.includes(kljuc) && vrednost) {
        formData.append(kljuc, String(vrednost));
      }
    }

    let result;

    // Razlikujemo kreiranje od izmene dokaza
    if (isEdit && pocetneVrednosti) {
      formData.append("dokazId", String(pocetneVrednosti.id));
      result = await izmeniDokaz(formData);
    } else {
      result = await kreirajDokaz(formData);
    }

    // Obrada greške sa servera
    if (!result.ok) {
      setServerGreska(result.greska);
      setLoading(false);
      return;
    }

    // Uspešno — preusmeravamo korisnika na odgovarajuću stranicu
    if (isEdit && pocetneVrednosti) {
      router.push(`/dokazi/${pocetneVrednosti.id}`);
    } else {
      router.push("/dokazi");
    }
  }

  // ─── Render ───────────────────────────────────────────────────────────────

  return (
    <div style={{ border: "1px solid #2a2a2a", background: "#111111" }}>
      {/* Header */}
      <div style={{ padding: "20px 24px", borderBottom: "1px solid #2a2a2a" }}>
        <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 9, textTransform: "uppercase", letterSpacing: "2px", color: "#555555", marginBottom: 4 }}>
          {isEdit ? "Izmena dokaza" : "Novi dokaz"}
        </p>
        <h2 style={{ fontFamily: "var(--font-display), 'Bebas Neue', sans-serif", fontSize: 24, letterSpacing: 2, color: "#f0ede8", lineHeight: 1 }}>
          {isEdit ? "Izmena podataka o dokazu" : "Evidentiraj novi dokaz"}
        </h2>
      </div>

      {/* Body */}
      <form onSubmit={handleSubmit(onSubmit)} style={{ padding: "24px" }}>

        {serverGreska && (
          <div style={{ border: "1px solid rgba(239,68,68,0.4)", background: "rgba(239,68,68,0.08)", padding: "10px 14px", marginBottom: 20, fontFamily: "var(--font-mono), monospace", fontSize: 12, color: "#ef4444" }}>
            {serverGreska}
          </div>
        )}

        {/* Grid: naziv + tip */}
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 16 }}>
          <div>
            <label style={labelStil()}>Naziv dokaza <span style={{ color: "#ef4444" }}>*</span></label>
            <input
              type="text"
              {...register("naziv")}
              placeholder="npr. Nož pronađen na licu mesta"
              disabled={loading}
              style={inputStil(loading)}
            />
            {errors.naziv && <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#ef4444", marginTop: 4 }}>{errors.naziv.message}</p>}
          </div>

          <div>
            <label style={labelStil()}>Tip dokaza <span style={{ color: "#ef4444" }}>*</span></label>
            <select
              {...register("tipDokaza")}
              disabled={loading || isEdit}
              style={{ ...inputStil(loading || isEdit), cursor: loading || isEdit ? "not-allowed" : "pointer" }}
            >
              <option value="">— Odaberi tip —</option>
              {TIPOVI_DOKAZA.map((tip) => (
                <option key={tip.value} value={tip.value}>{tip.label}</option>
              ))}
            </select>
            {errors.tipDokaza && <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#ef4444", marginTop: 4 }}>{errors.tipDokaza.message}</p>}
            {isEdit && (
              <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 10, color: "#555555", marginTop: 4 }}>Tip dokaza se ne može menjati nakon kreiranja.</p>
            )}
          </div>
        </div>

        {/* Grid: predmet + datum prijema */}
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 16 }}>
          <div>
            <label style={labelStil()}>Predmet <span style={{ color: "#ef4444" }}>*</span></label>
            <select
              {...register("predmetId")}
              disabled={loading}
              style={{ ...inputStil(loading), cursor: loading ? "not-allowed" : "pointer" }}
            >
              <option value="">— Odaberi predmet —</option>
              {predmeti.map((p) => (
                <option key={p.id} value={String(p.id)}>{p.naziv}</option>
              ))}
            </select>
            {errors.predmetId && <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#ef4444", marginTop: 4 }}>{errors.predmetId.message}</p>}
          </div>

          <div>
            <label style={labelStil()}>Datum prijema <span style={{ color: "#ef4444" }}>*</span></label>
            <input type="date" {...register("datumPrijema")} disabled={loading} style={inputStil(loading)} />
            {errors.datumPrijema && <p style={{ fontFamily: "var(--font-mono), monospace", fontSize: 11, color: "#ef4444", marginTop: 4 }}>{errors.datumPrijema.message}</p>}
          </div>
        </div>

        {/* Grid: datum pronalaska + lokacija pronalaska */}
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16, marginBottom: 16 }}>
          <div>
            <label style={labelStil()}>Datum pronalaska</label>
            <input type="date" {...register("datumPronalaska")} disabled={loading} style={inputStil(loading)} />
          </div>

          <div>
            <label style={labelStil()}>Lokacija pronalaska</label>
            <input
              type="text"
              {...register("lokacijaPronalaska")}
              placeholder="npr. Ul. Knez Mihailova 15, Beograd"
              disabled={loading}
              style={inputStil(loading)}
            />
          </div>
        </div>

        {/* Lokacija skladištenja */}
        <div style={{ marginBottom: 16 }}>
          <label style={labelStil()}>Lokacija skladištenja</label>
          <input
            type="text"
            {...register("lokacijaSkladistenja")}
            placeholder="npr. Skladište A, polica 3"
            disabled={loading}
            style={inputStil(loading)}
          />
        </div>

        {/* Opis */}
        <div style={{ marginBottom: 20 }}>
          <label style={labelStil()}>Opis</label>
          <textarea
            {...register("opis")}
            placeholder="Kratki opis dokaza..."
            rows={3}
            disabled={loading}
            style={{ ...inputStil(loading), resize: "vertical", minHeight: 70 }}
          />
        </div>

        {/* Specifična polja po tipu */}
        <PoljaPoTipu
          tipDokaza={odabraniTip}
          register={register}
          disabled={loading}
        />

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
            {loading ? "Čuvanje..." : isEdit ? "Sačuvaj izmene" : "Evidentiraj dokaz"}
          </button>
        </div>
      </form>
    </div>
  );
}
