// ─── Badge komponenta za status analize sa color-kodiranjem ──────────────────

import { StatusAnalize } from "@prisma/client";

const CONFIG: Record<StatusAnalize, { boja: string; naziv: string }> = {
  KREIRAN:    { boja: "text-fis-text2 border-fis-border",        naziv: "Kreiran"     },
  DODELJEN:   { boja: "text-fis-blue border-fis-blue/40",        naziv: "Dodeljen"    },
  U_TOKU:     { boja: "text-fis-yellow border-fis-yellow/40",    naziv: "U toku"      },
  ZAVRSEN:    { boja: "text-fis-green border-fis-green/40",      naziv: "Završen"     },
  PREKORACEN: { boja: "text-fis-red border-fis-red/40",          naziv: "Prekoračen"  },
  ODBIJEN:    { boja: "text-fis-text3 border-fis-border-hi",     naziv: "Odbijen"     },
};

interface StatusBadgeProps {
  status: StatusAnalize;
  className?: string;
}

export function StatusBadge({ status, className }: StatusBadgeProps) {
  const { boja, naziv } = CONFIG[status] ?? { boja: "text-fis-text2 border-fis-border", naziv: status };
  return (
    <span className={`fis-badge ${boja} ${className ?? ""}`}>
      {naziv}
    </span>
  );
}

export function tipAnalizeNaziv(tip: string): string {
  const mapa: Record<string, string> = {
    BALISTICKA:     "Balistička",
    DNK:            "DNK",
    DIGITALNA:      "Digitalna forenzika",
    HEMIJSKA:       "Hemijska",
    TOKSIKOLOSKA:   "Toksikološka",
    DOKUMENTOLOSKA: "Dokumentološka",
    DRUGA:          "Druga",
  };
  return mapa[tip] ?? tip;
}
