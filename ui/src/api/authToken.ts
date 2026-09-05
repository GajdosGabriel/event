import { ref } from 'vue'

/**
 * Prihlasovací token na jednom mieste — a hlavne reaktívne.
 *
 * Predtým ho každý čítal priamo z `localStorage`. To je mimo reaktivity Vue:
 * `computed`, ktorý sa naň pýtal, sa po odstránení tokenu (napr. keď API
 * odpovie 401) už nikdy neprepočítal a appka ostala vizuálne prihlásená —
 * s menu „Odhlásiť sa", ale bez mena, lebo identitu sa načítať nepodarilo.
 *
 * Súbor zámerne nič neimportuje zo `stores/` ani z `api/index` — používa ho
 * aj axios interceptor, takže by inak vznikol kruhový import.
 */
const STORAGE_KEY = 'auth_token'

function read(): string | null {
  try {
    return localStorage.getItem(STORAGE_KEY)
  } catch {
    // Súkromný režim prehliadača s vypnutým úložiskom — správame sa ako
    // neprihlásený namiesto pádu celej appky.
    return null
  }
}

export const authToken = ref<string | null>(read())

export function setAuthToken(token: string): void {
  authToken.value = token
  try {
    localStorage.setItem(STORAGE_KEY, token)
  } catch {
    /* bez úložiska token prežije len do zatvorenia karty */
  }
}

export function clearAuthToken(): void {
  authToken.value = null
  try {
    localStorage.removeItem(STORAGE_KEY)
  } catch {
    /* nemáme čo mazať */
  }
}
