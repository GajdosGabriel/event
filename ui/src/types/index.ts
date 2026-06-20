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

export interface ModelPermissions {
  view: boolean
  update: boolean
  publish?: boolean
  delete: boolean
  archive?: boolean
  restore: boolean
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
}

export interface AuthIdentity {
  id: number
  canal_id: number | null
  canal: string
  roles?: string[]
  canal_context?: {
    active: AuthCanalContextActive | null
    is_owner: boolean
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
}

// Event
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
  registrationDeadlineAt: string | null
  publishedAt: string | null
  deletedAt: string | null
  website: string | null
  locationName: string | null
  street: string | null
  postcode: string | null
  country: string | null
  latitude: number | null
  longitude: number | null
  imageUrl: string | null
  uploadedFiles: UploadedFileItem[]
  permissions: ModelPermissions
  allowedStatuses: AllowedStatusOption[]
  phone: string | null
  email: string | null
  municipality: { id: number; name: string; fullname?: string } | null
  canal: { id: number; name: string; thumbImage?: string } | null
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
}

// Canal
export type CanalIdentityMode = 'personal' | 'organization' | 'pseudonymous'

export interface CanalItem {
  id: number
  municipalityId: number | null
  venueId: number | null
  identityMode: CanalIdentityMode
  name: string
  slug: string
  titlePrefix: string | null
  titleSuffix: string | null
  email: string | null
  body: string | null
  imageUrl: string | null
  publishedAt: string | null
  status: ModelStatus
  website: string | null
  deletedAt: string | null
  createdAt: string
  updatedAt: string
  uploadedFiles: UploadedFileItem[]
  permissions: ModelPermissions
  allowedStatuses: AllowedStatusOption[]
}

// Venue
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
  phone: string | null
  country: string | null
  latitude: number | null
  longitude: number | null
  capacity: number | null
  openingHours: string | null
  category: string | null
  imageUrl: string | null
  status: ModelStatus
  deletedAt: string | null
  createdAt: string
  updatedAt: string
  uploadedFiles: UploadedFileItem[]
  permissions: ModelPermissions
  allowedStatuses: AllowedStatusOption[]
}

// Organization
export interface OrganizationItem {
  id: number
  name: string
  slug: string
  body: string | null
  website: string | null
  email: string | null
  phone: string | null
  status: ModelStatus
  deletedAt: string | null
  createdAt: string
  updatedAt: string
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
