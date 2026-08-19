import http, { BASE_URL } from './index'

export type QuestionTargetType = 'event' | 'workshop'
export type QuestionStatus = 'pending' | 'published' | 'hidden'

export interface QuestionItem {
  id: number
  body: string
  authorName: string | null
  upvotesCount: number
  answerBody: string | null
  answeredAt: string | null
  highlighted: boolean
  createdAt: string
  /** Len v moderačnom zozname — verejná odpoveď stav neposiela. */
  status: QuestionStatus | null
  statusLabel: string | null
}

/** Verejná nástenka spoza tokenu z QR kódu. */
export interface QuestionBoardView {
  code: string
  title: string
  eventName: string | null
  eventUrl: string | null
  startsAt: string | null
  endsAt: string | null
  venueName: string | null
  municipalityName: string | null
  organizerName: string | null
  intro: string | null
  open: boolean
  moderation: boolean
  showQuestions: boolean
  allowUpvotes: boolean
  askForName: boolean
  questionsCount: number
  /** Podpísaná známka, ktorú si odosielanie otázky vypýta späť. */
  ticket: string
  /** Otisk stavu pre polling — nezmenený znamená „nič nové". */
  v: string | null
  questions: QuestionItem[]
}

/** Nástenka v dashboarde — na rozdiel od verejnej nesie token a odkazy. */
export interface QuestionBoardAdmin {
  id: number
  targetType: QuestionTargetType | null
  targetId: number
  title: string
  token: string
  code: string
  publicUrl: string
  wallUrl: string
  isOpen: boolean
  acceptsQuestions: boolean
  moderation: boolean
  showQuestions: boolean
  allowUpvotes: boolean
  askForName: boolean
  intro: string | null
  opensAt: string | null
  closesAt: string | null
  questionsCount: number
  pendingCount: number
}

/** Miesto, kde nástenka môže byť — podujatie alebo jeden jeho workshop. */
export interface QuestionBoardSlot {
  targetType: QuestionTargetType
  targetId: number
  title: string
  board: QuestionBoardAdmin | null
}

export interface QuestionCounts {
  pending: number
  published: number
  hidden: number
}

function mapQuestion(raw: Record<string, unknown>): QuestionItem {
  return {
    id: raw['id'] as number,
    body: (raw['body'] as string) ?? '',
    authorName: (raw['author_name'] as string) ?? null,
    upvotesCount: Number(raw['upvotes_count'] ?? 0),
    answerBody: (raw['answer_body'] as string) ?? null,
    answeredAt: (raw['answered_at'] as string) ?? null,
    highlighted: Boolean(raw['highlighted']),
    createdAt: raw['created_at'] as string,
    status: (raw['status'] as QuestionStatus) ?? null,
    statusLabel: (raw['status_label'] as string) ?? null,
  }
}

function mapBoardView(raw: Record<string, unknown>): QuestionBoardView {
  const questions = (raw['questions'] as Record<string, unknown>[] | undefined) ?? []

  return {
    code: (raw['code'] as string) ?? '',
    title: (raw['title'] as string) ?? '',
    eventName: (raw['event_name'] as string) ?? null,
    eventUrl: (raw['event_url'] as string) ?? null,
    startsAt: (raw['starts_at'] as string) ?? null,
    endsAt: (raw['ends_at'] as string) ?? null,
    venueName: (raw['venue_name'] as string) ?? null,
    municipalityName: (raw['municipality_name'] as string) ?? null,
    organizerName: (raw['organizer_name'] as string) ?? null,
    intro: (raw['intro'] as string) ?? null,
    open: Boolean(raw['open']),
    moderation: Boolean(raw['moderation']),
    showQuestions: Boolean(raw['show_questions']),
    allowUpvotes: Boolean(raw['allow_upvotes']),
    askForName: Boolean(raw['ask_for_name']),
    questionsCount: Number(raw['questions_count'] ?? 0),
    ticket: (raw['ticket'] as string) ?? '',
    v: (raw['v'] as string) ?? null,
    questions: questions.map(mapQuestion),
  }
}

