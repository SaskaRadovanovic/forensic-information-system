"use client";

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
import { Upload, FileText } from "lucide-react";
import { kreirajDokument } from "@/app/(dashboard)/dokumentacija/actions";
import { izmeniDokument } from "@/app/(dashboard)/dokumentacija/[id]/actions";

// ─── Konstante ────────────────────────────────────────────────────────────────

const TIPOVI_DOKUMENATA = [
  "Izveštaj",
  "Fotografija",
  "Zapisnik",
  "Veštačenje",
  "Zbirni izveštaj",
  "Ostalo",
] as const;

const MAX_PDF_SIZE = 20 * 1024 * 1024; // 20 MB

// ─── Zod šema ─────────────────────────────────────────────────────────────────

const formSchema = z.object({
  naziv: z.string().min(2, "Naziv mora imati najmanje 2 karaktera."),
  predmetId: z.string().min(1, "Predmet je obavezan."),
  tipDokumenta: z.string().min(1, "Tip dokumenta je obavezan."),
  opis: z.string().optional(),
  razlogIzmene: z.string().optional(),
});

type FormValues = z.infer<typeof formSchema>;

// ─── Tipovi ───────────────────────────────────────────────────────────────────

interface Predmet {
  id: number;
  naziv: string;
}

interface InitialValues {
  id: number;
  naziv: string;
  predmetId: number;
  tipDokumenta: string;
  opis?: string;
  putanja: string; // za prikaz postojećeg fajla
}

interface DokumentFormProps {
  mode: "create" | "edit";
  predmeti: Predmet[];
  initialValues?: InitialValues;
}

// ─── Helper: naziv fajla iz putanje ──────────────────────────────────────────

function fileNameFromPath(putanja: string): string {
  return putanja.split(/[\\/]/).pop() ?? putanja;
}

// ─── Komponenta ───────────────────────────────────────────────────────────────

