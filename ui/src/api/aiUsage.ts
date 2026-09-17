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
}

export async function fetchAiUsage(days: number): Promise<AiUsageOverview> {
  const { data } = await http.get('/admin/ai-usage', { params: { days } })
  return data.data as AiUsageOverview
}
