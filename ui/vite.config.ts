import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://event-api.local',
        changeOrigin: true,
        secure: false,
        cookieDomainRewrite: '',
      },
      '/sanctum': {
        target: 'http://event-api.local',
        changeOrigin: true,
        secure: false,
        cookieDomainRewrite: '',
      },
      '/storage': {
        target: 'http://event-api.local',
        changeOrigin: true,
        secure: false,
      },
      '/images': {
        target: 'http://event-api.local',
        changeOrigin: true,
        secure: false,
      },
    },
  },
})
