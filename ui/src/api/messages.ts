import http from './index'

/** Typy cieľov, ktorým sa dá poslať správa (musia byť vo whitelist na backende). */
export type MessageTargetType = 'event' | 'venue' | 'canal'

export interface SendMessagePayload {
  target_type: MessageTargetType
  target_id: number
  body: string
  /** Povinné len pre neprihláseného odosielateľa. */
  sender_name?: string
  sender_email?: string
}

export async function sendMessage(payload: SendMessagePayload): Promise<void> {
  await http.post('/messages', payload)
}
