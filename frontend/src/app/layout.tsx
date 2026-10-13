import type { Metadata } from "next";
import "./globals.css";
export const metadata: Metadata = { title: "Ultimate POS", description: "Point of sale and inventory management" };
export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en"><body>{children}</body></html>;
}
