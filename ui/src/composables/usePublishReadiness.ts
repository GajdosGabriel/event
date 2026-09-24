import { textLength, satisfies } from '@/utils/publishReadiness'
import { computed, ref, watchEffect } from 'vue'
import { fetchReadinessRules, type AiKind, type ReadinessRule, type Scope } from '@/api/ai'

/**
 * „Je záznam hotový na zverejnenie?" — priebežne, počas písania.
 *
 * Pravidlá NEDEFINUJE. Sťahuje ich z `GET {scope}/publish-readiness`, kde ich
 * má na starosti `config/content_review.php` — tá istá tabuľka, podľa ktorej sa
 * na serveri rozhoduje, či sa zverejnený text pošle na kontrolu. Kým bola tá
 * istá otázka napísaná dvakrát, ticho si odpovedali inak: formulár tvrdil, že
 * je všetko v poriadku, a e-mail o pár minút tvrdil opak.
 *
 * Vedome to NIE je validácia. Nepripravený záznam sa zverejniť dá — toto je
 * merítko a odporúčanie, nie zámok. Riadi dve veci:
 *
 *  - ukazovateľ „hotové 4 zo 6" so zoznamom, čo ešte chýba,
 *  - viditeľnosť panela „Vyplniť pomocou AI" (viď AiAssistPanel.vue).
 *
 * Panel sa objaví až pri hotovom zázname zámerne. AI má vylepšiť hotový text,
 * nie ho vymyslieť: keby svietil nad prázdnym formulárom, prvé, čo by od nej
 * ľudia chceli, je „napíš mi popis" — a z názvu podujatia sa dá napísať len
 * výmysel.
 */
export function usePublishReadiness(
  scope: Scope,
  kind: AiKind,
  /** Ploché hodnoty formulára pod menami z konfigurácie (`body`, `venue_id`…). */
  values: () => Record<string, unknown>,
) {
  const rules = ref<ReadinessRule[]>([])
  const loaded = ref(false)

  watchEffect(async () => {
    try {
      const all = await fetchReadinessRules(scope)
      rules.value = all[kind] ?? []
    } catch {
      // Bez pravidiel sa formulár správa, akoby žiadne neboli — ukazovateľ
      // sa nezobrazí a panel ostane skrytý. Výpadok pomôcky nesmie brániť
      // v uložení záznamu.
      rules.value = []
    } finally {
      loaded.value = true
    }
  })

  /** Kľúče podmienok, ktoré ešte nie sú splnené — v poradí z konfigurácie. */
  const missing = computed(() => {
    const v = values()
    return rules.value.filter(r => !satisfies(r, v)).map(r => r.key)
  })

  const total = computed(() => rules.value.length)
  const satisfied = computed(() => total.value - missing.value.length)

  /** Hotové. Prázdny zoznam pravidiel sa za hotový nepovažuje. */
  const ready = computed(() => total.value > 0 && missing.value.length === 0)

  const percent = computed(() => total.value === 0 ? 0 : Math.round((satisfied.value / total.value) * 100))

  return { rules, loaded, missing, satisfied, total, ready, percent, textLength }
}
