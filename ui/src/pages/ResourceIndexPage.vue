<template>
  <div class="grid gap-4">
    <div class="index-head">
      <div class="head-actions">
        <h1 class="text-2xl font-semibold text-slate-900">{{ cfg.title }}</h1>
        <RouterLink :to="`${prefix}/create`" class="btn btn-primary">+ {{ cfg.createLabel }}</RouterLink>
      </div>
      <ResourceFilterBar
        v-model:search="search"
        v-model:status="statusFilter"
        v-model:sort="sortFilter"
        v-model:date-from="dateFrom"
        v-model:date-to="dateTo"
        :status-options="statusOptions"
        :sort-options="sortOptions"
        :show-date-range="resource === 'event'"
        :canal-filter="canalFilter"
        @change="load(1)"
        @clear-canal="canalFilter = null"
      />
    </div>

    <p v-if="loading" class="index-status">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="index-status-error">{{ error }}</p>

    <ul v-else class="index-list">
      <li v-for="item in items" :key="item.id" class="index-list-entry">
        <IndexRow
          :title="item.name"
          :image-url="item.imageUrl ?? undefined"
          :meta="item.meta ?? undefined"
          :status="item.statusLabel ?? item.status"
          :status-value="item.status"
          :show-link="`${prefix}/${item.id}`"
          :views-count="item.viewsCount"
          :muted="Boolean(item.deletedAt)"
        >
          <template v-if="resource === 'event'" #detail>
            <EventRowDetail
              :start-at="item.startAt"
              :end-at="item.endAt"
              :canal-id="item.canalId"
              :canal-name="item.canalName"
              :organization-title="item.organizationTitle"
              :venue-name="item.venueName"
              :created-at="item.createdAt"
              :deleted="Boolean(item.deletedAt)"
              @filter-canal="setCanalFilter(item)"
            />
          </template>
          <template #actions>
            <RowActions>
              <RouterLink :to="`${prefix}/${item.id}`" class="row-menu-item">{{ t('common.view') }}</RouterLink>
              <RouterLink v-if="item.permissions?.update" :to="`${prefix}/${item.id}/edit`" class="row-menu-item">{{ t('common.edit') }}</RouterLink>
              <button
                v-else-if="resource === 'event' && item.permissions?.duplicate"
                class="row-menu-item"
                @click="duplicate(item)"
              >{{ t('common.copy') }}</button>
              <button
                v-if="item.permissions?.publish || item.permissions?.unpublish"
                class="row-menu-item"
                @click="togglePublish(item)"
              >{{ item.permissions?.unpublish ? t('common.unpublish') : t('common.publish') }}</button>
              <button
                v-if="item.permissions?.delete && !item.deletedAt"
                class="row-menu-item row-menu-item-danger"
                @click="remove(item.id)"
              >{{ t('common.remove') }}</button>
              <button
                v-if="item.permissions?.restore && item.deletedAt"
                class="row-menu-item"
                @click="restore(item.id)"
              >{{ t('common.restore') }}</button>
            </RowActions>
          </template>
        </IndexRow>
      </li>
      <li v-if="!loading && items.length === 0" class="p-4 text-slate-500">{{ cfg.emptyLabel }}</li>
    </ul>

    <AppPaginator :current-page="page" :last-page="lastPage" @change="goToPage" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import http from '@/api/index'
import IndexRow from '@/components/IndexRow.vue'
import EventRowDetail from '@/components/EventRowDetail.vue'
import RowActions from '@/components/RowActions.vue'
import AppPaginator from '@/components/AppPaginator.vue'
import ResourceFilterBar, { type FilterOption } from '@/components/ResourceFilterBar.vue'
import { useToast } from '@/composables/useToast'
import { useSettings } from '@/composables/useSettings'
import { fmtDate } from '@/utils/dateFormat'
import { usePageQuery } from '@/composables/usePageQuery'
import { useI18n } from '@/i18n'

const props = defineProps<{
  resource: 'canal' | 'venue' | 'event'
  scope?: 'dashboard' | 'admin'
}>()

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { settings } = useSettings()
const { t } = useI18n()

const perPage = computed(() => {
  if (props.resource === 'event') return settings.value.eventsPerPage
  if (props.resource === 'venue') return settings.value.venuesPerPage
  return settings.value.canalsPerPage
})

const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => `${scope.value === 'admin' ? '/admin' : '/dashboard'}/${props.resource}s`)

// ── Per-resource config ──────────────────────────────────────────────────────

interface ResourceConfig {
  title: string
  createLabel: string
  emptyLabel: string
  loadFailed: string
  apiSlug: string
}

// Popisky nesie slovník pod svojím zdrojom (`canals.index`, …), nie tento
// súbor — tie isté slová používa aj detail a formulár.
const API_SLUGS: Record<string, string> = { canal: 'canals', venue: 'venues', event: 'events' }

