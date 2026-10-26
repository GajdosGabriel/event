import http from './index'
import type {
  AnswerMap,
  AnswerValue,
  QuestionItem,
  QuestionOptionItem,
  QuestionSummaryItem,
  TicketAnswerItem,
  TicketTypeItem,
} from '@/types'

/*
 * Dotazník k registrácii (strava, ubytovanie, alergie…). Na backende ho
 * stráži App\Services\Tickets\Questionnaire — tu je len mapovanie a pomôcky
 * pre formulár; o povinnosti, kapacite a cene rozhoduje vždy server.
 */

function mapOption(raw: Record<string, unknown>): QuestionOptionItem {
  return {
    id: (raw['id'] as number) ?? undefined,
    label: raw['label'] as string,
    priceAmount: (raw['price_amount'] as number) ?? null,
    capacity: (raw['capacity'] as number) ?? null,
    takenCount: (raw['taken_count'] as number) ?? null,
    remainingCapacity: (raw['remaining_capacity'] as number) ?? null,
    isActive: raw['is_active'] === undefined ? true : Boolean(raw['is_active']),
  }
}

export function mapQuestion(raw: Record<string, unknown>): QuestionItem {
  const options = (raw['options'] as Record<string, unknown>[] | undefined) ?? []

  return {
    id: (raw['id'] as number) ?? undefined,
    eventId: (raw['event_id'] as number) ?? undefined,
    scope: (raw['scope'] as QuestionItem['scope']) ?? 'attendee',
    type: raw['type'] as QuestionItem['type'],
    label: raw['label'] as string,
    help: (raw['help'] as string) ?? null,
    isRequired: Boolean(raw['is_required']),
    ticketTypeIds: (raw['ticket_type_ids'] as number[] | null) ?? null,
    editableByAttendee: raw['editable_by_attendee'] === undefined ? true : Boolean(raw['editable_by_attendee']),
    isActive: raw['is_active'] === undefined ? true : Boolean(raw['is_active']),
    sortOrder: (raw['sort_order'] as number) ?? 0,
    options: options.map(mapOption),
  }
}

export function mapAnswers(raw: unknown): TicketAnswerItem[] {
  return ((raw as Record<string, unknown>[]) ?? []).map(row => ({
    questionId: row['question_id'] as number,
    label: (row['label'] as string) ?? '',
    value: (row['value'] as string) ?? null,
    optionIds: (row['option_ids'] as number[]) ?? [],
    display: (row['display'] as string) ?? '',
    priceAmount: (row['price_amount'] as number) ?? 0,
  }))
}

// ── Pomôcky pre formulár ────────────────────────────────────────────────

export function hasOptions(question: QuestionItem): boolean {
  return question.type === 'single_choice' || question.type === 'multi_choice'
}

/**
 * Pýta sa otázka pri mieste daného typu? Rovnako ako EventQuestion::appliesToType:
 * bez výberu typov len pri hlavných vstupenkách, workshop je doplnok.
 */
export function appliesToType(question: QuestionItem, type: Pick<TicketTypeItem, 'id' | 'kind'>): boolean {
  if (question.scope !== 'attendee') return false
  if (!question.ticketTypeIds?.length) return type.kind !== 'workshop'
  return question.ticketTypeIds.includes(Number(type.id))
}

export function orderQuestions(questions: QuestionItem[]): QuestionItem[] {
  return questions.filter(q => q.scope === 'order')
}

export function emptyValue(question: QuestionItem): AnswerValue {
  if (isFixedChoice(question)) return activeOptions(question)[0].id ?? null
  if (question.type === 'multi_choice') return []
  if (question.type === 'checkbox') return false
  return null
}

/** Prázdne odpovede pre dané otázky (nový formulár). */
export function emptyAnswers(questions: QuestionItem[]): AnswerMap {
  return Object.fromEntries(questions.map(q => [q.id!, emptyValue(q)]))
}

/** Uložené odpovede → hodnoty formulára (úprava). */
export function answersToMap(questions: QuestionItem[], answers: TicketAnswerItem[] | undefined): AnswerMap {
  const map = emptyAnswers(questions)

  for (const answer of answers ?? []) {
    const question = questions.find(q => q.id === answer.questionId)
    if (!question) continue

    if (question.type === 'multi_choice') map[answer.questionId] = [...answer.optionIds]
    else if (question.type === 'single_choice') map[answer.questionId] = answer.optionIds[0] ?? null
    else if (question.type === 'checkbox') map[answer.questionId] = answer.value === '1'
    else map[answer.questionId] = answer.value
  }

  return map
}

function activeOptions(question: QuestionItem): QuestionOptionItem[] {
  return question.options.filter(o => o.isActive)
}

/**
 * Najnižšia suma, ktorej sa pri otázke nedá vyhnúť: povinný výber jednej
 * možnosti bez bezplatnej voľby (napr. „Účastnícky poplatok 10 €").
 */
function unavoidablePrice(question: QuestionItem): number {
  if (!question.isRequired || question.type !== 'single_choice') return 0
  const prices = activeOptions(question).map(o => o.priceAmount ?? 0)
  return prices.length ? Math.min(...prices) : 0
}

