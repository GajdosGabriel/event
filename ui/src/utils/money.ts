/**
 * Ceny drží backend v centoch (`price_amount`), zobrazujú sa v mene podujatia.
 * Formátovanie bolo doteraz skopírované v piatich komponentoch — jediné miesto
 * zaručí, že sa všade mení rovnako (napr. pri prechode na inú lokalizáciu).
 */
export function formatPrice(amountInCents: number, currency: string | null = 'EUR'): string {
  return new Intl.NumberFormat('sk-SK', {
    style: 'currency',
    currency: currency ?? 'EUR',
  }).format(amountInCents / 100)
}

/** „Zdarma" pre nulovú alebo chýbajúcu cenu — inak naformátovaná suma. */
export function formatPriceOrFree(amountInCents: number | null | undefined, currency: string | null = 'EUR'): string {
  return amountInCents ? formatPrice(amountInCents, currency) : 'Zdarma'
}
