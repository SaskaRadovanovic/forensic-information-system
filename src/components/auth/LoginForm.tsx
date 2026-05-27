"use client";

import { useState } from "react";
import { signIn } from "next-auth/react";
import { useRouter } from "next/navigation";

export function LoginForm() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [lozinka, setLozinka] = useState("");
  const [loading, setLoading] = useState(false);
  const [greska, setGreska] = useState<string | null>(null);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setGreska(null);

    const result = await signIn("credentials", { email, lozinka, redirect: false });

    if (result?.error) {
      setGreska("Pogrešan email ili lozinka.");
      setLoading(false);
    } else {
      router.push("/dokumentacija");
      router.refresh();
    }
  }

  return (
    <div style={{ width: 380, border: "1px solid #3a3a3a", background: "#111111", position: "relative" }}>
      {/* Header */}
      <div style={{ padding: "28px 32px 22px", borderBottom: "1px solid #2a2a2a", position: "relative", overflow: "hidden" }}>
        {/* Yellow accent line */}
        <div style={{ position: "absolute", bottom: 0, left: 0, right: 0, height: 2, background: "#f5c518" }} />
        <div
          style={{
            fontFamily: "var(--font-display), 'Bebas Neue', sans-serif",
            fontSize: 36,
            letterSpacing: 4,
            color: "#f5c518",
            lineHeight: 1,
          }}
        >
          ForenzIS
        </div>
        <div
          style={{
            fontFamily: "var(--font-mono), monospace",
            fontSize: 10,
            color: "#555555",
            marginTop: 6,
            textTransform: "uppercase",
            letterSpacing: 1,
          }}
        >
          Forenzički informacioni sistem
        </div>
      </div>

      {/* Body */}
      <form onSubmit={handleSubmit} style={{ padding: "28px 32px" }}>
        {greska && (
          <div
            style={{
              marginBottom: 14,
              border: "1px solid rgba(239,68,68,0.4)",
              background: "rgba(239,68,68,0.08)",
              padding: "9px 12px",
              fontFamily: "var(--font-mono), monospace",
              fontSize: 12,
              color: "#ef4444",
            }}
          >
            {greska}
          </div>
        )}

        {/* Email */}
        <div style={{ marginBottom: 14 }}>
          <label
            htmlFor="email"
            style={{
              display: "block",
              fontFamily: "var(--font-mono), monospace",
              fontSize: 10,
              textTransform: "uppercase",
              letterSpacing: "1.5px",
              color: "#555555",
              marginBottom: 6,
            }}
          >
            Email
          </label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="korisnik@fis.rs"
            required
            autoComplete="email"
            style={{
              width: "100%",
              background: "#181818",
              border: "1px solid #2a2a2a",
              color: "#f0ede8",
              fontFamily: "var(--font-mono), monospace",
              fontSize: 13,
              padding: "9px 12px",
              outline: "none",
              transition: "border-color 140ms ease",
            }}
            onFocus={(e) => { e.currentTarget.style.borderColor = "#f5c518"; e.currentTarget.style.background = "#202020"; }}
            onBlur={(e) => { e.currentTarget.style.borderColor = "#2a2a2a"; e.currentTarget.style.background = "#181818"; }}
          />
        </div>

        {/* Lozinka */}
        <div style={{ marginBottom: 20 }}>
          <label
            htmlFor="lozinka"
            style={{
              display: "block",
              fontFamily: "var(--font-mono), monospace",
              fontSize: 10,
              textTransform: "uppercase",
              letterSpacing: "1.5px",
              color: "#555555",
              marginBottom: 6,
            }}
          >
            Lozinka
          </label>
          <input
            id="lozinka"
            type="password"
            value={lozinka}
            onChange={(e) => setLozinka(e.target.value)}
            required
            autoComplete="current-password"
            style={{
              width: "100%",
              background: "#181818",
              border: "1px solid #2a2a2a",
              color: "#f0ede8",
              fontFamily: "var(--font-mono), monospace",
              fontSize: 13,
              padding: "9px 12px",
              outline: "none",
              transition: "border-color 140ms ease",
            }}
            onFocus={(e) => { e.currentTarget.style.borderColor = "#f5c518"; e.currentTarget.style.background = "#202020"; }}
            onBlur={(e) => { e.currentTarget.style.borderColor = "#2a2a2a"; e.currentTarget.style.background = "#181818"; }}
          />
        </div>

        {/* Submit */}
        <button
          type="submit"
          disabled={loading}
          style={{
            width: "100%",
            background: loading ? "#c9a015" : "#f5c518",
            border: "none",
            color: "#000000",
            fontFamily: "var(--font-mono), monospace",
            fontSize: 12,
            fontWeight: 600,
            textTransform: "uppercase",
            letterSpacing: "1.5px",
            padding: "11px 0",
            cursor: loading ? "not-allowed" : "pointer",
            transition: "background 140ms ease",
          }}
          onMouseEnter={(e) => { if (!loading) (e.currentTarget as HTMLButtonElement).style.background = "#c9a015"; }}
          onMouseLeave={(e) => { if (!loading) (e.currentTarget as HTMLButtonElement).style.background = "#f5c518"; }}
        >
          {loading ? "Prijavljivanje..." : "Prijavi se"}
        </button>
      </form>
    </div>
  );
}
