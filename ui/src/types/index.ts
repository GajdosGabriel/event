export type ModelStatus =
  | 'draft'
  | 'pending_review'
  | 'rejected'
  | 'scheduled'
  | 'published'
  | 'archived'
  | 'blocked'

export interface AllowedStatusOption {
  id: string
  name: string
}

/** Model, ktorého odkaz sa dá nahlásiť na prednostné overenie. */
export type LinkReportTarget = 'canal' | 'venue' | 'event' | 'organization'

/**
 * Údaj, ktorý pri overovaní neprešiel (`attribute_issues` v detaile modelu).
 *
 * Chodí len tomu, kto smie záznam upraviť, a len keď je naozaj pokazený —
 * „zatiaľ sme neoverili" sa nevracia vôbec, nie je čo zobraziť.
 */
export interface AttributeIssue {
  status: 'failed'
  /** Kľúč dôvodu (dns, not_found, timeout…) — popisky drží front. */
  reason: string | null
  httpStatus: number | null
  /** Koľkokrát po sebe overenie zlyhalo. */
  failures: number
  checkedAt: string | null
  /** Kedy sme o tom majiteľovi napísali; `null` = ešte nie. */
  notifiedAt: string | null
}

/** Pokazené údaje záznamu podľa názvu atribútu (dnes len `website`). */
export type AttributeIssues = Partial<Record<'website', AttributeIssue>>

/** Možnosť pre <select>, ktorej hodnoty aj popisky (cez lang) drží backend. */
export interface SelectOption {
  value: string
  label: string
}

/** Hodnota, ktorú vie niesť jedno pole formulára (FormField). */
export type FieldValue = string | number | boolean | null | undefined

/** Položka do `<select>` vo FormField — na rozdiel od SelectOption aj číselná. */
export interface FieldOption {
  value: FieldValue
  label: string
  /**
   * Vysvetlivka pod popiskou. Používa ju `type="radio"`, kde možnosť bez vety
   * „čo to znamená" núti človeka hádať; `<select>` ju ignoruje.
   */
  hint?: string
  /** Nedostupná voľba. Zostáva vidieť aj s dôvodom v `hint` — inak by človek nevedel, že existuje. */
  disabled?: boolean
}

export interface ModelPermissions {
  view: boolean
  update: boolean
  publish?: boolean
  unpublish?: boolean
  delete: boolean
  archive?: boolean
  unarchive?: boolean
  duplicate?: boolean
  restore: boolean
  viewTickets?: boolean
  checkin?: boolean
}

export interface CollectionPermissions {
  create: boolean
}

export interface UploadedFileItem {
  id?: number
  name: string
  url?: string
  previewUrl?: string
  type?: string
  disk?: string
  sizeBytes?: number
  isPrimary?: boolean
  mimeType?: string
}

export interface MunicipalityOverviewItem {
  municipalityId: number
  municipalityName: string
  municipalityShortname: string
  eventsCount: number
  thumbImage: string | null
  owner: string | null
  municipality: { id: number; name: string; shortname: string } | null
}

