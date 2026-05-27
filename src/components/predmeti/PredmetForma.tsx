"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { kreirajPredmet, izmeniPredmet } from "@/app/(dashboard)/predmeti/actions";

// ─── Validaciona šema ────────────────────────────────────────────────────────

const formSchema = z.object({
  naziv: z.string().min(2, "Naziv predmeta mora imati najmanje 2 karaktera."),
  opis: z.string().optional(),
});

type FormValues = z.infer<typeof formSchema>;

interface PocetneVrednosti {
  id: number;
  naziv: string;
  opis?: string | null;
}

interface PredmetFormaProps {
  mode: "create" | "edit";
  pocetneVrednosti?: PocetneVrednosti;
}

// ─── Forma za kreiranje/izmenu predmeta ──────────────────────────────────────

export function PredmetForma({ mode, pocetneVrednosti }: PredmetFormaProps) {
  const router = useRouter();
  const isEdit = mode === "edit";

  const [serverGreska, setServerGreska] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      naziv: pocetneVrednosti?.naziv ?? "",
      opis:  pocetneVrednosti?.opis  ?? "",
    },
  });

  async function onSubmit(values: FormValues) {
    setLoading(true);
    setServerGreska(null);

    const formData = new FormData();
    formData.append("naziv", values.naziv);
    if (values.opis?.trim()) formData.append("opis", values.opis.trim());

    let result;

    if (isEdit && pocetneVrednosti) {
      formData.append("predmetId", String(pocetneVrednosti.id));
      result = await izmeniPredmet(formData);
    } else {
      result = await kreirajPredmet(formData);
    }

    if (!result.ok) {
      setServerGreska(result.greska);
      setLoading(false);
      return;
    }

    if (isEdit && pocetneVrednosti) {
      router.push(`/predmeti/${pocetneVrednosti.id}`);
    } else if (!isEdit && "id" in result) {
      router.push(`/predmeti/${result.id}`);
    } else {
      router.push("/predmeti");
    }
  }

  return (
    <div className="fis-card">
      <div className="fis-card-header">
        <h3>{isEdit ? "Izmena predmeta" : "Novi predmet istrage"}</h3>
      </div>
      <div className="fis-card-body">
        <form onSubmit={handleSubmit(onSubmit)}>

          {/* Greška sa servera */}
          {serverGreska && (
            <div className="fis-alert fis-alert-red" style={{ marginBottom: 20 }}>
              {serverGreska}
            </div>
          )}

          <div className="fis-form-grid">

            {/* Naziv predmeta */}
            <div className="fis-form-group full">
              <label htmlFor="naziv">Naziv predmeta *</label>
              <input
                id="naziv"
                className="fis-input"
                style={{ width: "100%" }}
                placeholder="npr. Razbojništvo – Beograd Centar"
                disabled={loading}
                {...register("naziv")}
              />
              {errors.naziv && (
                <div className="fis-form-error">{errors.naziv.message}</div>
              )}
            </div>

            {/* Opis predmeta */}
            <div className="fis-form-group full">
              <label htmlFor="opis">Opis (opciono)</label>
              <textarea
                id="opis"
                className="fis-input"
                style={{ width: "100%", resize: "vertical", minHeight: 90 }}
                placeholder="Kratki opis slučaja, lokacija, okolnosti…"
                disabled={loading}
                {...register("opis")}
              />
            </div>

          </div>

          {/* Dugmad */}
          <div style={{ display: "flex", gap: 8, marginTop: 20 }}>
            <button
              type="submit"
              className="fis-btn fis-btn-primary"
              disabled={loading}
            >
              {loading ? "Čuvanje…" : isEdit ? "Sačuvaj izmene" : "Kreiraj predmet"}
            </button>
            <button
              type="button"
              className="fis-btn fis-btn-ghost"
              onClick={() => router.back()}
              disabled={loading}
            >
              Otkaži
            </button>
          </div>

        </form>
      </div>
    </div>
  );
}