export function DokumentForm({ mode, predmeti, initialValues }: DokumentFormProps) {
  const router = useRouter();
  const isEdit = mode === "edit";

  const [pdfFile, setPdfFile] = useState<File | null>(null);
  const [pdfError, setPdfError] = useState<string | null>(null);
  const [serverGreska, setServerGreska] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      naziv: initialValues?.naziv ?? "",
      predmetId: initialValues ? String(initialValues.predmetId) : "",
      tipDokumenta: initialValues?.tipDokumenta ?? "",
      opis: initialValues?.opis ?? "",
      razlogIzmene: "",
    },
  });

  // ─── PDF handler ─────────────────────────────────────────────────────────────

  function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    setPdfError(null);

    if (!file) { setPdfFile(null); return; }

    if (file.type !== "application/pdf") {
      setPdfError("Dozvoljen je samo PDF format.");
      setPdfFile(null);
      e.target.value = "";
      return;
    }
    if (file.size > MAX_PDF_SIZE) {
      setPdfError("Maksimalna veličina fajla je 20 MB.");
      setPdfFile(null);
      e.target.value = "";
      return;
    }
    setPdfFile(file);
  }

  // ─── Submit ──────────────────────────────────────────────────────────────────

  async function onSubmit(values: FormValues) {
    // U create modu PDF je obavezan
    if (!isEdit && !pdfFile) {
      setPdfError("PDF fajl je obavezan.");
      return;
    }

    setLoading(true);
    setServerGreska(null);

    const formData = new FormData();
    formData.append("naziv", values.naziv);
    formData.append("predmetId", values.predmetId);
    formData.append("tipDokumenta", values.tipDokumenta);
    if (values.opis?.trim()) formData.append("opis", values.opis.trim());
    if (pdfFile) formData.append("pdf", pdfFile);

    let result;

    if (isEdit && initialValues) {
      formData.append("dokumentId", String(initialValues.id));
      if (values.razlogIzmene?.trim()) {
        formData.append("razlogIzmene", values.razlogIzmene.trim());
      }
      result = await izmeniDokument(formData);
    } else {
      result = await kreirajDokument(formData);
    }

    if (!result.ok) {
      setServerGreska(result.greska);
      setLoading(false);
      return;
    }

    // Uspeh: idemo na detalje (edit) ili na listu (create)
    if (isEdit && initialValues) {
      router.push(`/dokumentacija/${initialValues.id}`);
    } else {
      router.push("/dokumentacija");
    }
  }

  // ─── Render ──────────────────────────────────────────────────────────────────

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          {isEdit ? "Izmena dokumenta" : "Novi dokument"}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">

          {serverGreska && (
            <div className="rounded-md bg-fis-red/10 border border-fis-red/30 px-4 py-3 text-sm text-fis-red">
              {serverGreska}
            </div>
          )}

          {/* Naziv */}
          <div className="space-y-2">
            <Label htmlFor="naziv">
              Naziv dokumenta <span className="text-red-500">*</span>
            </Label>
            <Input
              id="naziv"
              {...register("naziv")}
              placeholder="npr. Izveštaj o uviđaju"
              disabled={loading}
            />
            {errors.naziv && (
              <p className="text-sm text-fis-red">{errors.naziv.message}</p>
            )}
          </div>

          {/* Predmet (SCRUM-36) */}
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

          {/* Tip dokumenta (SCRUM-35) */}
          <div className="space-y-2">
            <Label htmlFor="tipDokumenta">
              Tip dokumenta <span className="text-red-500">*</span>
            </Label>
            <NativeSelect
              id="tipDokumenta"
              {...register("tipDokumenta")}
              disabled={loading}
            >
              <option value="">— Odaberi tip —</option>
              {TIPOVI_DOKUMENATA.map((tip) => (
                <option key={tip} value={tip}>{tip}</option>
              ))}
            </NativeSelect>
            {errors.tipDokumenta && (
              <p className="text-sm text-fis-red">{errors.tipDokumenta.message}</p>
            )}
          </div>

          {/* Opis */}
          <div className="space-y-2">
            <Label htmlFor="opis">Opis (opciono)</Label>
            <Textarea
              id="opis"
              {...register("opis")}
              placeholder="Kratki opis sadržaja dokumenta..."
              rows={3}
              disabled={loading}
            />
          </div>

          {/* PDF upload (SCRUM-37) */}
          <div className="space-y-2">
            <Label>
              PDF fajl{" "}
              {isEdit ? (
                <span className="text-fis-text2 text-xs font-normal">(ostavi prazno da zadržiš postojeći)</span>
              ) : (
                <span className="text-red-500">*</span>
              )}
            </Label>

            {/* Prikaz postojećeg fajla u edit modu */}
            {isEdit && !pdfFile && initialValues && (
              <div className="flex items-center gap-2 rounded-md bg-fis-surface2 border border-fis-surface3 px-3 py-2 text-sm text-fis-text2">
                <FileText className="h-4 w-4 text-red-500 flex-shrink-0" />
                <span className="truncate font-mono text-xs">
                  {fileNameFromPath(initialValues.putanja)}
                </span>
              </div>
            )}

            <label
              htmlFor="pdf-input"
              className="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-fis-surface3 bg-fis-surface2 px-4 py-4 text-sm text-fis-text2 hover:bg-fis-surface3 transition-colors"
            >
              {pdfFile ? (
                <>
                  <FileText className="h-5 w-5 text-fis-red flex-shrink-0" />
                  <div className="min-w-0">
                    <p className="font-medium truncate">{pdfFile.name}</p>
                    <p className="text-xs text-fis-text2 mt-0.5">
                      {(pdfFile.size / 1024 / 1024).toFixed(2)} MB
                    </p>
                  </div>
                </>
              ) : (
                <>
                  <Upload className="h-5 w-5 flex-shrink-0 text-fis-text2" />
                  <span>
                    {isEdit ? "Klikni za upload novog PDF-a" : "Klikni za odabir PDF fajla"}{" "}
                    (maks. 20 MB)
                  </span>
                </>
              )}
              <input
                id="pdf-input"
                type="file"
                accept=".pdf,application/pdf"
                className="hidden"
                onChange={handleFileChange}
                disabled={loading}
              />
            </label>
            {pdfError && <p className="text-sm text-fis-red">{pdfError}</p>}
          </div>

          {/* Razlog izmene — samo u edit modu (SCRUM-42) */}
          {isEdit && (
            <div className="space-y-2">
              <Label htmlFor="razlogIzmene">Razlog izmene (opciono)</Label>
              <Textarea
                id="razlogIzmene"
                {...register("razlogIzmene")}
                placeholder="Zašto se menja ovaj dokument?"
                rows={2}
                disabled={loading}
              />
            </div>
          )}

          {/* Dugmad */}
          <div className="flex items-center justify-end gap-3 pt-2 border-t">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.back()}
              disabled={loading}
            >
              Otkaži
            </Button>
            <Button type="submit" disabled={loading}>
              {loading
                ? "Čuvanje..."
                : isEdit
                ? "Sačuvaj izmene"
                : "Sačuvaj dokument"}
            </Button>
          </div>

        </form>
      </CardContent>
    </Card>
  );
}
