import http from './index'

export type Scope = 'dashboard' | 'admin'

/** Typ záznamu, ktorý panel obsluhuje. Zhoduje sa s kľúčmi v config/content_review.php. */
export type AiKind = 'event' | 'venue' | 'canal'

/** Čo má AI s textom spraviť. `html` dopĺňa server — popis je HTML pole. */
export type AiMode = 'grammar' | 'style' | 'expand'

export interface AiSuggestion {
  success: boolean
  improved_text?: string
  changes_summary?: string
  error?: string
}

/**
 * Návrh textu od AI. Nič neukladá — formulár ho ukáže vedľa pôvodného textu
 * a zapíše sa až po potvrdení človekom.
 *
 * `action: 'draft'` (písať od nuly) server prijme len pri mieste a kanáli;
 * pri podujatí by šlo o vymýšľanie faktov (viď AiAssistController).
 */
export async function aiAssist(
  scope: Scope,
  payload: {
    kind: AiKind
    action: 'improve' | 'draft'
    text?: string
    modes?: AiMode[]
    name?: string
    context?: string
  },
): Promise<AiSuggestion> {
  const { data } = await http.post(`/${scope}/ai/assist`, payload)
  return data as AiSuggestion
}

/** Jedna výhrada z kontroly zverejneného textu. */
export interface ContentReviewIssue {
  severity: 'notice' | 'warning'
  /** Ktorý režim panela výhradu rieši — panel si ho podľa toho zaškrtne. */
  mode: AiMode
  message: string
  /** Úryvok, ktorého sa výhrada týka. Prázdny, keď ide o text ako celok. */
  quote: string
}

export interface ContentReviewResult {
  score: number | null
  summary: string | null
  issues: ContentReviewIssue[]
  modes: AiMode[]
  reviewedAt: string | null
  /** Odtlačok textu, ktorý model videl — viď useContentReview(). */
  contentHash: string | null
}

/**
 * Uložený posudok zverejneného textu, alebo null, keď ešte nebežal.
 *
 * Ten istý obsah, aký prišiel e-mailom. Formulár si ho pýta preto, aby výhrady
 * nežili len v schránke: kto príde upravovať záznam z iného dôvodu, má ich
 * vidieť nad popisom.
 */
export async function fetchContentReview(
  scope: Scope,
  kind: AiKind,
  id: number,
): Promise<ContentReviewResult | null> {
  const { data } = await http.get(`/${scope}/ai/review/${kind}/${id}`)
  return (data.data ?? null) as ContentReviewResult | null
}

/** Jedna podmienka pripravenosti, tak ako ju definuje config/content_review.php. */
export interface ReadinessRule {
  key: string
  rule: 'filled' | 'any_of' | 'min_chars'
  fields: string[]
  value?: number
}

export type ReadinessRules = Record<AiKind, ReadinessRule[]>

/**
 * Podmienky pripravenosti pre všetky typy.
 *
 * Sťahujú sa raz za reláciu a držia sa v module — je to číselník, ktorý sa
 * medzi dvoma otvoreniami formulára nezmení, a bez tejto pamäte by ho každý
 * editor pýtal znova. Rozbehnutý dotaz sa zdieľa, nech dva formuláre otvorené
 * naraz nepošlú dva.
 */
let rulesPromise: Promise<ReadinessRules> | null = null

export function fetchReadinessRules(scope: Scope): Promise<ReadinessRules> {
  rulesPromise ??= http
    .get(`/${scope}/publish-readiness`)
    .then(({ data }) => (data.data ?? {}) as ReadinessRules)
    .catch((e) => {
      // Neúspech sa nezapamätá — ďalší formulár to smie skúsiť znova.
      rulesPromise = null
      throw e
    })

  return rulesPromise
}
