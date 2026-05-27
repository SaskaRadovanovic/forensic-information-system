import NextAuth, { type NextAuthOptions } from "next-auth";
import CredentialsProvider from "next-auth/providers/credentials";
import { prisma } from "@/lib/prisma";
import bcrypt from "bcryptjs";
import type { Uloga } from "@prisma/client";

export const authOptions: NextAuthOptions = {
  session: {
    strategy: "jwt",
  },
  pages: {
    signIn: "/login",
  },
  providers: [
    CredentialsProvider({
      name: "Credentials",
      credentials: {
        email: { label: "Email", type: "email" },
        lozinka: { label: "Lozinka", type: "password" },
      },
      async authorize(credentials) {
        if (!credentials?.email || !credentials?.lozinka) {
          return null;
        }

        const korisnik = await prisma.korisnik.findUnique({
          where: { email: credentials.email },
        });

        if (!korisnik || !korisnik.aktivan) {
          return null;
        }

        const lozinkaOk = await bcrypt.compare(
          credentials.lozinka,
          korisnik.lozinkaHash
        );

        if (!lozinkaOk) {
          return null;
        }

        return {
          id: String(korisnik.id),
          email: korisnik.email,
          name: `${korisnik.ime} ${korisnik.prezime}`,
          uloga: korisnik.uloga,
        };
      },
    }),
  ],
  callbacks: {
    async jwt({ token, user }) {
      if (user) {
        token.id = user.id;
        token.uloga = (user as { uloga: Uloga }).uloga;
      }
      return token;
    },
    async session({ session, token }) {
      if (session.user) {
        session.user.id = token.id as string;
        session.user.uloga = token.uloga as Uloga;
      }
      return session;
    },
  },
};

export default NextAuth(authOptions);
