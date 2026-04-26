import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ command }) => ({
  // Dev: WAMP subdirectory. Build: root path for Hostinger (tarotestrellas.cl).
  base: command === 'build' ? '/' : '/',
  plugins: [react(), tailwindcss()],
}));
