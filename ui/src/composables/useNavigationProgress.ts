import { ref } from 'vue'

/**
 * Prechod na lazy route (dashboard, editor) trvá, kým sa stiahne chunk — dovtedy
 * ostávala obrazovka prázdna. `navigating` sa zapne až po krátkej pauze, aby
 * rýchle prechody neblikali.
 */
export const navigating = ref(false)

const SHOW_DELAY_MS = 150
let timer: ReturnType<typeof setTimeout> | null = null

export function startNavigation() {
  if (timer) return
  timer = setTimeout(() => { navigating.value = true }, SHOW_DELAY_MS)
}

export function endNavigation() {
  if (timer) clearTimeout(timer)
  timer = null
  navigating.value = false
}