export interface FilterParams {
  published?: string | boolean
  unpublished?: string | boolean
  blocked?: string | boolean
  status?: string
  deleted?: string | boolean
  search?: string
  municipality?: string | number
  list?: 'upcoming' | 'ongoing' | 'all'
  per_page?: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface LookupOption {
  id: number
  name: string
  zip?: string
}

// Auth
export interface AuthCanalContextActive {
  id: number
  name: string
  slug?: string
}

export interface AuthCanalItem {
  id: number
  name: string
  slug: string
  status: string
  /** Rola používateľa v tomto kanáli — App\Enums\CanalRole. */
  role?: string
}

export interface AuthIdentity {
  id: number
  display_name: string
  /** Vlastný e-mail prihláseného návštevníka — cudzie e-maily API neposiela. */
  email?: string | null
  canal_id: number | null
  canal: string
  roles?: string[]
  canals?: AuthCanalItem[]
  canal_context?: {
    active: AuthCanalContextActive | null
    is_owner: boolean
    /** Rola v práve aktívnom kanáli; o právach rozhoduje backend. */
    role?: string | null
    role_label?: string | null
  } | null
  permissions?: Record<string, boolean>
  [key: string]: unknown
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  display_name: string
  email: string
  password: string
  password_confirmation?: string
  /** Súhlas s obchodnými podmienkami — bez neho API registráciu odmietne. */
  terms_accepted: boolean
}

/**
 * Cesty „Pridať do kalendára". Skladá ich API (App\Services\Calendar\IcsGenerator),
 * aby termín, miesto aj popis boli rovnaké na webe, v `.ics` súbore aj v e-maile.
 */
export interface CalendarLinks {
  /** Súbor `.ics` — Apple Kalendár, desktopový Outlook, Thunderbird. */
  download: string
  google: string
  outlook: string
}

// Event
/** Jeden ďalší termín série tak, ako ho vracia verejný detail. */
export interface SeriesOccurrence {
  id: number
  name: string
  slug: string | null
  startAt: string | null
  endAt: string | null
  dateRangeLabel: string | null
  url: string | null
}

export interface EventItem {
  id: number
  canalId: number | null
  canalName: string
  municipalityId: number | null
  venueId: number | null
  name: string
  slug: string
  body: string | null
  status: ModelStatus
  startAt: string | null
  endAt: string | null
  dateRangeLabel: string | null
  /** Séria opakovaných termínov; null pri jednorazovom podujatí. */
  seriesId: number | null
  /** Koľko ďalších termínov série ešte len bude — len vo verejnom výpise. */
  seriesUpcomingCount: number | null
  /** Ostatné nadchádzajúce termíny série — len na verejnom detaile. */
  seriesOccurrences: SeriesOccurrence[]
  registrationDeadlineAt: string | null
  ticketsEnabled: boolean
  workshopLockOnStart?: boolean
  /** Pripomienka účastníkom: hodiny pred začiatkom, null = neposielať. */
  reminderHoursBefore: number | null
  /** Kedy pripomienka odišla — posiela sa raz. */
  reminderSentAt: string | null
  priceAmount: number | null
  priceCurrency: string | null
  publishedAt: string | null
  /** Naplánované zverejnenie — čas, kedy event sám prejde do „Publikovaný". */
  publishAt: string | null
  deletedAt: string | null
  createdAt: string | null
  updatedAt: string | null
  website: string | null
  locationName: string | null
  street: string | null
  postcode: string | null
  country: string | null
  latitude: number | null
  longitude: number | null
  imageUrl: string | null
  /** Veľký variant (1280px) — spolu s `imageUrl` tvorí srcset na kartách. */
  imageUrlLarge: string | null
  uploadedFiles: UploadedFileItem[]
  permissions: ModelPermissions
  allowedStatuses: AllowedStatusOption[]
  ticketTypeKindOptions: SelectOption[]
  ticketTypeLabels: Record<string, string>
  phone: string | null
  email: string | null
  /** Údaje, ktoré pri overovaní neprešli; `null` = všetko v poriadku. */
  attributeIssues: AttributeIssues | null
  /** Má podujatie organizátora s e-mailom, ktorému možno poslať správu? */
  contactable: boolean
  /** Len na verejnom detaile; null pri podujatí bez termínu. */
  calendarLinks: CalendarLinks | null
  municipality: { id: number; name: string; fullname?: string } | null
  canal: { id: number; name: string; thumbImage?: string; website?: string | null } | null
  venue: {
    id: number
    name: string
    street: string | null
    postcode: string | null
    latitude: string | null
    longitude: string | null
    phone: string | null
    website: string | null
    openingHours: Record<string, string | null> | null
  } | null
  uploadedImages: { thumb: string; large: string; original: string }[]
  tags: TagItem[]
  /**
   * Počet zobrazení verejného detailu. null pre návštevníka — backend ho
   * do odpovede dáva len organizátorovi a adminovi.
   */
  viewsCount: number | null
}

/** Obsahový štítok podujatia. `group` je facet — viď TagGroup na backende. */
export interface TagItem {
  id: number
  slug: string
  name: string
  group: string
  emoji: string | null
  /** Kto štítok priradil: ručne človek, AI, alebo odvodenie z dát. */
  source?: 'manual' | 'ai' | 'derived' | 'import'
}

export interface TagGroupItem {
  group: string
  label: string
  tags: (TagItem & { eventsCount: number })[]
}

// Canal
export type CanalIdentityMode = 'personal' | 'organization' | 'pseudonymous'

export interface CanalItem {
  id: number
  municipalityId: number | null
  venueId: number | null
  identityMode: CanalIdentityMode
  /** Preložený popisok typu identity z API (lang), nie z frontu. */
  identityModeLabel: string | null
  name: string
  slug: string
  titlePrefix: string | null
  titleSuffix: string | null
  email: string | null
  /** Údaje, ktoré pri overovaní neprešli; `null` = všetko v poriadku. */
  attributeIssues: AttributeIssues | null
  phone: string | null
  body: string | null
  imageUrl: string | null
  publishedAt: string | null
  status: ModelStatus
  website: string | null
  street: string | null
  postcode: string | null
  country: string | null
  latitude: number | null
  longitude: number | null
  /** Odkiaľ sú súradnice: budova / adresa / odhad AI / stred obce / ručne. */
  coordinatesSource: CoordinatesSource | null
  deletedAt: string | null
  createdAt: string
  updatedAt: string
  uploadedFiles: UploadedFileItem[]
  permissions: ModelPermissions
  /** Prečo sa záznam nedá stiahnuť z výpisu — odkazuje naň podujatie. */
  unpublishBlockedReason: string | null
  allowedStatuses: AllowedStatusOption[]
  municipality: { id: number; name: string } | null
  /** Fakturačná identita kanála; `null` pri osobnom kanáli bez firmy. */
  organization: { id: number; name: string } | null
  venuesList: { id: number; name: string; isOwner: boolean }[]
  membersList: { id: number; name: string; isOwner: boolean }[]
  /** Má cieľ vlastníka s e-mailom? (riadi tlačidlo „Poslať správu") */
  contactable?: boolean
}

// Venue
/**
 * Presnosť GPS súradníc miesta. Detekcia ich dopĺňa rebríkom zdrojov, takže
 * značka na mape môže sedieť na budove aj len na strede obce — operátor musí
 * vedieť, ktorý prípad vidí.
 */
export type CoordinatesSource = 'venue' | 'address' | 'ai' | 'municipality' | 'manual'

/**
 * Adresa v editore — jeden tvar pre miesto aj kanál, aby ju vedel obslúžiť
 * jeden komponent (AddressFieldset + AddressMapField).
 *
 * Kľúč obce je zámerne `municipalityId`: tak sa volá číselník aj stĺpec v
 * kanáloch. Miesto ho má v DB ako `village_id` — premenuje sa až tesne pred
 * odoslaním, viď `toAddressPayload()`.
 */
export interface AddressModel {
  municipalityId: number | null
  street: string
  postcode: string
  country: string
  latitude: number | null
  longitude: number | null
  coordinatesSource: CoordinatesSource | null
}

export interface VenueItem {
  id: number
  canalId: number | null
  villageId: number | null
  name: string
  slug: string
  street: string | null
  postcode: string | null
  body: string | null
  website: string | null
  email: string | null
  /** Údaje, ktoré pri overovaní neprešli; `null` = všetko v poriadku. */
  attributeIssues: AttributeIssues | null
  phone: string | null
  country: string | null
  latitude: number | null
  longitude: number | null
  /** Odkiaľ sú súradnice: budova / adresa / odhad AI / stred obce / ručne. */
  coordinatesSource: CoordinatesSource | null
  capacity: number | null
  openingHours: unknown | null
  category: string | null
  imageUrl: string | null
  status: ModelStatus
  deletedAt: string | null
  createdAt: string
  updatedAt: string
  uploadedFiles: UploadedFileItem[]
  permissions: ModelPermissions
  /** Prečo sa záznam nedá stiahnuť z výpisu — odkazuje naň podujatie. */
  unpublishBlockedReason: string | null
  allowedStatuses: AllowedStatusOption[]
  municipality: { id: number; name: string } | null
  canalsList: { id: number; name: string; isOwner: boolean }[]
  /** Má cieľ vlastníka s e-mailom? (riadi tlačidlo „Poslať správu") */
  contactable?: boolean
}

// Municipality
export interface MunicipalityItem {
  id: number
  name: string
  shortname: string | null
  zip: string | null
  createdAt: string
  updatedAt: string
  deletedAt: string | null
}

// Organization
export interface OrganizationItem {
  id: number
  title: string
  /** Súkromná osoba namiesto firmy — nemá IČO ani zápis v registri. */
  person: boolean
  slug: string
  description: string | null
  website: string | null
  email: string | null
  /** Údaje, ktoré pri overovaní neprešli; `null` = všetko v poriadku. */
  attributeIssues: AttributeIssues | null
  phone: string | null
  villageId: number | null
  status: ModelStatus
  published: boolean
  /** Väzba na Account — centrálnu evidenciu firiem. */
  accountUuid: string | null
  accountSyncedAt: string | null
  /** Fakturačné údaje z Accountu. Vyplnené len v detaile, nie vo výpise. */
  account: OrganizationAccountData | null
  /** Počet kanálov, ktoré pod firmou fakturujú. Vo výpise aj v detaile. */
  canalsCount: number
  /** Kanály aj s tímom. Naplnené len v detaile — vo výpise je prázdne pole. */
  canals: OrganizationCanal[]
  deletedAt: string | null
  createdAt: string
  updatedAt: string
}

/**
 * Kanál, ktorý fakturuje pod organizáciou.
 *
 * Ľudia nevisia na firme priamo — členom sa je vždy v konkrétnom kanáli
 * a tam platí aj rola. Firma je len fakturačná strecha nad kanálmi.
 */
export interface OrganizationCanal {
  id: number
  name: string
  status: ModelStatus
  identityMode: string | null
  members: OrganizationCanalMember[]
}

export interface OrganizationCanalMember {
  id: number
  name: string
  /** owner | editor | checkin — platí len v tomto kanáli. */
  role: string | null
  isOwner: boolean
}

/** Odpoveď Accountu na `/api/v1/organizations/{uuid}` (len časti, ktoré Event zobrazuje). */
export interface OrganizationAccountData {
  id: string
  name: string
  legal_name: string | null
  legal_form: string | null
  legal_form_label: string | null
  status: string | null
  identifiers?: {
    ico: string | null
    dic: string | null
    ic_dph: string | null
    vat_mode: string | null
    is_vat_payer: boolean
    ico_verified_at: string | null
    vat_verified_at: string | null
  }
  registration?: {
    court: string | null
    section: string | null
    insert: string | null
    line: string | null
  }
  address?: {
    street: string | null
    city: string | null
    postal_code: string | null
    region: string | null
    country: string | null
    line: string | null
  }
  contact?: {
    email: string | null
    billing_email: string | null
    phone: string | null
    website: string | null
    /** Adresa, na ktorú doklady reálne odchádzajú, a či ju zákazník potvrdil. */
    billing_email_effective: string | null
    billing_email_verified: boolean
    billing_email_verified_at: string | null
  }
  bank?: {
    name: string | null
    iban: string | null
    swift: string | null
  }
  billing?: {
    currency: string | null
    /** Údaje, bez ktorých Account nevystaví faktúru. */
    missing: string[]
  }
}

/** Plochý tvar fakturačných údajov, s ktorým pracuje formulár. */
export interface OrganizationAccountForm {
  legal_name: string
  legal_form: string
  ico: string
  dic: string
  ic_dph: string
  vat_mode: string
  register_court: string
  register_section: string
  register_insert: string
  street: string
  city: string
  postal_code: string
  country: string
  email: string
  billing_email: string
  phone: string
  website: string
  bank_name: string
  iban: string
  swift: string
}

/**
 * Výsledok vyhľadania IČO v registri (RPO/ARES) cez Account.
 * Register nie je zmluvne garantovaný, preto je `found: false` bežný stav,
 * nie chyba — používateľ údaje jednoducho dopíše ručne.
 */
export interface IcoLookupResult {
  found: boolean
  source?: string
  error?: string
  name?: string | null
  legal_name?: string | null
  legal_form?: string | null
  ico?: string | null
  dic?: string | null
  ic_dph?: string | null
  street?: string | null
  street_no?: string | null
  city?: string | null
  postal_code?: string | null
  region?: string | null
  country?: string | null
  register_court?: string | null
  register_section?: string | null
  register_insert?: string | null
}

// Access control
export interface AccessRole {
  id?: number
  name: string
  label?: string
  permissions?: string[]
}

export interface AccessPermission {
  id?: number
  name: string
  label?: string
  description?: string
}

export interface UserRolesPayload {
  roles: string[]
}

// Tickets
export type TicketStatus = 'reserved' | 'confirmed' | 'cancelled'
export type TicketPaymentStatus = 'none' | 'pending' | 'paid' | 'failed' | 'refunded'
export type AdmissionStatus = 'valid' | 'waitlisted' | 'cancelled'

// Typ lístka (napr. Standard, VIP, Zdarma) alebo workshop v rámci eventu.
export interface TicketTypeItem {
  id?: number
  eventId?: number
  name: string
  kind: 'ticket' | 'workshop'
  description: string | null
  startsAt: string | null
  endsAt: string | null
  priceAmount: number | null
  priceCurrency: string
  capacity: number | null
  maxPerOrder: number
  minPerOrder: number
  requiresAttendeeName: boolean
  /** Workshop otvorený aj pre neregistrovaných — nevyžaduje hlavnú vstupenku. */
  openToPublic?: boolean
  saleStartsAt: string | null
  saleEndsAt: string | null
  isActive: boolean
  sortOrder: number
  soldCount?: number
  remainingCapacity?: number | null
  onSale?: boolean
  /** Je prihlásený návštevník na tomto workshope? (verejný zoznam typov) */
  viewerJoined?: boolean
  /** Je náhradníkom na plnom workshope? */
  viewerWaitlisted?: boolean
  /** Jeho poradie v čakačke (1 = najbližší na rade). */
  viewerWaitlistPosition?: number | null
  /** Počet náhradníkov na workshope. */
  waitlistCount?: number
  createdAt?: string
}

// Jednotlivá vstupenka (jedno miesto) s vlastným QR kódom.
export interface AdmissionItem {
  id?: number
  uuid: string
  ticketId?: number
  eventId?: number
  attendeeName: string | null
  status: AdmissionStatus
  statusLabel: string
  confirmationStatus: 'pending' | 'confirmed' | 'declined' | 'expired' | null
  confirmationStatusLabel: string | null
  confirmationDeadlineAt: string | null
  isCheckedIn: boolean
  checkedInAt: string | null
  /** Kto vstupenku označil pri dverách — meno pribudlo kvôli check-inu na viacerých zariadeniach. */
  checkedInBy?: { id: number; name?: string } | null
  qrUrl: string
  ticketType?: { id: number; name: string; kind?: 'ticket' | 'workshop'; startsAt?: string | null } | null
  holderName?: string | null
  event?: { id: number; name: string } | null
}

// Objednávka / registrácia (jeden nákup jedného kupujúceho).
export interface TicketItem {
  id?: number
  uuid: string
  eventId?: number
  holderName: string
  quantity?: number
  holderEmail?: string
  holderPhone?: string | null
  status: TicketStatus
  statusLabel: string
  paymentStatus: TicketPaymentStatus
  paymentStatusLabel: string
  priceAmount: number | null
  priceCurrency: string | null
  checkedInCount: number
  admissionsTotal: number
  admissions: AdmissionItem[]
  createdAt: string
  deletedAt?: string | null
  event?: EventItem
  permissions?: {
    update: boolean
    checkin: boolean
  }
}

export interface TicketCheckinResult {
  /**
   * `queued` nechodí zo servera — skener ho nastaví sám, keď sken uložil do
   * offline fronty a odošle ho, až keď bude spojenie.
   */
  status: 'checked_in' | 'already_checked_in' | 'invalid' | 'reverted' | 'queued'
  reason?: 'not_found' | 'cancelled' | 'waitlisted' | 'unconfirmed' | null
  admission: AdmissionItem | null
}

// RSVP: potvrdenie účasti účastníkom z e-mailu.
export interface RsvpInfo {
  status: 'pending' | 'confirmed' | 'declined' | 'expired' | null
  statusLabel: string | null
  /** 'order' = objednávateľ rezervoval za účastníka, 'waitlist' = ponuka uvoľneného miesta. */
  reason: 'order' | 'waitlist'
  attendeeName: string | null
  holderName: string | null
  isPaid: boolean
  /** Potvrdenú bezplatnú vstupenku môže účastník ešte zrušiť. */
  canCancel: boolean
  deadlineAt: string | null
  event: { id: number; name: string; dateRangeLabel: string | null } | null
  seats: { label: string; type: string | null }[]
}

export interface CheckinStats {
  total: number
  arrived: number
  remaining: number
}

/** Prehľad do bočného panela zoznamu prihlásených. */
export interface AttendeeSummary {
  admissions: { total: number; arrived: number; remaining: number; cancelled: number; waitlisted: number }
  orders: { total: number; confirmed: number; reserved: number; cancelled: number }
  payments: { currency: string; paidAmount: number; pendingAmount: number; pendingCount: number }
  types: {
    id: number
    name: string
    kind: 'ticket' | 'workshop'
    capacity: number | null
    sold: number
    arrived: number
    waitlisted: number
  }[]
}

// Prehľadová štatistika pre úvodnú stránku dashboardu a adminu.
export type StatsScope = 'dashboard' | 'admin'
export type StatsPeriodKey = 'day' | 'week' | 'month' | 'all'
export type AttentionSeverity = 'info' | 'warning' | 'serious' | 'critical'

export interface StatsMetric {
  label: string
  /** 'money' = suma v centoch, inak celé číslo. */
  format: 'number' | 'money'
  value: number
  previous: number | null
  /** Percentuálna zmena voči predchádzajúcemu rovnako dlhému oknu. */
  change: number | null
}

export interface StatsPeriod {
  key: StatsPeriodKey
  label: string
  from: string | null
  to: string | null
  metrics: Record<string, StatsMetric>
}

export interface StatsTrendDay {
  date: string
  views: number
  events: number
  tickets: number
  admissions: number
  checkins: number
}

export interface StatsViews {
  events: number
  venues: number
  canals: number
  total: number
  /** Priemer zobrazení na jedno zverejnené podujatie. */
  perPublishedEvent: number | null
  /** Koľko percent zo zobrazení podujatí skončilo registráciou. */
  conversion: number | null
  top: { id: number; name: string; status: ModelStatus | null; startAt: string | null; views: number; seats: number }[]
}

export interface StatsAttentionItem {
  key: string
  severity: AttentionSeverity
  label: string
  hint: string
  count: number
  /** Cesta relatívna k rozsahu (dashboard/admin), bez vedúcej lomky. */
  link: string | null
}

export interface StatsOverview {
  scope: StatsScope
  generatedAt: string
  trendDays: number
  periods: StatsPeriod[]
  totals: {
    events: {
      total: number
      published: number
      draft: number
      archived: number
      active: number
      running: number
      today: number
      next7d: number
      withTicketing: number
    }
    venues: { total: number }
    canals: { total: number }
  }
  trend: StatsTrendDay[]
  ticketing: {
    orders: { total: number; paid: number; awaitingPayment: number; revenuePaid: number; revenueAwaiting: number }
    seats: { total: number; valid: number; cancelled: number; waitlisted: number; awaitingConfirmation: number }
    capacity: { seats: number; sold: number; limitedTypes: number; unlimitedTypes: number; rate: number | null }
    attendance: { expected: number; arrived: number; rate: number | null }
  }
  views: StatsViews
  statuses: { key: ModelStatus; label: string; count: number }[]
  sources: { own: number; imported: number; importedRate: number | null }
  attention: StatsAttentionItem[]
  topEvents: { id: number; name: string; startAt: string | null; status: ModelStatus | null; seats: number; capacity: number | null; rate: number | null }[]
  upcoming: { id: number; name: string; startAt: string | null; endAt: string | null; status: ModelStatus | null; venue: string | null; seats: number }[]
  topCanals: { id: number; name: string; eventsTotal: number; eventsRecent: number }[]
  users: { total: number; verified: number; blocked: number; active30d: number } | null
}

/** Kanál v chipe riadku výpisu miest — z `canals_list` v odpovedi. */
export interface RowCanal {
  id: number
  name: string
  isOwner: boolean
}
