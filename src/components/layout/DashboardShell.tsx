"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState, useEffect } from "react";
import { cn } from "@/lib/utils";
import { Topbar } from "./Topbar";
import type { Uloga } from "@prisma/client";

// ── Nav structure ────────────────────────────────────────────────────────────

interface NavItem {
  href: string;
  label: string;
  adminOnly?: boolean;
  ulogeKojeVide?: Uloga[];
}

interface NavSection {
  label: string;
  items: NavItem[];
}

const NAV_SECTIONS: NavSection[] = [
  {
    label: "Dokumentacija",
    items: [
      { href: "/dokumentacija", label: "Dokumenti" },
      { href: "/tagovi", label: "Tagovi", adminOnly: true },
    ],
  },
  {
    label: "Predmeti",
    items: [
      { href: "/predmeti", label: "Predmeti" },
    ],
  },
  {
    label: "Forenzika",
    items: [
      { href: "/dokazi", label: "Evidencija dokaza" },
      { href: "/dokazi/zahtevi", label: "Zahtevi za dokaze", ulogeKojeVide: ["TEHNICAR", "ADMINISTRATOR"] },
      { href: "/analize", label: "Zahtevi za analizu", ulogeKojeVide: ["ISTRAZITELJ", "ADMINISTRATOR", "VESTAK"] },
    ],
  },
  {
    label: "Sistem",
    items: [
      { href: "/obavestenja", label: "Obaveštenja" },
      {
        href: "/izvestaji/analize",
        label: "Izveštaji analiza",
        ulogeKojeVide: ["ADMINISTRATOR", "ISTRAZITELJ"],
      },
    ],
  },
];

// ── Props ────────────────────────────────────────────────────────────────────

interface User {
  name: string;
  email: string;
  uloga: Uloga;
}

interface DashboardShellProps {
  user: User;
  children: React.ReactNode;
}

// ── Component ────────────────────────────────────────────────────────────────

export function DashboardShell({ user, children }: DashboardShellProps) {
  const pathname = usePathname();
  const [neprocitano, setNeprocitano] = useState(0);

  useEffect(() => {
    async function ucitajBroj() {
      try {
        const res = await fetch("/api/obavestenja?samo_broj=true");
        const data = await res.json();
        if (data.ok) setNeprocitano(data.neprocitano ?? 0);
      } catch {
        // ignorišemo mrežne greške
      }
    }
    ucitajBroj();
    const interval = setInterval(ucitajBroj, 60_000);
    return () => clearInterval(interval);
  }, []);

  function isActive(href: string) {
    return pathname === href || pathname.startsWith(href + "/");
  }

  return (
    <div className="flex h-screen flex-col" style={{ background: "#0a0a0a" }}>
      {/* ── Topbar ──────────────────────────────────────────────────────── */}
      <Topbar user={user} />

      {/* ── Body ────────────────────────────────────────────────────────── */}
      <div className="flex flex-1 overflow-hidden">

        {/* ── Sidebar ─────────────────────────────────────────────────── */}
        <aside
          className="flex flex-shrink-0 flex-col overflow-y-auto"
          style={{
            width: 210,
            background: "#111111",
            borderRight: "1px solid #2a2a2a",
            paddingTop: 16,
            paddingBottom: 16,
          }}
        >
          {NAV_SECTIONS.map((section) => {
            const visibleItems = section.items.filter((item) => {
              if (item.adminOnly && user.uloga !== "ADMINISTRATOR") return false;
              if (item.ulogeKojeVide && !item.ulogeKojeVide.includes(user.uloga)) return false;
              return true;
            });
            if (visibleItems.length === 0) return null;

            return (
              <div key={section.label} className="mb-2">
                {/* Section label */}
                <p
                  className="px-[18px] pb-[6px] pt-[16px]"
                  style={{
                    fontFamily: "var(--font-mono), monospace",
                    fontSize: 9,
                    textTransform: "uppercase",
                    letterSpacing: "2px",
                    color: "#555555",
                  }}
                >
                  {section.label}
                </p>

                {/* Nav items */}
                {visibleItems.map((item) => {
                  const active = isActive(item.href);
                  return (
                    <Link
                      key={item.href}
                      href={item.href}
                      style={{
                        display: "flex",
                        alignItems: "center",
                        padding: "9px 18px",
                        fontFamily: "var(--font-mono), monospace",
                        fontSize: 12,
                        color: active ? "#f5c518" : "#999999",
                        background: active ? "#2a2200" : "transparent",
                        borderLeft: active ? "2px solid #f5c518" : "2px solid transparent",
                        textDecoration: "none",
                        transition: "all 140ms ease",
                        gap: 8,
                      }}
                    >
                      <span style={{ flex: 1 }}>{item.label}</span>
                      {/* Badge za nepročitana obaveštenja */}
                      {item.href === "/obavestenja" && neprocitano > 0 && (
                        <span
                          style={{
                            background: "#f5c518",
                            color: "#000",
                            fontFamily: "var(--font-mono), monospace",
                            fontSize: 9,
                            fontWeight: 700,
                            padding: "1px 5px",
                            minWidth: 15,
                            textAlign: "center",
                          }}
                        >
                          {neprocitano > 99 ? "99+" : neprocitano}
                        </span>
                      )}
                    </Link>
                  );
                })}
              </div>
            );
          })}
        </aside>

        {/* ── Main ────────────────────────────────────────────────────── */}
        <main
          className="flex-1 overflow-y-auto"
          style={{ background: "#0a0a0a", padding: "28px 32px" }}
        >
          {children}
        </main>
      </div>
    </div>
  );
}
