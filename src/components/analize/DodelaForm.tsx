"use client";

// ─── Forma za dodelu i preraspodelu veštaka ───────────────────────────────────
import { useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { NativeSelect } from "@/components/ui/native-select";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { dodeliVestaka } from "@/app/(dashboard)/analize/actions";

interface Vestak {
  idKorisnik: number;
  idVestak: string;
  specijalnost: string | null;
  korisnik: { ime: string; prezime: string };
}

interface DodelaFormProps {
  zahtevId: number;
  trenutniVestakId: number | null;  // null = prva dodela, !null = preraspodela
  vestaci: Vestak[];
}

export function DodelaForm({ zahtevId, trenutniVestakId, vestaci }: DodelaFormProps) {
  const router = useRouter();
  const jePrerasporedba = trenutniVestakId !== null;

  const [vestakId, setVestakId] = useState<string>("");
  const [razlog, setRazlog] = useState("");
  const [greska, setGreska] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!vestakId) { setGreska("Odabir veštaka je obavezan."); return; }
    if (jePrerasporedba && !razlog.trim()) { setGreska("Razlog preraspodele je obavezan."); return; }

    setLoading(true);
    setGreska(null);

    const rezultat = await dodeliVestaka(
      zahtevId,
      parseInt(vestakId, 10),
      jePrerasporedba ? razlog : undefined
    );

    if (!rezultat.ok) {
      setGreska(rezultat.greska);
      setLoading(false);
      return;
    }

    router.push(`/analize/${zahtevId}`);
    router.refresh();
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{jePrerasporedba ? "Preraspodela veštaka" : "Dodela veštaka"}</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={onSubmit} className="space-y-5">

          {greska && (
            <div className="rounded-md bg-fis-red/10 border border-fis-red/30 px-4 py-3 text-sm text-fis-red">
              {greska}
            </div>
          )}

          {/* Odabir veštaka */}
          <div className="space-y-2">
            <Label htmlFor="vestakId">Veštak <span className="text-red-500">*</span></Label>
            <NativeSelect
              id="vestakId"
              value={vestakId}
              onChange={(e) => setVestakId(e.target.value)}
              disabled={loading}
            >
              <option value="">— Odaberi veštaka —</option>
              {vestaci.map((v) => (
                <option key={v.idKorisnik} value={String(v.idKorisnik)}
                  disabled={v.idKorisnik === trenutniVestakId}>
                  {v.korisnik.ime} {v.korisnik.prezime}
                  {v.specijalnost ? ` — ${v.specijalnost}` : ""}
                  {v.idKorisnik === trenutniVestakId ? " (trenutni)" : ""}
                </option>
              ))}
            </NativeSelect>
          </div>

          {/* Razlog (obavezan samo kod preraspodele) */}
          {jePrerasporedba && (
            <div className="space-y-2">
              <Label htmlFor="razlog">Razlog preraspodele <span className="text-red-500">*</span></Label>
              <Textarea
                id="razlog"
                value={razlog}
                onChange={(e) => setRazlog(e.target.value)}
                rows={3}
                disabled={loading}
                placeholder="Objasnite zašto se vrši preraspodela..."
              />
            </div>
          )}

          {/* Dugmad */}
          <div className="flex items-center justify-end gap-3 pt-2 border-t border-fis-surface3">
            <Button type="button" variant="outline" onClick={() => router.back()} disabled={loading}>
              Otkaži
            </Button>
            <Button type="submit" disabled={loading}>
              {loading ? "Čuvanje..." : jePrerasporedba ? "Prerasporedi" : "Dodeli veštaka"}
            </Button>
          </div>

        </form>
      </CardContent>
    </Card>
  );
}
