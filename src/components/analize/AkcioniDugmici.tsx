"use client";

// ─── Akcioni dugmici za detalje zahteva (brisanje, status, verifikacija) ──────
import { useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel,
  AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
  AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { obrisiZahtev, zapocniAnalizu, odbijiZahtev, verifikujRezultat } from "@/app/(dashboard)/analize/actions";
import { StatusAnalize } from "@prisma/client";
import { Trash2, Play, XCircle, CheckCircle } from "lucide-react";

interface AkcioniDugmiciProps {
  zahtevId: number;
  status: StatusAnalize;
  uloga: string;
  jeDodeljenVestak: boolean;
  imaRezultat: boolean;
  jeVerifikovan: boolean;
  vestakIdZahteva: number | null;
  korisnikId: number;
}

export function AkcioniDugmici({
  zahtevId, status, uloga, jeDodeljenVestak, imaRezultat, jeVerifikovan, vestakIdZahteva, korisnikId
}: AkcioniDugmiciProps) {
  const router = useRouter();
  const [greska, setGreska] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [razlogBrisanja, setRazlogBrisanja] = useState("");

  async function handleBrisanje() {
    if (!razlogBrisanja.trim()) { setGreska("Razlog brisanja je obavezan."); return; }
    setLoading(true);
    const res = await obrisiZahtev(zahtevId, razlogBrisanja);
    if (!res.ok) { setGreska(res.greska); setLoading(false); return; }
    router.push("/analize");
    router.refresh();
  }

  async function handleZapocni() {
    setLoading(true);
    const res = await zapocniAnalizu(zahtevId);
    if (!res.ok) { setGreska(res.greska); setLoading(false); return; }
    router.refresh();
  }

  async function handleOdbij() {
    setLoading(true);
    const res = await odbijiZahtev(zahtevId);
    if (!res.ok) { setGreska(res.greska); setLoading(false); return; }
    router.refresh();
  }

  async function handleVerifikuj() {
    setLoading(true);
    const res = await verifikujRezultat(zahtevId);
    if (!res.ok) { setGreska(res.greska); setLoading(false); return; }
    router.refresh();
  }

  const mozeBrisati = uloga === "ADMINISTRATOR" && status !== "U_TOKU" && status !== "ZAVRSEN";
  const mozeZapoceti = uloga === "VESTAK" && status === "DODELJEN" && vestakIdZahteva === korisnikId;
  const mozeOdbiti = (uloga === "ADMINISTRATOR" || uloga === "ISTRAZITELJ") && status !== "ZAVRSEN" && status !== "ODBIJEN";
  const mozeVerifikovati = (uloga === "ISTRAZITELJ" || uloga === "ADMINISTRATOR") && imaRezultat && !jeVerifikovan;

  if (!mozeBrisati && !mozeZapoceti && !mozeOdbiti && !mozeVerifikovati) return null;

  return (
    <div className="flex flex-wrap items-center gap-2">
      {greska && <p className="w-full text-sm text-fis-red">{greska}</p>}

      {/* Dugme: Pokreni analizu */}
      {mozeZapoceti && (
        <Button size="sm" onClick={handleZapocni} disabled={loading} className="gap-1.5">
          <Play className="h-3.5 w-3.5" />
          Pokreni analizu
        </Button>
      )}

      {/* Dugme: Verifikuj rezultat */}
      {mozeVerifikovati && (
        <Button size="sm" onClick={handleVerifikuj} disabled={loading}
          className="gap-1.5 bg-fis-green/10 text-fis-green hover:bg-fis-green/20 border-0">
          <CheckCircle className="h-3.5 w-3.5" />
          Verifikuj rezultat
        </Button>
      )}

      {/* Dugme: Odbij zahtev */}
      {mozeOdbiti && (
        <AlertDialog>
          <AlertDialogTrigger
            render={(props) => (
              <Button
                size="sm"
                variant="outline"
                disabled={loading}
                className="gap-1.5 text-fis-orange border-fis-orange/30 hover:bg-fis-orange/10"
                {...props}
              >
                <XCircle className="h-3.5 w-3.5" />
                Odbij zahtev
              </Button>
            )}
          />
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Potvrda odbijanja</AlertDialogTitle>
              <AlertDialogDescription>
                {`Da li ste sigurni da želite da odbijete zahtev #${zahtevId}? Ova akcija se ne može poništiti.`}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Otkaži</AlertDialogCancel>
              <AlertDialogAction onClick={handleOdbij}>Odbij</AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      )}

      {/* Dugme: Obriši zahtev (ADMINISTRATOR) */}
      {mozeBrisati && (
        <AlertDialog>
          <AlertDialogTrigger
            render={(props) => (
              <Button
                size="sm"
                variant="outline"
                disabled={loading}
                className="gap-1.5 text-fis-red border-fis-red/30 hover:bg-fis-red/10"
                {...props}
              >
                <Trash2 className="h-3.5 w-3.5" />
                Obriši zahtev
              </Button>
            )}
          />
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Brisanje zahteva</AlertDialogTitle>
              <AlertDialogDescription>
                Brisanje je permanentno. Unesite razlog brisanja koji će biti evidentiran u sistemu.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <div className="px-0 pb-2 space-y-2">
              <Label htmlFor="razlog-brisanja">Razlog brisanja <span className="text-red-500">*</span></Label>
              <Textarea
                id="razlog-brisanja"
                value={razlogBrisanja}
                onChange={(e) => setRazlogBrisanja(e.target.value)}
                rows={3}
                placeholder="Objasnite razlog brisanja zahteva..."
              />
            </div>
            <AlertDialogFooter>
              <AlertDialogCancel>Otkaži</AlertDialogCancel>
              <AlertDialogAction
                onClick={handleBrisanje}
                className="bg-fis-red hover:bg-fis-red/90 text-white"
              >
                Obriši trajno
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      )}
    </div>
  );
}
