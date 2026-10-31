import { useI18n } from '@/i18n'

/**
 * Zrozumiteľné názvy udalostí denníka (`canals.outreach_sent` →
 * „Oslovenie kanála odoslané“). Neznáma udalosť sa ukáže surovým kľúčom,
 * aby nový záznam nikdy nezmizol len pre chýbajúci preklad.
 */
export function useSystemLogLabels() {
  const { t } = useI18n()

  function eventLabel(event: string): string {
    const key = `systemLog.ev.${event.replace(/\./g, '_')}`
    const translated = t(key as Parameters<typeof t>[0])
    return translated === key ? event : translated
  }

  /** Stav môže prísť aj z Accountu (dôvod odmietnutia), neznámy sa ukáže surovo. */
  function statusLabel(status: string): string {
    const key = `systemLog.status.${status}`
    const translated = t(key as Parameters<typeof t>[0])
    return translated === key ? status : translated
  }

  return { eventLabel, statusLabel }
}
