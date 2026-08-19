import http from './index'

/**
 * „Pripomeň mi" — odber podujatia bez účtu.
 *
 * Existuje preto, že na bezplatnom podujatí bez lístkov sa na verejnom detaile
 * nedá spraviť vôbec nič: registračná sekcia aj mobilná lišta sú skryté.
 */

export type SubscriptionInfo = {
  /** Názov podujatia, ktorého sa odber týka (null, keď zanikol jeho cieľ). */
  event: string | null
  /** Beží odber ešte? Po odhlásení je false. */
  active: boolean
}

function mapSubscription(raw: Record<string, unknown>): SubscriptionInfo {
  return {
    event: (raw['event'] as string) ?? null,
    active: Boolean(raw['active']),
  }
}

/**
 * Podpísaná známka, že sa formulár naozaj otvoril. Bez nej backend odoslanie
 * odmietne — pýta sa až pri otvorení formulára, nie pri načítaní stránky, aby
 * jej minimálny vek nezmeškal človeka, ktorý klikne hneď.
 */
export async function subscriptionTicket(eventId: number): Promise<string> {
  const { data } = await http.get(`/events/${eventId}/subscription`)
  return (data.ticket as string) ?? ''
}

export async function subscribeToEvent(
  eventId: number,
  payload: { email: string; ticket: string; locale: string; website?: string },
): Promise<void> {
  await http.post(`/events/${eventId}/subscription`, payload)
}

export async function showSubscription(token: string): Promise<SubscriptionInfo> {
  const { data } = await http.get(`/subscriptions/${token}`)
  return mapSubscription((data.data ?? data) as Record<string, unknown>)
}

export async function unsubscribe(token: string): Promise<SubscriptionInfo> {
  const { data } = await http.delete(`/subscriptions/${token}`)
  return mapSubscription((data.data ?? data) as Record<string, unknown>)
}
