import { computed, ref, type ComputedRef } from 'vue'
import type { AttributeIssue, AttributeIssues } from '@/types'

/** Čokoľvek, čo má web a stav jeho overenia — kanál, miesto, podujatie, firma. */
interface HasWebsiteIssue {
  website: string | null
  attributeIssues: AttributeIssues | null
}

/**
 * Upozornenie „táto adresa nám neodpovedá" pod poľom Web vo formulári.
 *
 * Jediná vec, ktorú rieši navyše oproti obyčajnému ref-u, je tá dôležitá:
 * upozornenie sa týka **uloženej** adresy. Len čo ju organizátor v poli začne
 * meniť, zmizne — inak by mu červenal opravený riadok a nadával mu na adresu,
 * ktorú práve prepísal. Znova sa objaví až vtedy, keď overenie zlyhá aj tej
 * novej (a to sa dozvie po uložení, nie počas písania).
 */
export function useWebsiteIssue(current: () => string | null | undefined): {
  apply: (item: HasWebsiteIssue | null | undefined) => void
  issue: ComputedRef<AttributeIssue | null>
} {
  const saved = ref('')
  const state = ref<AttributeIssue | null>(null)

  function apply(item: HasWebsiteIssue | null | undefined): void {
    saved.value = item?.website ?? ''
    state.value = item?.attributeIssues?.website ?? null
  }

  const issue = computed(() => (state.value && (current() ?? '') === saved.value ? state.value : null))

  return { apply, issue }
}
