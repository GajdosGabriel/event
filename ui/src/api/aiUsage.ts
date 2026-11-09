import http from './index'

/**
 * Prehľad spotreby OpenAI (Admin\AiUsageController). Len čítanie — AI
 * funkcie sa tu nevypínajú, stránka ukazuje, čo koľko tokenov berie.
 */
export interface AiUsageTotals {
  calls: number
  failed: number
  promptTokens: number
  completionTokens: number
  costUsd: number
}

export interface AiUsageGroup extends AiUsageTotals {
  key: string | number | null
  name?: string | null
}

export interface AiUsageDay extends AiUsageTotals {
  day: string
}

export interface AiUsageRecent {
  id: number
  createdAt: string | null
  feature: string
  source: string | null
  model: string
  promptTokens: number
  completionTokens: number
  costUsd: number
  success: boolean
  canal: { id: number; name: string } | null
  user: { id: number; name: string } | null
  subjectType: string | null
  subjectId: number | null
  subjectName: string | null
}

export interface AiUsageOverview {
  days: number
  totals: { period: AiUsageTotals; month: AiUsageTotals; all: AiUsageTotals }
  byFeature: AiUsageGroup[]
  bySource: AiUsageGroup[]
  byCanal: AiUsageGroup[]
  byUser: AiUsageGroup[]
  byDay: AiUsageDay[]
  recent: AiUsageRecent[]
  recentMeta: { currentPage: number; lastPage: number; total: number }
}

/** Limity AI vyhľadávania (Admin\AiLimitsController) — prepisujú config/.env. */
export interface AiLimitsInput {
  profileEnrichment: boolean
  emailDiscoveryAi: boolean
  monthlyLimitUsd: number
}

export interface AiLimits extends AiLimitsInput {
  apiKey: boolean
  /** Spoločný rozpočet; uvoľňuje sa po dňoch. limitUsd 0 = bez limitu (len z configu). */
  budget: { limitUsd: number; spentUsd: number; releasedUsd: number; available: boolean }
}

export async function fetchAiLimits(): Promise<AiLimits> {
  const { data } = await http.get('/admin/ai-usage/limits')
  return data.data as AiLimits
}

export async function updateAiLimits(input: AiLimitsInput): Promise<AiLimits> {
  const { data } = await http.put('/admin/ai-usage/limits', input)
  return data.data as AiLimits
}

export async function fetchAiUsage(days: number, page = 1): Promise<AiUsageOverview> {
  const { data } = await http.get('/admin/ai-usage', { params: { days, page } })
  return data.data as AiUsageOverview
}
