import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createHead } from '@vueuse/head'
import router from './router'
import App from './App.vue'
import { initI18n } from './i18n'
import './styles.css'

initI18n()

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(createHead())
app.mount('#app')

/**
 * Service worker. Registruje sa až po `load`, aby nesúťažil o sieť s prvým
 * vykreslením, a len v produkčnom builde — v dev režime by cachoval moduly,
 * ktoré Vite práve prepisuje, a HMR by prestalo dávať zmysel.
 *
 * Beží kvôli dvom offline situáciám: skener pri vchode a vstupenka s QR kódom
 * v telefóne. Podrobnosti o stratégii sú v `public/sw.js`.
 */
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {
      // Zamietnutá registrácia (privátny režim, zakázané cookies) nie je nič,
      // s čím by mohol návštevník niečo spraviť — appka funguje aj bez nej.
    })
  })
}
