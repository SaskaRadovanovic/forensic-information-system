"use client";

// ─── Uvoz zavisnosti ─────────────────────────────────────────────────────────
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { NativeSelect } from "@/components/ui/native-select";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
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
    <Card>
      <CardHeader>
        <CardTitle>{isEdit ? "Izmena dokaza" : "Novi dokaz"}</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">

          {/* Prikaz greške sa servera ako postoji */}
          {serverGreska && (
            <div className="rounded-md bg-fis-red/10 border border-fis-red/30 px-4 py-3 text-sm text-fis-red">
              {serverGreska}
            </div>
          )}

          {/* ── Zajednička polja ──────────────────────────────────────────── */}

          {/* Naziv dokaza */}
          <div className="space-y-2">
            <Label htmlFor="naziv">
              Naziv dokaza <span className="text-red-500">*</span>
            </Label>
            <Input
              id="naziv"
              {...register("naziv")}
              placeholder="npr. Nož pronađen na licu mesta"
              disabled={loading}
            />
            {errors.naziv && (
              <p className="text-sm text-fis-red">{errors.naziv.message}</p>
            )}
          </div>

          {/* Tip dokaza — onemogućen pri izmeni */}
          <div className="space-y-2">
            <Label htmlFor="tipDokaza">
              Tip dokaza <span className="text-red-500">*</span>
            </Label>
            <NativeSelect
              id="tipDokaza"
              {...register("tipDokaza")}
              disabled={loading || isEdit}
            >
              <option value="">— Odaberi tip —</option>
              {TIPOVI_DOKAZA.map((tip) => (
                <option key={tip.value} value={tip.value}>
                  {tip.label}
                </option>
              ))}
            </NativeSelect>
            {errors.tipDokaza && (
              <p className="text-sm text-fis-red">{errors.tipDokaza.message}</p>
            )}
            {/* Napomena korisniku da se tip ne može menjati */}
            {isEdit && (
              <p className="text-xs text-fis-text3">Tip dokaza se ne može menjati nakon kreiranja.</p>
            )}
          </div>

          {/* Predmet kome dokaz pripada */}
          <div className="space-y-2">
            <Label htmlFor="predmetId">
              Predmet <span className="text-red-500">*</span>
            </Label>
            <NativeSelect
              id="predmetId"
              {...register("predmetId")}
              disabled={loading}
            >
              <option value="">— Odaberi predmet —</option>
              {predmeti.map((p) => (
                <option key={p.id} value={String(p.id)}>
                  {p.naziv}
                </option>
              ))}
            </NativeSelect>
            {errors.predmetId && (
              <p className="text-sm text-fis-red">{errors.predmetId.message}</p>
            )}
          </div>

          {/* Datum prijema dokaza */}
          <div className="space-y-2">
            <Label htmlFor="datumPrijema">
              Datum prijema <span className="text-red-500">*</span>
            </Label>
            <Input
              id="datumPrijema"
              type="date"
              {...register("datumPrijema")}
              disabled={loading}
            />
            {errors.datumPrijema && (
              <p className="text-sm text-fis-red">{errors.datumPrijema.message}</p>
            )}
          </div>

          {/* Datum pronalaska — opciono polje */}
          <div className="space-y-2">
            <Label htmlFor="datumPronalaska">Datum pronalaska (opciono)</Label>
            <Input
              id="datumPronalaska"
              type="date"
              {...register("datumPronalaska")}
              disabled={loading}
            />
          </div>

          {/* Lokacija pronalaska — opciono polje */}
          <div className="space-y-2">
            <Label htmlFor="lokacijaPronalaska">Lokacija pronalaska (opciono)</Label>
            <Input
              id="lokacijaPronalaska"
              {...register("lokacijaPronalaska")}
              placeholder="npr. Ul. Knez Mihailova 15, Beograd"
              disabled={loading}
            />
          </div>

          {/* Lokacija gde se dokaz čuva */}
          <div className="space-y-2">
            <Label htmlFor="lokacijaSkladistenja">Lokacija skladištenja</Label>
            <Input
              id="lokacijaSkladistenja"
              {...register("lokacijaSkladistenja")}
              placeholder="npr. Skladište A, polica 3"
              disabled={loading}
            />
          </div>

          {/* Opis dokaza */}
          <div className="space-y-2">
            <Label htmlFor="opis">Opis (opciono)</Label>
            <Textarea
              id="opis"
              {...register("opis")}
              placeholder="Kratki opis dokaza..."
              rows={3}
              disabled={loading}
            />
          </div>

          {/* ── Specifična polja u zavisnosti od tipa dokaza ─────────────── */}
          <PoljaPoTipu
            tipDokaza={odabraniTip}
            register={register}
            disabled={loading}
          />

          {/* ── Dugmad za akcije ─────────────────────────────────────────── */}
          <div className="flex items-center justify-end gap-3 pt-2 border-t border-fis-surface3">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.back()}
              disabled={loading}
            >
              Otkaži
            </Button>
            <Button type="submit" disabled={loading}>
              {loading ? "Čuvanje..." : "Sačuvaj"}
            </Button>
          </div>

        </form>
      </CardContent>
    </Card>
  );
}