function mapBoardAdmin(raw: Record<string, unknown>): QuestionBoardAdmin {
  return {
    id: raw['id'] as number,
    targetType: (raw['target_type'] as QuestionTargetType) ?? null,
    targetId: Number(raw['target_id'] ?? 0),
    title: (raw['title'] as string) ?? '',
    token: (raw['token'] as string) ?? '',
    code: (raw['code'] as string) ?? '',
    publicUrl: (raw['public_url'] as string) ?? '',
    wallUrl: (raw['wall_url'] as string) ?? '',
    isOpen: Boolean(raw['is_open']),
    acceptsQuestions: Boolean(raw['accepts_questions']),
    moderation: Boolean(raw['moderation']),
    showQuestions: Boolean(raw['show_questions']),
    allowUpvotes: Boolean(raw['allow_upvotes']),
    askForName: Boolean(raw['ask_for_name']),
    intro: (raw['intro'] as string) ?? null,
    opensAt: (raw['opens_at'] as string) ?? null,
    closesAt: (raw['closes_at'] as string) ?? null,
    questionsCount: Number(raw['questions_count'] ?? 0),
    pendingCount: Number(raw['pending_count'] ?? 0),
  }
}

/* ---------------------------------------------------------------- verejné */

export async function showQuestionBoard(token: string): Promise<QuestionBoardView> {
  const { data } = await http.get(`/q/${token}`)
  return mapBoardView((data.data ?? data) as Record<string, unknown>)
}

export interface QuestionStreamResult {
  changed: boolean
  v?: string
  questionsCount?: number
  questions?: QuestionItem[]
}

/**
 * Prírastok pre polling. Keď sa od posledného volania nič nezmenilo, server
 * pošle len `changed: false` a zoznam vôbec neserializuje.
 */
export async function streamQuestions(token: string, version: string | null): Promise<QuestionStreamResult> {
  const { data } = await http.get(`/q/${token}/stream`, { params: { v: version ?? '' } })

  if (!data.changed) return { changed: false }

  return {
    changed: true,
    v: data.v as string,
    questionsCount: Number(data.questions_count ?? 0),
    questions: ((data.questions as Record<string, unknown>[]) ?? []).map(mapQuestion),
  }
}

export interface AskPayload {
  body: string
  author_name?: string | null
  ticket: string
  /** Honeypot — musí zostať prázdny, vypĺňa ho len automat. */
  website?: string
}

export interface AskResult {
  id: number
  pending: boolean
  question: QuestionItem | null
}

export async function askQuestion(token: string, payload: AskPayload): Promise<AskResult> {
  const { data } = await http.post(`/q/${token}/questions`, payload)

  return {
    id: data.id as number,
    pending: Boolean(data.pending),
    question: data.question ? mapQuestion(data.question as Record<string, unknown>) : null,
  }
}

export async function voteQuestion(token: string, id: number, voterToken: string): Promise<number> {
  const { data } = await http.post(`/q/${token}/questions/${id}/vote`, { voter_token: voterToken })
  return Number(data.upvotes_count ?? 0)
}

export async function unvoteQuestion(token: string, id: number, voterToken: string): Promise<number> {
  const { data } = await http.delete(`/q/${token}/questions/${id}/vote`, { data: { voter_token: voterToken } })
  return Number(data.upvotes_count ?? 0)
}

/* -------------------------------------------------------------- dashboard */

export async function indexQuestionBoards(eventId: number): Promise<QuestionBoardSlot[]> {
  const { data } = await http.get(`/dashboard/events/${eventId}/question-boards`)

  return ((data.data ?? []) as Record<string, unknown>[]).map((slot) => ({
    targetType: slot['target_type'] as QuestionTargetType,
    targetId: Number(slot['target_id'] ?? 0),
    title: (slot['title'] as string) ?? '',
    board: slot['board'] ? mapBoardAdmin(slot['board'] as Record<string, unknown>) : null,
  }))
}

export async function createQuestionBoard(
  eventId: number,
  targetType: QuestionTargetType,
  targetId: number,
): Promise<QuestionBoardAdmin> {
  const { data } = await http.post(`/dashboard/events/${eventId}/question-boards`, {
    target_type: targetType,
    target_id: targetId,
  })
  return mapBoardAdmin((data.data ?? data) as Record<string, unknown>)
}

export interface BoardSettingsPayload {
  is_open?: boolean
  moderation?: boolean
  show_questions?: boolean
  allow_upvotes?: boolean
  ask_for_name?: boolean
  intro?: string | null
  opens_at?: string | null
  closes_at?: string | null
}

export async function updateQuestionBoard(boardId: number, payload: BoardSettingsPayload): Promise<QuestionBoardAdmin> {
  const { data } = await http.put(`/dashboard/question-boards/${boardId}`, payload)
  return mapBoardAdmin((data.data ?? data) as Record<string, unknown>)
}

