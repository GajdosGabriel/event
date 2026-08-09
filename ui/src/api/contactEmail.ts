import http from './index'
import type { ContactEmailState, ContactEmailTarget } from '@/types'

/**
 * Overovanie kontaktných e-mailov (kanál, miesto, podujatie, organizátor).
 *
 * Stav adresy chodí v detaile modelu ako `email_verification`; tu sú len dve
 * akcie — potvrdenie odkazu z e-mailu a opätovné odoslanie z formulára.
 */

export interface ContactEmailVerifyResult {
  message: string
  type: ContactEmailTarget | null
  name: string | null
  email: string | null
}

/** Prevod odpovede API na tvar, s ktorým pracujú komponenty. */
export function mapContactEmailState(raw: unknown): ContactEmailState | null {
  if (!raw || typeof raw !== 'object') return null

  const data = raw as Record<string, unknown>

  return {
    verified: Boolean(data['verified']),
    pending: data['pending'] === undefined ? undefined : Boolean(data['pending']),
    sentAt: (data['sent_at'] as string) ?? null,
    canResend: data['can_resend'] === undefined ? undefined : Boolean(data['can_resend']),
    retryAfter: (data['retry_after'] as string) ?? null,
  }
}

/** Potvrdenie adresy odkazom z e-mailu. Bez prihlásenia — autorizuje token. */
export async function verifyContactEmail(token: string): Promise<ContactEmailVerifyResult> {
  const { data } = await http.post('/contact-email/verify', { token })
  const payload = (data.data ?? {}) as Record<string, unknown>

  return {
    message: (data.message as string) ?? '',
    type: (payload['type'] as ContactEmailTarget) ?? null,
    name: (payload['name'] as string) ?? null,
    email: (payload['email'] as string) ?? null,
  }
}

/** „Poslať znova" z formulára. Vyžaduje právo model upraviť. */
export async function resendContactEmail(
  type: ContactEmailTarget,
  id: number,
): Promise<{ message: string }> {
  const { data } = await http.post('/contact-email/resend', { type, id })

  return { message: (data.message as string) ?? '' }
}
