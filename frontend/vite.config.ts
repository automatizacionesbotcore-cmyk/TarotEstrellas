import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ command }) => ({
  // In WAMP this project is served from a subdirectory, so production assets
  // need an absolute base path. Dev server keeps root-based paths.
  base: command === 'build' ? '/tarotEstrella/tarotestrellas/frontend/dist/' : '/',
  plugins: [react(), tailwindcss()],
}));
