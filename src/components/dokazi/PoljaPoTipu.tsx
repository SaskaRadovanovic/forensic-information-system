"use client";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { NativeSelect } from "@/components/ui/native-select";
import type { UseFormRegister } from "react-hook-form";

// ─── Tipovi ─────────────────────────────────────────────────────────────────

interface PoljaPoTipuProps {
  tipDokaza: string;
  register: UseFormRegister<any>;
  disabled?: boolean;
}

// ─── Komponenta ─────────────────────────────────────────────────────────────

export function PoljaPoTipu({ tipDokaza, register, disabled }: PoljaPoTipuProps) {
  // Ako tip nije odabran, ne prikazujemo specifična polja
  if (!tipDokaza) return null;

  return (
    <div className="space-y-4 rounded-lg border border-fis-surface3 bg-fis-surface2 p-4">
      {/* Naslov sekcije */}
      <p className="text-sm font-semibold text-fis-text2 uppercase tracking-wide">
        Specifična polja — {labelZaTip(tipDokaza)}
      </p>

      {/* Dinamička polja u zavisnosti od tipa */}
      {tipDokaza === "BIOLOSKI_TRAG" && (
        <PoljaBioloskiTrag register={register} disabled={disabled} />
      )}
      {tipDokaza === "ORUZJE" && (
        <PoljaOruzje register={register} disabled={disabled} />
      )}
      {tipDokaza === "DOKUMENT" && (
        <PoljaDokument register={register} disabled={disabled} />
      )}
      {tipDokaza === "ODECA" && (
        <PoljaOdeca register={register} disabled={disabled} />
      )}
      {tipDokaza === "UZORAK" && (
        <PoljaUzorak register={register} disabled={disabled} />
      )}
    </div>
  );
}

// ─── Helper: label za tip dokaza ────────────────────────────────────────────

function labelZaTip(tip: string): string {
  const mapa: Record<string, string> = {
    BIOLOSKI_TRAG: "Biološki trag",
    ORUZJE: "Oružje",
    DOKUMENT: "Dokument",
    ODECA: "Odeća",
    UZORAK: "Uzorak",
  };
  return mapa[tip] ?? tip;
}

// ─── Polja: Biološki trag ───────────────────────────────────────────────────

function PoljaBioloskiTrag({ register, disabled }: { register: UseFormRegister<any>; disabled?: boolean }) {
  return (
    <div className="grid grid-cols-2 gap-4">
      <div className="space-y-2">
        <Label htmlFor="vrstaTraga">Vrsta traga</Label>
        <Input id="vrstaTraga" {...register("vrstaTraga")} placeholder="npr. Krv, Pljuvačka" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="nacinUzorkovanja">Način uzorkovanja</Label>
        <Input id="nacinUzorkovanja" {...register("nacinUzorkovanja")} placeholder="npr. Bris" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="usloviCuvanja">Uslovi čuvanja</Label>
        <Input id="usloviCuvanja" {...register("usloviCuvanja")} placeholder="npr. -20°C" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="kolicina">Količina</Label>
        <Input id="kolicina" {...register("kolicina")} placeholder="npr. 5 ml" disabled={disabled} />
      </div>
    </div>
  );
}

// ─── Polja: Oružje ──────────────────────────────────────────────────────────

function PoljaOruzje({ register, disabled }: { register: UseFormRegister<any>; disabled?: boolean }) {
  return (
    <div className="grid grid-cols-2 gap-4">
      <div className="space-y-2">
        <Label htmlFor="vrstaOruzja">Vrsta oružja</Label>
        <NativeSelect id="vrstaOruzja" {...register("vrstaOruzja")} disabled={disabled}>
          <option value="">— Odaberi —</option>
          <option value="Vatreno">Vatreno</option>
          <option value="Hladno">Hladno</option>
          <option value="Eksplozivno">Eksplozivno</option>
          <option value="Ostalo">Ostalo</option>
        </NativeSelect>
      </div>
      <div className="space-y-2">
        <Label htmlFor="marka">Marka</Label>
        <Input id="marka" {...register("marka")} placeholder="npr. Zastava" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="model">Model</Label>
        <Input id="model" {...register("model")} placeholder="npr. CZ 99" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="kalibar">Kalibar</Label>
        <Input id="kalibar" {...register("kalibar")} placeholder="npr. 9mm" disabled={disabled} />
      </div>
      <div className="col-span-2 space-y-2">
        <Label htmlFor="serijskiBr">Serijski broj</Label>
        <Input id="serijskiBr" {...register("serijskiBr")} placeholder="npr. AB-123456" disabled={disabled} />
      </div>
    </div>
  );
}

