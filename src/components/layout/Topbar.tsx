"use client";

import { signOut } from "next-auth/react";
import type { Uloga } from "@prisma/client";

const ULOGA_LABELS: Record<Uloga, string> = {
  ADMINISTRATOR: "Administrator",
  ISTRAZITELJ:   "Istražitelj",
  TEHNICAR:      "Tehničar",
  VESTAK:        "Veštak",
};

interface TopbarProps {
  user: { name: string; uloga: Uloga };
}

export function Topbar({ user }: TopbarProps) {
  return (
    <header
      style={{
        height: 52,
        flexShrink: 0,
        background: "#111111",
        borderBottom: "1px solid #2a2a2a",
        display: "flex",
        alignItems: "center",
        padding: "0 20px",
        gap: 20,
        position: "relative",
      }}
    >
      {/* Żółta linia na dole topbara */}
      <div
        style={{
          position: "absolute",
          bottom: 0, left: 0, right: 0,
          height: 1,
          background: "linear-gradient(90deg, #f5c518 0%, transparent 60%)",
          opacity: 0.5,
        }}
      />

      {/* Logo */}
      <span
        style={{
          fontFamily: "var(--font-display), 'Bebas Neue', sans-serif",
          fontSize: 26,
          letterSpacing: 4,
          color: "#f5c518",
          lineHeight: 1,
        }}
      >
        ForenzIS
      </span>

      {/* Separator */}
      <div style={{ width: 1, height: 20, background: "#3a3a3a" }} />

      {/* Subtitle */}
      <span
        style={{
          fontFamily: "var(--font-mono), monospace",
          fontSize: 10,
          color: "#555555",
          textTransform: "uppercase",
          letterSpacing: 1,
        }}
      >
        Forenzički informacioni sistem
      </span>

      {/* Spacer */}
      <div style={{ flex: 1 }} />

      {/* User */}
      <span
        style={{
          fontFamily: "var(--font-mono), monospace",
          fontSize: 11,
          color: "#999999",
        }}
      >
        {user.name} — {ULOGA_LABELS[user.uloga]}
      </span>

      <div style={{ width: 1, height: 20, background: "#3a3a3a" }} />

      {/* Logout */}
      <button
        onClick={() => signOut({ callbackUrl: "/login" })}
        style={{
          fontFamily: "var(--font-mono), monospace",
          fontSize: 11,
          fontWeight: 600,
          textTransform: "uppercase",
          letterSpacing: 1,
          padding: "5px 12px",
          border: "1px solid #3a3a3a",
          background: "transparent",
          color: "#999999",
          cursor: "pointer",
          transition: "all 140ms ease",
        }}
        onMouseEnter={(e) => {
          (e.currentTarget as HTMLButtonElement).style.color = "#f0ede8";
          (e.currentTarget as HTMLButtonElement).style.borderColor = "#999999";
        }}
        onMouseLeave={(e) => {
          (e.currentTarget as HTMLButtonElement).style.color = "#999999";
          (e.currentTarget as HTMLButtonElement).style.borderColor = "#3a3a3a";
        }}
      >
        Odjava
      </button>
    </header>
  );
}
