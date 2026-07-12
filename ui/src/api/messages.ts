import http from './index'

/** Typy cieľov, ktorým sa dá poslať správa (musia byť vo whitelist na backende). */
export type MessageTargetType = 'event' | 'venue' | 'canal'

export interface SendMessagePayload {
  target_type: MessageTargetType
  target_id: number
  body: string
}

/** Posielať môžu len prihlásení a overení používatelia — hostí front vyzve na registráciu. */
export async function sendMessage(payload: SendMessagePayload): Promise<void> {
  await http.post('/messages', payload)
}
