import http from './index'
import type { SystemLogEntry } from '@/api/systemLogs'

export type InsightsKind = 'canal' | 'venue'

/** Udalosť z časovej osi: denník, volanie AI alebo doplnenie údajov. */
export type InsightsEntry = {
  id: string
  createdAt: string
  level: SystemLogEntry['level']
} & (
  | { type: 'log'; event: string; status: string | null; message: string }
  | { type: 'ai'; feature: string; message: string | null; model: string; costUsd: number }
  | { type: 'profile'; fields: string[] }
  | { type: 'coordinates'; source: string | null }
)

export interface RecordInsights {
  facts: {
    createdAt: string | null
    updatedAt: string | null
    publishedAt: string | null
    viewsTotal: number
    viewsMonth: number
    eventsTotal: number
    eventsUpcoming: number
    firstEventAt: string | null
    lastEventAt: string | null
    filesCount: number
    /** Odberatelia — len kanál. */
    followers: number | null
    /** Kanály na mieste — len miesto. */
    canalsCount: number | null
    contentReview: { score: number | null; reviewedAt: string } | null
    ai: { calls: number; failed: number; costUsd: number }
  }
  timeline: InsightsEntry[]
}

/** Len administrácia (Admin\RecordInsightsController). */
export async function getRecordInsights(kind: InsightsKind, id: number): Promise<RecordInsights> {
  const { data } = await http.get(`/admin/${kind}s/${id}/insights`)
  return data.data as RecordInsights
}
