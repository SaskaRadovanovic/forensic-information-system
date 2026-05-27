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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { kreirajPredmet } from "@/app/(dashboard)/predmeti/actions";
import { izmeniPredmet } from "@/app/(dashboard)/predmeti/actions";

// ─── Zod šema za validaciju forme ───────────────────────────────────────────

const formSchema = z.object({
  naziv: z.string().min(2, "Naziv predmeta mora imati najmanje 2 karaktera."),
  opis: z.string().optional(),
});

type FormValues = z.infer<typeof formSchema>;

// ─── Tipovi za props ────────────────────────────────────────────────────────

interface PocetneVrednosti {
  id: number;
  naziv: string;
  opis?: string | null;
}

interface PredmetFormaProps {
  mode: "create" | "edit";
  pocetneVrednosti?: PocetneVrednosti;
}

// ─── Komponenta forme za predmet ─────────────────────────────────────────────

export function PredmetForma({ mode, pocetneVrednosti }: PredmetFormaProps) {
  const router = useRouter();
  const isEdit = mode === "edit";

  // Stanje za greške sa servera i loading indikator
  const [serverGreska, setServerGreska] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  // Inicijalizacija React Hook Form sa Zod validacijom
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      naziv: pocetneVrednosti?.naziv ?? "",
      opis: pocetneVrednosti?.opis ?? "",
    },
  });

  // ─── Submit handler ──────────────────────────────────────────────────────

  async function onSubmit(values: FormValues) {
    setLoading(true);
    setServerGreska(null);

    // Gradimo FormData objekat
    const formData = new FormData();
    formData.append("naziv", values.naziv);
    if (values.opis?.trim()) formData.append("opis", values.opis.trim());

    let result;

    if (isEdit && pocetneVrednosti) {
      // Izmena postojećeg predmeta
      formData.append("predmetId", String(pocetneVrednosti.id));
      result = await izmeniPredmet(formData);
    } else {
      // Kreiranje novog predmeta
      result = await kreirajPredmet(formData);
    }

    if (!result.ok) {
      setServerGreska(result.greska);
      setLoading(false);
      return;
    }

    // Preusmeravamo na odgovarajuću stranicu nakon uspešne operacije
    if (isEdit && pocetneVrednosti) {
      router.push(`/predmeti/${pocetneVrednosti.id}`);
    } else if (!isEdit && "id" in result) {
      router.push(`/predmeti/${result.id}`);
    } else {
      router.push("/predmeti");
    }
  }

  // ─── Render ──────────────────────────────────────────────────────────────

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          {isEdit ? "Izmena predmeta" : "Novi predmet istrage"}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">

          {/* Prikaz greške sa servera */}
          {serverGreska && (
            <div className="rounded-md bg-fis-red/10 border border-fis-red/30 px-4 py-3 text-sm text-fis-red">
              {serverGreska}
            </div>
          )}

          {/* Naziv predmeta */}
          <div className="space-y-2">
            <Label htmlFor="naziv">
              Naziv predmeta <span className="text-red-500">*</span>
            </Label>
            <Input
              id="naziv"
              {...register("naziv")}
              placeholder="npr. Ubistvo u ulici Knez Mihailova"
              disabled={loading}
            />
            {errors.naziv && (
              <p className="text-sm text-fis-red">{errors.naziv.message}</p>
            )}
          </div>

          {/* Opis predmeta */}
          <div className="space-y-2">
            <Label htmlFor="opis">Opis (opciono)</Label>
            <Textarea
              id="opis"
              {...register("opis")}
              placeholder="Kratki opis predmeta istrage..."
              rows={4}
              disabled={loading}
            />
          </div>

          {/* Dugmad za akcije */}
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
              {loading ? "Čuvanje..." : isEdit ? "Sačuvaj izmene" : "Kreiraj predmet"}
            </Button>
          </div>

        </form>
      </CardContent>
    </Card>
  );
}
