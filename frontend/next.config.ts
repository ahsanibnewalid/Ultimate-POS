import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  poweredByHeader: false,
  // Shared cPanel hosting without Node.js serves the exported files as static assets.
  output: "export",
  trailingSlash: true,
  images: { unoptimized: true },
};

export default nextConfig;