// ─── Polja: Dokument ────────────────────────────────────────────────────────

function PoljaDokument({ register, disabled }: { register: UseFormRegister<any>; disabled?: boolean }) {
  return (
    <div className="grid grid-cols-2 gap-4">
      <div className="space-y-2">
        <Label htmlFor="vrstaDokumenta">Vrsta dokumenta</Label>
        <NativeSelect id="vrstaDokumenta" {...register("vrstaDokumenta")} disabled={disabled}>
          <option value="">— Odaberi —</option>
          <option value="Lična karta">Lična karta</option>
          <option value="Pasoš">Pasoš</option>
          <option value="Ugovor">Ugovor</option>
          <option value="Pismo">Pismo</option>
          <option value="Ostalo">Ostalo</option>
        </NativeSelect>
      </div>
      <div className="space-y-2">
        <Label htmlFor="jezik">Jezik</Label>
        <Input id="jezik" {...register("jezik")} placeholder="npr. Srpski" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="brojStranica">Broj stranica</Label>
        <Input id="brojStranica" {...register("brojStranica")} type="number" placeholder="npr. 12" disabled={disabled} />
      </div>
    </div>
  );
}

// ─── Polja: Odeća ───────────────────────────────────────────────────────────

function PoljaOdeca({ register, disabled }: { register: UseFormRegister<any>; disabled?: boolean }) {
  return (
    <div className="grid grid-cols-2 gap-4">
      <div className="space-y-2">
        <Label htmlFor="vrstaOdevnogPredmeta">Vrsta odevnog predmeta</Label>
        <Input id="vrstaOdevnogPredmeta" {...register("vrstaOdevnogPredmeta")} placeholder="npr. Jakna" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="velicina">Veličina</Label>
        <Input id="velicina" {...register("velicina")} placeholder="npr. XL" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="boja">Boja</Label>
        <Input id="boja" {...register("boja")} placeholder="npr. Crna" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="stanje">Stanje</Label>
        <NativeSelect id="stanje" {...register("stanje")} disabled={disabled}>
          <option value="">— Odaberi —</option>
          <option value="Dobro">Dobro</option>
          <option value="Oštećeno">Oštećeno</option>
          <option value="Umrljano">Umrljano</option>
          <option value="Pocepano">Pocepano</option>
        </NativeSelect>
      </div>
    </div>
  );
}

// ─── Polja: Uzorak ──────────────────────────────────────────────────────────

function PoljaUzorak({ register, disabled }: { register: UseFormRegister<any>; disabled?: boolean }) {
  return (
    <div className="grid grid-cols-2 gap-4">
      <div className="space-y-2">
        <Label htmlFor="vrstaUzorka">Vrsta uzorka</Label>
        <Input id="vrstaUzorka" {...register("vrstaUzorka")} placeholder="npr. Zemlja" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="kolicinaUzorka">Količina</Label>
        <Input id="kolicinaUzorka" {...register("kolicinaUzorka")} placeholder="npr. 50" disabled={disabled} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="jedinicaMere">Jedinica mere</Label>
        <NativeSelect id="jedinicaMere" {...register("jedinicaMere")} disabled={disabled}>
          <option value="">— Odaberi —</option>
          <option value="ml">ml</option>
          <option value="g">g</option>
          <option value="kom">kom</option>
          <option value="cm²">cm²</option>
        </NativeSelect>
      </div>
      <div className="space-y-2">
        <Label htmlFor="nacinUzorkovanjaUzorka">Način uzorkovanja</Label>
        <Input id="nacinUzorkovanjaUzorka" {...register("nacinUzorkovanjaUzorka")} placeholder="npr. Pipetom" disabled={disabled} />
      </div>
      <div className="col-span-2 space-y-2">
        <Label htmlFor="usloviCuvanjaUzorka">Uslovi čuvanja</Label>
        <Input id="usloviCuvanjaUzorka" {...register("usloviCuvanjaUzorka")} placeholder="npr. Sobna temperatura" disabled={disabled} />
      </div>
    </div>
  );
}
