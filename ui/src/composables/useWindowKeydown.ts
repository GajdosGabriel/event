import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Naviaže obsluhu klávesnice na `window` na dobu života komponentu.
 *
 * Vue nemá modifikátor `.window` — to je syntax Alpine.js. Zápis ako
 * `@keydown.esc.window="close"` sa preto ticho ignoruje a skratka nefunguje
 * (element navyše nie je fokusovaný, takže sa keydown k nemu ani nedostane).
 */
export function useWindowKeydown(handler: (event: KeyboardEvent) => void) {
  onMounted(() => window.addEventListener('keydown', handler))
  onBeforeUnmount(() => window.removeEventListener('keydown', handler))
}