/** Povinné poplatky z dotazníka (centy) — patria do ceny, nie medzi „príplatky". */
export function requiredFee(questions: QuestionItem[]): number {
  return questions.reduce((sum, q) => sum + unavoidablePrice(q), 0)
}

/** Ponúka dotazník aj voliteľné platené možnosti (nad rámec povinného poplatku)? */
export function hasOptionalExtras(questions: QuestionItem[]): boolean {
  return questions.some(q => hasOptions(q) && activeOptions(q).some(o => (o.priceAmount ?? 0) > unavoidablePrice(q)))
}

/** Povinná otázka s jedinou možnosťou (poplatok) — nie je čo vyberať. */
export function isFixedChoice(question: QuestionItem): boolean {
  return question.type === 'single_choice' && question.isRequired && activeOptions(question).length === 1
}

/** Príplatok za zvolené možnosti (centy) — len na zobrazenie, cenu počíta server. */
export function answersPrice(questions: QuestionItem[], answers: AnswerMap | undefined): number {
  if (!answers) return 0

  return questions.filter(hasOptions).reduce((sum, question) => {
    const value = answers[question.id!]
    const ids = Array.isArray(value) ? value : value === null || value === undefined || value === '' ? [] : [Number(value)]
    return sum + ids.reduce((s, id) => s + (question.options.find(o => o.id === id)?.priceAmount ?? 0), 0)
  }, 0)
}

/**
 * Chyby zo servera (`items.0.attendees.1.answers.12`, `answers.12`,
 * `admissions.5.12`) → mapa podľa prefixu a id otázky.
 */
export function answerErrors(errors: Record<string, string[]> | undefined, prefix: string): Record<number, string> {
  const result: Record<number, string> = {}
  if (!errors) return result

  for (const [key, messages] of Object.entries(errors)) {
    if (!key.startsWith(prefix + '.')) continue
    const id = Number(key.slice(prefix.length + 1))
    if (Number.isFinite(id) && messages?.length) result[id] = messages[0]
  }

  return result
}

// ── API ─────────────────────────────────────────────────────────────────

/** Aktívne otázky pre registračný formulár (web aj embed). */
export async function publicQuestionnaire(eventId: number): Promise<QuestionItem[]> {
  const { data } = await http.get(`/events/${eventId}/questionnaire`)
  return ((data.data ?? data) as Record<string, unknown>[]).map(mapQuestion)
}

export interface QuestionPayload {
  label: string
  help?: string | null
  scope: QuestionItem['scope']
  type: QuestionItem['type']
  is_required?: boolean
  ticket_type_ids?: number[] | null
  editable_by_attendee?: boolean
  is_active?: boolean
  options?: { id?: number; label: string; price_amount?: number | null; capacity?: number | null; is_active?: boolean }[]
}

export async function indexQuestions(eventId: number): Promise<QuestionItem[]> {
  const { data } = await http.get(`/dashboard/events/${eventId}/questionnaire`)
  return ((data.data ?? data) as Record<string, unknown>[]).map(mapQuestion)
}

export async function createQuestion(eventId: number, payload: QuestionPayload): Promise<QuestionItem> {
  const { data } = await http.post(`/dashboard/events/${eventId}/questionnaire`, payload)
  return mapQuestion((data.data ?? data) as Record<string, unknown>)
}

export async function updateQuestion(eventId: number, questionId: number, payload: Partial<QuestionPayload>): Promise<QuestionItem> {
  const { data } = await http.put(`/dashboard/events/${eventId}/questionnaire/${questionId}`, payload)
  return mapQuestion((data.data ?? data) as Record<string, unknown>)
}

export async function deleteQuestion(eventId: number, questionId: number): Promise<void> {
  await http.delete(`/dashboard/events/${eventId}/questionnaire/${questionId}`)
}

export async function reorderQuestions(eventId: number, ids: number[]): Promise<QuestionItem[]> {
  const { data } = await http.put(`/dashboard/events/${eventId}/questionnaire/order`, { ids })
  return ((data.data ?? data) as Record<string, unknown>[]).map(mapQuestion)
}

export async function questionnaireSummary(eventId: number): Promise<QuestionSummaryItem[]> {
  const { data } = await http.get(`/dashboard/events/${eventId}/questionnaire/summary`)
  return ((data.data ?? []) as Record<string, unknown>[]).map(row => ({
    id: row['id'] as number,
    label: row['label'] as string,
    type: row['type'] as QuestionSummaryItem['type'],
    scope: row['scope'] as QuestionSummaryItem['scope'],
    isActive: Boolean(row['is_active']),
    answeredCount: (row['answered_count'] as number) ?? null,
    options: ((row['options'] as Record<string, unknown>[]) ?? []).map(o => ({
      id: o['id'] as number,
      label: o['label'] as string,
      count: (o['count'] as number) ?? 0,
      capacity: (o['capacity'] as number) ?? null,
      revenue: (o['revenue'] as number) ?? 0,
      isActive: Boolean(o['is_active']),
    })),
  }))
}
