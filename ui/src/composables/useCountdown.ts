import { computed, onBeforeUnmount, onMounted, ref, type Ref } from 'vue'
import { localeTag } from '@/i18n'

export interface CountdownPart {
  value: string
  /** Skratka jednotky v jazyku rozhrania („d", „h", „min", „s"). */
  unit: string
}

const UNITS = ['day', 'hour', 'minute', 'second'] as const

/**
 * Skratku jednotky berieme z Intl, nie zo slovníka — prehliadač ju vie
 * v každom z jazykov rozhrania a pri pridaní ďalšieho netreba nič prekladať.
 */
function unitLabel(unit: (typeof UNITS)[number]): string {
  // `narrow`, nie `short`: krátky tvar sa v slovenčine skloňuje („1 deň",
  // „2 dni"), úzky je jedno písmeno bez ohľadu na počet.
  const parts = new Intl.NumberFormat(localeTag(), { style: 'unit', unit, unitDisplay: 'narrow' }).formatToParts(2)
  return parts.find(part => part.type === 'unit')?.value ?? unit
}

/**
 * Živý odpočet do daného okamihu: dni, hodiny, minúty, sekundy. Po termíne
 * (alebo bez neho) vracia `null`, aby volajúci nemusel kresliť samé nuly.
 */
export function useCountdown(target: Ref<string | null | undefined>) {
  const now = ref(Date.now())
  let timer: ReturnType<typeof setInterval> | undefined

  onMounted(() => { timer = setInterval(() => { now.value = Date.now() }, 1000) })
  onBeforeUnmount(() => clearInterval(timer))

  const parts = computed<CountdownPart[] | null>(() => {
    if (!target.value) return null
    const end = new Date(target.value).getTime()
    if (Number.isNaN(end)) return null
    const left = Math.floor((end - now.value) / 1000)
    if (left <= 0) return null

    const values = [
      Math.floor(left / 86_400),
      Math.floor((left % 86_400) / 3600),
      Math.floor((left % 3600) / 60),
      left % 60,
    ]
    return UNITS.map((unit, index) => ({
      // Dni môžu mať tri cifry; ostatné držia šírku dvoma, aby odpočet neposkakoval.
      value: index === 0 ? String(values[index]) : String(values[index]).padStart(2, '0'),
      unit: unitLabel(unit),
    }))
  })

  return { parts, now }
}