const cfg = computed<ResourceConfig>(() => {
  const section = API_SLUGS[props.resource] as 'canals' | 'venues' | 'events'
  return {
    title: t(`${section}.index.title`),
    createLabel: t(`${section}.index.create`),
    emptyLabel: t(`${section}.index.empty`),
    loadFailed: t(`${section}.index.loadFailed`),
    apiSlug: section,
  }
})

// ── Generic item shape (maps what backend returns) ──────────────────────────

interface ResourceItem {
  id: number
  name: string
  status: string
  statusLabel?: string | null
  imageUrl?: string | null
  meta?: string | null
  publishedAt?: string | null
  deletedAt?: string | null
  createdAt?: string | null
  permissions?: Record<string, boolean>
  canalId?: number | null
  canalName?: string | null
  /** Firma nad kanálom — v odpovedi je len v admin výpise. */
  organizationTitle?: string | null
  venueName?: string | null
  /** ISO termín; formátuje až EventRowDetail, ktorý z neho počíta aj fázu. */
  startAt?: string | null
  endAt?: string | null
  /** Počet zobrazení; chýba, keď naň používateľ nemá právo. */
  viewsCount?: number | null
}

function mapItem(raw: Record<string, unknown>): ResourceItem {
  const primaryImage = raw['primary_image'] as Record<string, string> | null
  const imageUrl =
    (raw['image_url'] as string) ??
    primaryImage?.['thumb'] ??
    (raw['thumb_image'] as string) ??
    null

  // meta: only for non-event resources (venue address, etc.)
  const startAt = (raw['start_at'] as string) ?? null
  const endAt = (raw['end_at'] as string) ?? null
  const meta = !startAt && raw['street']
    ? [raw['street'], raw['postcode']].filter(Boolean).join(', ')
    : null

  const canalRaw = raw['canal'] as { id?: number; name: string; organization?: { title: string } | null } | null
  const venueRaw = raw['venue'] as { name: string } | null
  const canalId = (canalRaw?.id ?? (raw['canal_id'] as number)) ?? null
  const canalName = canalRaw?.name ?? (raw['canal_name'] as string) ?? null
  const venueName = venueRaw?.name ?? null

  const createdAtRaw = raw['created_at'] as string | null
  const createdAt = createdAtRaw ? fmtDate(createdAtRaw) : null

  return {
    id: raw['id'] as number,
    name: (raw['name'] as string) ?? '',
    status: (raw['status'] as string) ?? '',
    statusLabel: (raw['status_label'] as string) ?? null,
    imageUrl,
    meta,
    startAt,
    endAt,
    publishedAt: (raw['published_at'] as string) ?? null,
    deletedAt: (raw['deleted_at'] as string) ?? null,
    createdAt,
    permissions: (raw['permissions'] as Record<string, boolean>) ?? {},
    canalId,
    canalName,
    organizationTitle: canalRaw?.organization?.title ?? null,
    venueName,
    // Backend ho do odpovede dáva len organizátorovi a adminovi — pre ostatných
    // kľúč vôbec neexistuje a počítadlo sa nevykreslí.
    viewsCount: typeof raw['views_count'] === 'number' ? (raw['views_count'] as number) : null,
  }
}

// ── State ───────────────────────────────────────────────────────────────────

const items = ref<ResourceItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const statusFilter = ref('')
const sortFilter = ref('newest')
const dateFrom = ref('')
const dateTo = ref('')
const canalFilter = ref<{ id: number; name: string } | null>(null)
const apiStatusOptions = ref<FilterOption[]>([])

/**
 * „Zmazaný" nie je stav z backendu (je to soft delete), ale pre používateľa je
 * to tá istá otázka — „v akom stave to je". Vo filtri preto stojí vedľa
 * ostatných stavov a do API sa prekladá na `deleted=1`.
 */
const DELETED_STATUS = 'deleted'
const statusOptions = computed<FilterOption[]>(() => [
  ...apiStatusOptions.value,
  { value: DELETED_STATUS, label: t('filters.deleted') },
])
const sortOptions = computed<FilterOption[]>(() => {
  const opts: FilterOption[] = [
    { value: 'newest', label: t('filters.sort.newest') },
    { value: 'oldest', label: t('filters.sort.oldest') },
    { value: 'name', label: t('filters.sort.name') },
  ]
  if (props.resource === 'event') opts.push({ value: 'upcoming', label: t('filters.sort.upcoming') })
  return opts
})

watch(() => route.query.municipality, () => load(1))

// ── Filters ⇆ URL query (shareable links, survives reload/back) ─────────────

/** Bez `page` — to do adresy pridá až `replaceQuery`/`goToPage`. */
function filtersToQuery(): Record<string, string> {
  const q: Record<string, string> = {}
  if (route.query.municipality) q['municipality'] = String(route.query.municipality)
  if (search.value) q['q'] = search.value
  if (statusFilter.value) q['status'] = statusFilter.value
  if (sortFilter.value && sortFilter.value !== 'newest') q['sort'] = sortFilter.value
  if (dateFrom.value) q['from'] = dateFrom.value
  if (dateTo.value) q['to'] = dateTo.value
  if (canalFilter.value) {
    q['canal_id'] = String(canalFilter.value.id)
    q['canal_name'] = canalFilter.value.name
  }
  return q
}

