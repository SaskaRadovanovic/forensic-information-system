import type { Uloga } from "@prisma/client";
import "next-auth";
import "next-auth/jwt";

declare module "next-auth" {
  interface Session {
    user: {
      id: string;
      email: string;
      name: string;
      uloga: Uloga;
    };
  }

  interface User {
    id: string;
    uloga: Uloga;
  }
}

declare module "next-auth/jwt" {
  interface JWT {
    id: string;
    uloga: Uloga;
  }
}
