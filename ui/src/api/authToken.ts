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

/**
 * Vlastný token super-admina počas prihlásenia do cudzieho účtu. Kým tu je,
 * `authToken` patrí tomu používateľovi; návrat ho vráti späť.
 */
const IMPERSONATOR_KEY = 'auth_token_impersonator'

function readImpersonator(): string | null {
  try {
    return localStorage.getItem(IMPERSONATOR_KEY)
  } catch {
    return null
  }
}

export const impersonatorToken = ref<string | null>(readImpersonator())

export function beginImpersonation(token: string): void {
  // Odložíme len pôvodný token admina — vnorené prihlásenie backend nepustí.
  if (impersonatorToken.value === null && authToken.value !== null) {
    impersonatorToken.value = authToken.value
    try {
      localStorage.setItem(IMPERSONATOR_KEY, authToken.value)
    } catch {
      /* bez úložiska sa po zatvorení karty admin prihlási znova */
    }
  }
  setAuthToken(token)
}

/** Vráti token admina. `false`, ak sa nebolo kam vrátiť. */
export function endImpersonation(): boolean {
  const token = impersonatorToken.value
  if (token === null) return false
  impersonatorToken.value = null
  try {
    localStorage.removeItem(IMPERSONATOR_KEY)
  } catch {
    /* nemáme čo mazať */
  }
  setAuthToken(token)
  return true
}

export function clearAuthToken(): void {
  authToken.value = null
  try {
    localStorage.removeItem(STORAGE_KEY)
  } catch {
    /* nemáme čo mazať */
  }
}
