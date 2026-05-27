// ─── Timeline prikaz lanca čuvanja dokaza ───────────────────────────────────

interface LanacZapis {
  id: number;
  akcija: string;
  datumVreme: Date;
  napomena: string | null;
  tehnicar: {
    korisnik: {
      ime: string;
      prezime: string;
    };
  };
}

interface LanacCuvanjaVremenskaLinijaProps {
  zapisi: LanacZapis[];
}

export function LanacCuvanjaVremenskaLinija({ zapisi }: LanacCuvanjaVremenskaLinijaProps) {
  // Ako nema zapisa, ne prikazujemo ništa
  if (zapisi.length === 0) return null;

  return (
    <div className="space-y-0">
      {zapisi.map((zapis, index) => (
        <div key={zapis.id} className="flex gap-4">
          {/* Vertikalna linija sa tačkom */}
          <div className="flex flex-col items-center">
            {/* Tačka */}
            <div className="h-3 w-3 rounded-full bg-fis-yellow flex-shrink-0 mt-1.5" />
            {/* Linija (ne prikazujemo posle poslednjeg) */}
            {index < zapisi.length - 1 && (
              <div className="w-0.5 flex-1 bg-fis-surface3" />
            )}
          </div>

          {/* Sadržaj zapisa */}
          <div className="pb-6 min-w-0">
            {/* Akcija */}
            <p className="text-sm font-medium text-fis-text1">{zapis.akcija}</p>
            {/* Datum i tehničar */}
            <p className="text-xs text-fis-text2 mt-0.5">
              {new Date(zapis.datumVreme).toLocaleDateString("sr-RS", {
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
              })}
              {" — "}
              {zapis.tehnicar.korisnik.ime} {zapis.tehnicar.korisnik.prezime}
            </p>
            {/* Napomena */}
            {zapis.napomena && (
              <p className="text-xs text-fis-text3 mt-1 italic">{zapis.napomena}</p>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}
