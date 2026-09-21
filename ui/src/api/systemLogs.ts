import http from './index'

/**
 * Denník udalostí (Admin\SystemLogController) — čo komu odišlo, čo zlyhalo,
 * prihlásenia, importy, cron. Len čítanie; staré záznamy maže backend sám.
 */
export type SystemLogLevel = 'info' | 'warning' | 'error'
export type SystemLogStatus = 'sent' | 'failed' | 'skipped' | 'ok'

export interface SystemLogEntry {
  id: number
  createdAt: string | null
  level: SystemLogLevel
  channel: string
  event: string
  status: SystemLogStatus | null
  message: string
  recipient: string | null
  user: { id: number; email: string | null } | null
  subjectType: string | null
  subjectId: number | null
  ip: string | null
  context: Record<string, unknown> | null
}

export interface SystemLogSummary {
  sentDay: number
  sentWeek: number
  failedDay: number
  failedWeek: number
  errorsDay: number
  errorsWeek: number
  loginsDay: number
  authFailedDay: number
}

export interface SystemLogPage {
  data: SystemLogEntry[]
  meta: { currentPage: number; lastPage: number; total: number }
  summary: SystemLogSummary
  channels: string[]
  retention: { days: number; errorDays: number }
}

export interface SystemLogParams {
  channel?: string
  level?: SystemLogLevel
  status?: SystemLogStatus
  search?: string
  recipient?: string
  user_id?: number
  date_from?: string
  date_to?: string
  page?: number
}

export async function fetchSystemLogs(params: SystemLogParams = {}): Promise<SystemLogPage> {
  // Prázdne filtre sa neposielajú — backend by ich validoval ako prázdny reťazec.
  const clean = Object.fromEntries(Object.entries(params).filter(([, value]) => value !== undefined && value !== ''))
  const { data } = await http.get('/admin/system-logs', { params: clean })
  return data as SystemLogPage
}
