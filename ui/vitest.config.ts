import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/*.spec.ts'],
    // Formátovacie helpery používajú toLocaleDateString('sk-SK'), takže výsledok
    // závisí od časovej zóny stroja. Bez fixnej zóny by testy prechádzali
    // lokálne a padali v CI (ktoré beží v UTC).
    env: {
      TZ: 'Europe/Bratislava',
    },
  },
})