function filtersFromQuery() {
  const q = route.query
  search.value = typeof q.q === 'string' ? q.q : ''
  statusFilter.value = typeof q.status === 'string' ? q.status : ''
  sortFilter.value = typeof q.sort === 'string' ? q.sort : 'newest'
  dateFrom.value = typeof q.from === 'string' ? q.from : ''
  dateTo.value = typeof q.to === 'string' ? q.to : ''
  // Staré odkazy s ?deleted=1 nech ďalej fungujú.
  if (q.deleted === '1') statusFilter.value = DELETED_STATUS
  canalFilter.value = typeof q.canal_id === 'string' && Number(q.canal_id) > 0
    ? {
        id: Number(q.canal_id),
        name: typeof q.canal_name === 'string' ? q.canal_name : t('canals.index.fallbackName', { id: String(q.canal_id) }),
      }
    : null
}

function syncQuery(page: number) {
  replaceQuery(filtersToQuery(), page)
}

/**
 * Číslo strany drží v adrese (`?page=`) — inak sa zoznam po návrate z detailu
 * tlačidlom „späť" vždy začínal na prvej strane.
 */
const { pageFromQuery, load, goToPage, replaceQuery } = usePageQuery(fetchPage)

// ── API calls (generic — no per-resource imports needed) ────────────────────

const apiBase = computed(() => `/${scope.value}/${cfg.value.apiSlug}`)

async function fetchPage(p: number) {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, unknown> = { page: p, per_page: perPage.value }
    if (search.value) params['search'] = search.value
    if (statusFilter.value === DELETED_STATUS) {
      params['deleted'] = 1
    } else if (statusFilter.value) {
      params['status'] = statusFilter.value
    }
    if (sortFilter.value && sortFilter.value !== 'newest') params['sort'] = sortFilter.value
    if (dateFrom.value) params['date_from'] = dateFrom.value
    if (dateTo.value) params['date_to'] = dateTo.value
    if (canalFilter.value) params['canal_id'] = canalFilter.value.id
    if (route.query.municipality) params['municipality'] = route.query.municipality
    syncQuery(p)
    const { data } = await http.get(apiBase.value, { params })
    const list: Record<string, unknown>[] = data.data ?? data
    items.value = list.map(mapItem)
    page.value = data.meta?.current_page ?? 1
    lastPage.value = data.meta?.last_page ?? 1

    // Populate status options from API on first successful load
    const allowed = data.meta?.allowed_statuses as { value: string; label: string }[] | undefined
    if (allowed?.length && !apiStatusOptions.value.length) {
      apiStatusOptions.value = allowed
    }
  } catch {
    error.value = cfg.value.loadFailed
  } finally {
    loading.value = false
  }
}

function setCanalFilter(item: ResourceItem) {
  if (!item.canalId || !item.canalName) return
  canalFilter.value = { id: item.canalId, name: item.canalName }
  load(1)
}

async function togglePublish(item: ResourceItem) {
  // Smer určuje právo, nie published_at — publikované podujatie má `unpublish`,
  // koncept `publish`. Endpoint je ten istý, líši sa len príznakom.
  const publishing = !item.permissions?.unpublish
  try {
    await http.post(`${apiBase.value}/${item.id}/publish`, { published: publishing })
    toast.success(publishing ? t('common.published') : t('common.unpublished'))
    load(page.value)
  } catch { toast.error(t('common.actionFailed')) }
}

async function remove(id: number) {
  if (!confirm(t('common.removeConfirm'))) return
  try {
    await http.delete(`${apiBase.value}/${id}`)
    toast.success(t('common.removed'))
    load(page.value)
  } catch { toast.error(t('common.removeFailed')) }
}

async function restore(id: number) {
  try {
    await http.post(`${apiBase.value}/${id}/restore`)
    toast.success(t('common.restored'))
    load(page.value)
  } catch { toast.error(t('common.restoreFailed')) }
}

async function duplicate(item: ResourceItem) {
  try {
    const { data } = await http.post(`${apiBase.value}/${item.id}/duplicate`)
    const newId = (data.data ?? data).id
    toast.success(t('events.copy.created'))
    router.push(`${prefix.value}/${newId}/edit`)
  } catch { toast.error(t('events.copy.failed')) }
}

// Reload when resource prop changes (router reuse)
watch(() => props.resource, () => {
  search.value = ''
  statusFilter.value = ''
  sortFilter.value = 'newest'
  dateFrom.value = ''
  dateTo.value = ''
  canalFilter.value = null
  apiStatusOptions.value = []
  load(1)
})

onMounted(() => {
  filtersFromQuery()
  load(pageFromQuery())
})
</script>
