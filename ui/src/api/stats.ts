import http from './index'
import type {
  ModelStatus,
  StatsAttentionItem,
  StatsMetric,
  StatsOverview,
  StatsPeriod,
  StatsPeriodKey,
  StatsScope,
  StatsTrendDay,
} from '@/types'

const PERIOD_ORDER: StatsPeriodKey[] = ['day', 'week', 'month', 'all']

function num(value: unknown): number {
  return typeof value === 'number' ? value : Number(value ?? 0)
}

function nullableNum(value: unknown): number | null {
  return value === null || value === undefined ? null : num(value)
}

function mapMetric(raw: Record<string, unknown>): StatsMetric {
  return {
    label: (raw['label'] as string) ?? '',
    format: (raw['format'] as StatsMetric['format']) ?? 'number',
    value: num(raw['value']),
    previous: nullableNum(raw['previous']),
    change: nullableNum(raw['change']),
  }
}

function mapPeriods(raw: Record<string, unknown> | undefined): StatsPeriod[] {
  if (!raw) return []

  return PERIOD_ORDER.filter(key => raw[key]).map((key) => {
    const period = raw[key] as Record<string, unknown>
    const metrics = (period['metrics'] as Record<string, Record<string, unknown>>) ?? {}

    return {
      key,
      label: (period['label'] as string) ?? '',
      from: (period['from'] as string) ?? null,
      to: (period['to'] as string) ?? null,
      metrics: Object.fromEntries(
        Object.entries(metrics).map(([metricKey, metric]) => [metricKey, mapMetric(metric)]),
      ),
    }
  })
}

export async function fetchOverviewStats(scope: StatsScope): Promise<StatsOverview> {
  const { data } = await http.get(scope === 'admin' ? '/admin' : '/dashboard')
  const raw = (data.data ?? data) as Record<string, unknown>

  const totals = (raw['totals'] as Record<string, Record<string, unknown>>) ?? {}
  const events = totals['events'] ?? {}
  const ticketing = (raw['ticketing'] as Record<string, Record<string, unknown>>) ?? {}
  const orders = ticketing['orders'] ?? {}
  const seats = ticketing['seats'] ?? {}
  const capacity = ticketing['capacity'] ?? {}
  const attendance = ticketing['attendance'] ?? {}
  const sources = (raw['sources'] as Record<string, unknown>) ?? {}
  const users = raw['users'] as Record<string, unknown> | null

  return {
    scope: (raw['scope'] as StatsScope) ?? scope,
    generatedAt: (raw['generated_at'] as string) ?? '',
    trendDays: num(raw['trend_days']) || 30,
    periods: mapPeriods(raw['periods'] as Record<string, unknown>),
    totals: {
      events: {
        total: num(events['total']),
        published: num(events['published']),
        draft: num(events['draft']),
        archived: num(events['archived']),
        active: num(events['active']),
        running: num(events['running']),
        today: num(events['today']),
        next7d: num(events['next_7d']),
        withTicketing: num(events['with_ticketing']),
      },
      venues: { total: num((totals['venues'] ?? {})['total']) },
      canals: { total: num((totals['canals'] ?? {})['total']) },
    },
    trend: ((raw['trend'] as Record<string, unknown>[]) ?? []).map((day): StatsTrendDay => ({
      date: day['date'] as string,
      events: num(day['events']),
      tickets: num(day['tickets']),
      admissions: num(day['admissions']),
      checkins: num(day['checkins']),
    })),
    ticketing: {
      orders: {
        total: num(orders['total']),
        paid: num(orders['paid']),
        awaitingPayment: num(orders['awaiting_payment']),
        revenuePaid: num(orders['revenue_paid']),
        revenueAwaiting: num(orders['revenue_awaiting']),
      },
      seats: {
        total: num(seats['total']),
        valid: num(seats['valid']),
        cancelled: num(seats['cancelled']),
        waitlisted: num(seats['waitlisted']),
        awaitingConfirmation: num(seats['awaiting_confirmation']),
      },
      capacity: {
        seats: num(capacity['seats']),
        sold: num(capacity['sold']),
        limitedTypes: num(capacity['limited_types']),
        unlimitedTypes: num(capacity['unlimited_types']),
        rate: nullableNum(capacity['rate']),
      },
      attendance: {
        expected: num(attendance['expected']),
        arrived: num(attendance['arrived']),
        rate: nullableNum(attendance['rate']),
      },
    },
    statuses: ((raw['statuses'] as Record<string, unknown>[]) ?? []).map(row => ({
      key: row['key'] as ModelStatus,
      label: row['label'] as string,
      count: num(row['count']),
    })),
    sources: {
      own: num(sources['own']),
      imported: num(sources['imported']),
      importedRate: nullableNum(sources['imported_rate']),
    },
    attention: ((raw['attention'] as Record<string, unknown>[]) ?? []).map((item): StatsAttentionItem => ({
      key: item['key'] as string,
      severity: item['severity'] as StatsAttentionItem['severity'],
      label: item['label'] as string,
      hint: (item['hint'] as string) ?? '',
      count: num(item['count']),
      link: (item['link'] as string) ?? null,
    })),
    topEvents: ((raw['top_events'] as Record<string, unknown>[]) ?? []).map(row => ({
      id: num(row['id']),
      name: row['name'] as string,
      startAt: (row['start_at'] as string) ?? null,
      status: (row['status'] as ModelStatus) ?? null,
      seats: num(row['seats']),
      capacity: nullableNum(row['capacity']),
      rate: nullableNum(row['rate']),
    })),
    upcoming: ((raw['upcoming'] as Record<string, unknown>[]) ?? []).map(row => ({
      id: num(row['id']),
      name: row['name'] as string,
      startAt: (row['start_at'] as string) ?? null,
      endAt: (row['end_at'] as string) ?? null,
      status: (row['status'] as ModelStatus) ?? null,
      venue: (row['venue'] as string) ?? null,
      seats: num(row['seats']),
    })),
    topCanals: ((raw['top_canals'] as Record<string, unknown>[]) ?? []).map(row => ({
      id: num(row['id']),
      name: row['name'] as string,
      eventsTotal: num(row['events_total']),
      eventsRecent: num(row['events_recent']),
    })),
    users: users
      ? {
          total: num(users['total']),
          verified: num(users['verified']),
          blocked: num(users['blocked']),
          active30d: num(users['active_30d']),
        }
      : null,
  }
}
