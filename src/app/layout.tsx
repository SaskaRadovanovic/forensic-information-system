import type { Metadata } from "next";
import { IBM_Plex_Sans, IBM_Plex_Mono, Bebas_Neue } from "next/font/google";
import "./globals.css";
import { Providers } from "@/components/providers";

const ibmPlexSans = IBM_Plex_Sans({
  variable: "--font-sans",
  subsets: ["latin"],
  weight: ["400", "500", "600"],
});

const ibmPlexMono = IBM_Plex_Mono({
  variable: "--font-mono",
  subsets: ["latin"],
  weight: ["400", "500", "600"],
});

const bebasNeue = Bebas_Neue({
  variable: "--font-display",
  subsets: ["latin"],
  weight: ["400"],
});

export const metadata: Metadata = {
  title: "FIS — Forenzički informacioni sistem",
  description: "Forenzički informacioni sistem — upravljanje dokazima i istragama",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="sr" className={`${ibmPlexSans.variable} ${ibmPlexMono.variable} ${bebasNeue.variable} dark h-full`}>
      <body className="h-full bg-fis-bg font-sans antialiased">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
