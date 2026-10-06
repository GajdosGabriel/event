import { useI18n } from '@/i18n'

/**
 * Zrozumiteľné názvy udalostí denníka (`canals.outreach_simulated` →
 * „Oslovenie kanála simulované“). Neznáma udalosť sa ukáže surovým kľúčom,
 * aby nový záznam nikdy nezmizol len pre chýbajúci preklad.
 */
export function useSystemLogLabels() {
  const { t } = useI18n()

  function eventLabel(event: string): string {
    const key = `systemLog.ev.${event.replace(/\./g, '_')}`
    const translated = t(key as Parameters<typeof t>[0])
    return translated === key ? event : translated
  }

  return { eventLabel }
}
