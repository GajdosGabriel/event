import http from './index'

/**
 * Prehľady naprieč podujatiami pre admina — všetky objednávky vstupeniek
 * a rezervácie (Admin\TicketController) a otázky z publika
 * (Admin\QuestionController). Len čítanie; spravuje sa v detaile podujatia.
 */
export interface OverviewMeta {
  currentPage: number
  lastPage: number
  total: number
}

export interface OverviewEvent {
  id: number
  name: string
  startAt?: string | null
}

export interface AdminTicketRow {
  id: number
  createdAt: string | null
  holderName: string
  holderEmail: string
  holderPhone: string | null
  userId: number | null
  status: 'reserved' | 'confirmed' | 'cancelled'
  statusLabel: string
  paymentStatus: 'none' | 'pending' | 'paid' | 'failed' | 'refunded'
  paymentStatusLabel: string
  priceAmount: number | null
  priceCurrency: string | null
  admissionsTotal: number
  checkedInCount: number
  event: OverviewEvent | null
}

export interface AdminTicketPage {
  data: AdminTicketRow[]
  meta: OverviewMeta
  summary: { total: number; day: number; week: number; paid: number; cancelled: number }
}

export interface AdminTicketParams {
  search?: string
  status?: string
  payment?: string
  price?: 'free' | 'paid' | ''
  date_from?: string
  date_to?: string
  page?: number
}

export interface AdminQuestionRow {
  id: number
  createdAt: string | null
  body: string
  authorName: string | null
  userId: number | null
  status: 'pending' | 'published' | 'hidden'
  statusLabel: string
  visibility: 'public' | 'private'
  visibilityLabel: string
  upvotesCount: number
  answerBody: string | null
  answeredAt: string | null
  wantsEmail: boolean
  event: OverviewEvent | null
  workshop: string | null
}

export interface AdminQuestionPage {
  data: AdminQuestionRow[]
  meta: OverviewMeta
  summary: { total: number; day: number; week: number; pending: number; unanswered: number }
}

export interface AdminQuestionParams {
  search?: string
  status?: string
  visibility?: string
  answered?: 'yes' | 'no' | ''
  date_from?: string
  date_to?: string
  page?: number
}

// Prázdne filtre sa neposielajú — backend by ich validoval ako prázdny reťazec.
function clean(params: object): Record<string, unknown> {
  return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== undefined && value !== ''))
}

export async function fetchAdminTickets(params: AdminTicketParams = {}): Promise<AdminTicketPage> {
  const { data } = await http.get('/admin/tickets', { params: clean(params) })
  return data as AdminTicketPage
}

export async function fetchAdminQuestions(params: AdminQuestionParams = {}): Promise<AdminQuestionPage> {
  const { data } = await http.get('/admin/questions', { params: clean(params) })
  return data as AdminQuestionPage
}
