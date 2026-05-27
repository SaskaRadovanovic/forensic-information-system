"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { obrisiPredmetPotpuno } from "@/app/(dashboard)/predmeti/actions";

interface Props {
  predmetId: number;
  naziv: string;
}

export function ObrisiPredmetPotpunoDugme({ predmetId, naziv }: Props) {
  const router = useRouter();
  const [isPending, startTransition] = useTransition();
  const [greska, setGreska] = useState<string | null>(null);
  const [otvoren, setOtvoren] = useState(false);
  const [unosNaziva, setUnosNaziva] = useState("");

  function handleOtvori() {
    setOtvoren(true);
    setGreska(null);
    setUnosNaziva("");
  }

  function handleOtkazi() {
    setOtvoren(false);
    setUnosNaziva("");
    setGreska(null);
  }

  function handleBrisi() {
    if (unosNaziva.trim() !== naziv.trim()) {
      setGreska("Naziv se ne poklapa. Unesite tačan naziv predmeta.");
      return;
    }
    startTransition(async () => {
      const result = await obrisiPredmetPotpuno(predmetId);
      if (!result.ok) {
        setGreska(result.greska);
      } else {
        router.push("/predmeti");
      }
    });
  }

  return (
    <>
      {/* Dugme koje otvara dijalog */}
      <button
        className="fis-btn fis-btn-danger fis-btn-sm"
        onClick={handleOtvori}
        disabled={isPending}
      >
        Obriši sve
      </button>

      {/* Modal dijalog za potvrdu */}
      {otvoren && (
        <div style={{
          position: "fixed", inset: 0, zIndex: 1000,
          background: "rgba(0,0,0,0.7)",
          display: "flex", alignItems: "center", justifyContent: "center",
        }}>
          <div style={{
            background: "var(--color-fis-surface)",
            border: "1px solid var(--color-fis-red)",
            padding: 28,
            maxWidth: 460,
            width: "90%",
          }}>
            {/* Naslov */}
            <div style={{
              fontFamily: "var(--font-mono)", fontSize: 11, fontWeight: 700,
              textTransform: "uppercase", letterSpacing: "2px",
              color: "var(--color-fis-red)", marginBottom: 12,
            }}>
              ⚠ Nepovratno brisanje predmeta
            </div>

            {/* Opis */}
            <p style={{
              fontFamily: "var(--font-mono)", fontSize: 12,
              color: "var(--color-fis-text2)", lineHeight: 1.7, marginBottom: 20,
            }}>
              Ovo će trajno obrisati predmet <strong style={{ color: "var(--color-fis-text1)" }}>„{naziv}"</strong> zajedno sa svim vezanim podacima:
              dokazima, lancima čuvanja, zahtevima za analizu, rezultatima, dokumentima i svim istorijatima.
            </p>

            {/* Polje za potvrdu */}
            <div className="fis-form-group" style={{ marginBottom: 16 }}>
              <label style={{
                fontFamily: "var(--font-mono)", fontSize: 10,
                textTransform: "uppercase", letterSpacing: "1.5px",
                color: "var(--color-fis-text3)", display: "block", marginBottom: 6,
              }}>
                Unesite naziv predmeta za potvrdu
              </label>
              <input
                type="text"
                className="fis-input"
                style={{ width: "100%" }}
                placeholder={naziv}
                value={unosNaziva}
                onChange={(e) => setUnosNaziva(e.target.value)}
                disabled={isPending}
                autoFocus
              />
            </div>

            {/* Greška */}
            {greska && (
              <div className="fis-alert fis-alert-red" style={{ marginBottom: 16 }}>
                {greska}
              </div>
            )}

            {/* Dugmad */}
            <div style={{ display: "flex", gap: 8 }}>
              <button
                className="fis-btn fis-btn-danger"
                onClick={handleBrisi}
                disabled={isPending || !unosNaziva.trim()}
              >
                {isPending ? "Brisanje…" : "Obriši trajno"}
              </button>
              <button
                className="fis-btn fis-btn-ghost"
                onClick={handleOtkazi}
                disabled={isPending}
              >
                Otkaži
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
