import { computed, ref, type CSSProperties } from 'vue'

/**
 * Náklon karty za kurzorom — plagát v hlavičke detailu sa pod myšou nakláňa
 * ako list papiera a prejde po ňom odlesk.
 *
 * Len pre myš: na dotyku by náklon skákal pri každom ťuknutí a pri rolovaní
 * prstom by plagát „utekal". Kto má v systéme vypnuté animácie, nedostane nič.
 */
export function useTilt(maxDeg = 7) {
  const x = ref(0.5)
  const y = ref(0.5)
  const active = ref(false)

  const reducedMotion = typeof window !== 'undefined'
    && typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches

  function onMove(e: PointerEvent) {
    if (reducedMotion || e.pointerType !== 'mouse') return
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect()
    if (!rect.width || !rect.height) return
    x.value = (e.clientX - rect.left) / rect.width
    y.value = (e.clientY - rect.top) / rect.height
    active.value = true
  }

  function onLeave() {
    active.value = false
    x.value = 0.5
    y.value = 0.5
  }

  const style = computed<CSSProperties>(() => ({
    transform: `rotateX(${((0.5 - y.value) * 2 * maxDeg).toFixed(2)}deg) rotateY(${((x.value - 0.5) * 2 * maxDeg).toFixed(2)}deg)`,
    // Za kurzorom ide hneď, späť do roviny sa vracia pomaly.
    transition: active.value ? 'transform 80ms linear' : 'transform 600ms cubic-bezier(0.2, 0.8, 0.2, 1)',
  }))

  const glareStyle = computed<CSSProperties>(() => ({
    opacity: active.value ? 1 : 0,
    background: `radial-gradient(circle at ${(x.value * 100).toFixed(1)}% ${(y.value * 100).toFixed(1)}%, rgb(255 255 255 / 0.28), transparent 55%)`,
  }))

  return { style, glareStyle, onMove, onLeave }
}
