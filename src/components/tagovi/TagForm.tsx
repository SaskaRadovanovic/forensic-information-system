"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { TagBadge } from "@/components/ui/tag-badge";
import { kreirajTag } from "@/app/(dashboard)/tagovi/actions";
import { cn } from "@/lib/utils";

// ── Paleta boja ───────────────────────────────────────────────────────────────

const PALETTE = [
  { label: "Žuta",        hex: "#FACC15" },
  { label: "Crvena",      hex: "#F87171" },
  { label: "Zelena",      hex: "#34D399" },
  { label: "Plava",       hex: "#60A5FA" },
  { label: "Narandžasta", hex: "#FB923C" },
  { label: "Ljubičasta",  hex: "#A78BFA" },
  { label: "Roza",        hex: "#F472B6" },
  { label: "Tirkizna",    hex: "#22D3EE" },
];

// ── Komponenta ────────────────────────────────────────────────────────────────

export function TagForm() {
  const router = useRouter();
  const [naziv, setNaziv] = useState("");
  const [boja, setBoja] = useState(PALETTE[0].hex);
  const [loading, setLoading] = useState(false);
  const [greska, setGreska] = useState<string | null>(null);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setGreska(null);

    const fd = new FormData();
    fd.append("naziv", naziv);
    fd.append("boja", boja);

    const result = await kreirajTag(fd);

    if (!result.ok) {
      setGreska(result.greska);
      setLoading(false);
      return;
    }

    router.push("/tagovi");
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Novi tag</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-6">

          {/* Error */}
          {greska && (
            <div className="rounded-md bg-fis-red/10 border border-fis-red/30 px-4 py-3 text-sm text-fis-red">
              {greska}
            </div>
          )}

          {/* Naziv */}
          <div className="space-y-2">
            <Label htmlFor="naziv">
              Naziv taga <span className="text-fis-red">*</span>
            </Label>
            <Input
              id="naziv"
              value={naziv}
              onChange={(e) => setNaziv(e.target.value)}
              placeholder="npr. HITNO, Vatreno oružje..."
              required
              disabled={loading}
            />
            <p className="text-xs text-fis-text2">
              Naziv mora biti jedinstven. Tagove kreira administrator, a dodeljuju ih korisnici.
            </p>
          </div>

          {/* Paleta boja */}
          <div className="space-y-3">
            <Label>Boja taga</Label>

            {/* Swatches */}
            <div className="flex flex-wrap gap-2">
              {PALETTE.map((p) => (
                <button
                  key={p.hex}
                  type="button"
                  title={p.label}
                  onClick={() => setBoja(p.hex)}
                  className={cn(
                    "h-8 w-8 rounded-md border-2 transition-all",
                    boja === p.hex
                      ? "scale-110 border-white shadow-lg"
                      : "border-transparent opacity-70 hover:opacity-100 hover:scale-105"
                  )}
                  style={{ backgroundColor: p.hex }}
                />
              ))}
            </div>

            {/* Preview */}
            {naziv.trim() && (
              <div className="flex items-center gap-2 pt-1">
                <span className="text-xs text-fis-text2">Preview:</span>
                <TagBadge naziv={naziv.trim().toUpperCase()} boja={boja} />
              </div>
            )}
          </div>

          {/* Dugmad */}
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
              {loading ? "Čuvanje..." : "Kreiraj tag"}
            </Button>
          </div>

        </form>
      </CardContent>
    </Card>
  );
}