export async function rotateQuestionBoardToken(boardId: number): Promise<QuestionBoardAdmin> {
  const { data } = await http.post(`/dashboard/question-boards/${boardId}/rotate-token`)
  return mapBoardAdmin((data.data ?? data) as Record<string, unknown>)
}

export async function indexBoardQuestions(
  boardId: number,
  status?: QuestionStatus | null,
): Promise<{ questions: QuestionItem[]; counts: QuestionCounts }> {
  const { data } = await http.get(`/dashboard/question-boards/${boardId}/questions`, {
    params: status ? { status } : {},
  })

  return {
    questions: ((data.data ?? []) as Record<string, unknown>[]).map(mapQuestion),
    counts: (data.counts as QuestionCounts) ?? { pending: 0, published: 0, hidden: 0 },
  }
}

export interface ModerateQuestionPayload {
  status?: QuestionStatus
  highlighted?: boolean
  answered?: boolean
  answer_body?: string | null
}

export async function moderateQuestion(id: number, payload: ModerateQuestionPayload): Promise<QuestionItem> {
  const { data } = await http.patch(`/dashboard/questions/${id}`, payload)
  return mapQuestion((data.data ?? data) as Record<string, unknown>)
}

export async function deleteQuestion(id: number): Promise<void> {
  await http.delete(`/dashboard/questions/${id}`)
}

/**
 * Adresy generovanej snímky. Skladajú sa tu, nie na backende — sú to obyčajné
 * odkazy pre `<img>` a `<a download>`, ktoré idú mimo axiosu, a relatívna cesta
 * nad `/api` funguje v dev proxy aj na produkcii rovnako (rovnaký vzor ako
 * `admissionQrImageUrl` pri vstupenkách).
 */
export function slidePngUrl(token: string, params: Record<string, string>): string {
  return `${BASE_URL}/q/${token}/slide.png?${new URLSearchParams(params).toString()}`
}

export function slidePptxUrl(token: string, params: Record<string, string>): string {
  return `${BASE_URL}/q/${token}/slide.pptx?${new URLSearchParams(params).toString()}`
}

/** Samotný QR kód — do rohu premietacej steny, kde je na snímku málo miesta. */
export function boardQrUrl(token: string, size = 480): string {
  return `${BASE_URL}/q/${token}/qr.png?size=${size}`
}

/**
 * Otázky a odpovede na verejnom detaile podujatia.
 *
 * Tá istá nástenka ako `/q/{token}`, ale hľadá sa cez podujatie: token je
 * autorizácia, dá sa rotovať a nemá sa šíriť mimo QR kódu, takže ho verejný
 * detail nikdy nedostane.
 */

/** V akej fáze je nástenka voči termínu podujatia (odvodzuje ju backend). */
export type QuestionPhase = 'before' | 'live' | 'after'

export interface EventQuestionsView {
  /** false = podujatie nástenku nemá; sekcia sa vôbec nevykreslí. */
  available: boolean
  phase: QuestionPhase
  open: boolean
  moderation: boolean
  showQuestions: boolean
  allowUpvotes: boolean
  askForName: boolean
  intro: string | null
  questionsCount: number
  /** Koľko z nich má odpoveď — podľa toho sa rozhoduje, či sekcia stojí za zobrazenie. */
  answeredCount: number
  ticket: string
  questions: QuestionItem[]
}

export async function showEventQuestions(eventId: number): Promise<EventQuestionsView> {
  const { data } = await http.get(`/events/${eventId}/questions`)
  const raw = data as Record<string, unknown>
  const questions = (raw['questions'] as Record<string, unknown>[] | undefined) ?? []

  return {
    available: Boolean(raw['available']),
    phase: (raw['phase'] as QuestionPhase) ?? 'before',
    open: Boolean(raw['open']),
    moderation: Boolean(raw['moderation']),
    showQuestions: Boolean(raw['show_questions']),
    allowUpvotes: Boolean(raw['allow_upvotes']),
    askForName: Boolean(raw['ask_for_name']),
    intro: (raw['intro'] as string) ?? null,
    questionsCount: Number(raw['questions_count'] ?? 0),
    answeredCount: Number(raw['answered_count'] ?? 0),
    ticket: (raw['ticket'] as string) ?? '',
    questions: questions.map(mapQuestion),
  }
}

export async function askEventQuestion(eventId: number, payload: AskPayload): Promise<AskResult> {
  const { data } = await http.post(`/events/${eventId}/questions`, payload)

  return {
    id: data.id as number,
    pending: Boolean(data.pending),
    question: data.question ? mapQuestion(data.question as Record<string, unknown>) : null,
  }
}
