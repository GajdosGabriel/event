<template>
  <div class="grid gap-4">
    <div class="index-head">
      <div class="head-actions">
        <h1 class="text-2xl font-semibold text-slate-900">{{ cfg.title }}</h1>
        <RouterLink :to="`${prefix}/create`" class="btn btn-primary">+ {{ cfg.createLabel }}</RouterLink>
      </div>
      <div class="flex flex-wrap gap-2">
        <input v-model="search" type="search" placeholder="Hľadať…" class="form-input max-w-xs" @input="onSearch" />
        <select v-if="apiStatusOptions.length" v-model="statusFilter" class="form-input w-auto" @change="load(1)">
          <option value="">Všetky stavy</option>
          <option v-for="opt in apiStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <!-- Active canal filter chip -->
        <button v-if="canalFilter" type="button"
          class="inline-flex items-center gap-1.5 rounded-full bg-teal-100 px-3 py-1 text-xs font-medium text-teal-800 ring-1 ring-inset ring-teal-300 hover:bg-teal-200 transition-colors"
          @click="clearCanalFilter">
          {{ canalFilter.name }}
          <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
        </button>
      </div>
    </div>

    <p v-if="loading" class="index-status">Načítavam…</p>
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
        >
          <template v-if="resource === 'event'" #detail>
            <div class="mt-1.5 flex flex-col gap-1">
              <!-- Dátumy -->
              <div v-if="item.startLabel" class="flex flex-wrap items-center gap-1.5">
                <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-200">
                  <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                  Začiatok: {{ item.startLabel }}
                </span>
                <span v-if="item.endLabel" class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                  <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                  Koniec: {{ item.endLabel }}
                </span>
              </div>
              <!-- Kanál + venue + stav + dátum vzniku -->
              <div class="flex flex-wrap items-center gap-1.5">
                <button v-if="item.canalName && resource === 'event'" type="button"
                  class="inline-flex items-center rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700 ring-1 ring-inset ring-teal-200 hover:bg-teal-100 transition-colors cursor-pointer"
                  :title="`Filtrovať podľa kanála: ${item.canalName}`"
                  @click.prevent="setCanalFilter(item)">
                  {{ item.canalName }}
                </button>
                <span v-else-if="item.canalName"
                  class="inline-flex items-center rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700 ring-1 ring-inset ring-teal-200">
                  {{ item.canalName }}
                </span>
                <span v-if="item.venueName"
                  class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                  {{ item.venueName }}
                </span>
                <span v-if="item.createdAt"
                  class="inline-flex items-center gap-1 rounded-md bg-slate-50 px-2 py-0.5 text-xs text-slate-400 ring-1 ring-inset ring-slate-200">
                  <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  {{ item.createdAt }}
                </span>
                <span v-if="item.deletedAt"
                  class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600 ring-1 ring-inset ring-red-200">
                  zmazaný
                </span>
              </div>
            </div>
          </template>
          <template #actions>
            <RowActions>
              <RouterLink :to="`${prefix}/${item.id}`" class="row-menu-item">Zobraziť</RouterLink>
              <RouterLink :to="`${prefix}/${item.id}/edit`" class="row-menu-item">Upraviť</RouterLink>
              <button
                v-if="item.permissions?.publish"
                class="row-menu-item"
                @click="togglePublish(item)"
              >{{ item.publishedAt ? 'Zrušiť publikovanie' : 'Publikovať' }}</button>
              <button
                v-if="item.permissions?.delete && !item.deletedAt"
                class="row-menu-item row-menu-item-danger"
                @click="remove(item.id)"
              >Zmazať</button>
              <button
                v-if="item.permissions?.restore && item.deletedAt"
                class="row-menu-item"
                @click="restore(item.id)"
              >Obnoviť</button>
            </RowActions>
          </template>
        </IndexRow>
      </li>
      <li v-if="!loading && items.length === 0" class="p-4 text-slate-500">{{ cfg.emptyLabel }}</li>
    </ul>

    <AppPaginator :current-page="page" :last-page="lastPage" @change="load" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import http from '@/api/index'
import IndexRow from '@/components/IndexRow.vue'
import RowActions from '@/components/RowActions.vue'
import AppPaginator from '@/components/AppPaginator.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps<{
  resource: 'canal' | 'venue' | 'event'
  scope?: 'dashboard' | 'admin'
}>()

const route = useRoute()
const toast = useToast()

const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => `${scope.value === 'admin' ? '/admin' : '/dashboard'}/${props.resource}s`)

// ── Per-resource config ──────────────────────────────────────────────────────

interface ResourceConfig {
  title: string
  createLabel: string
  emptyLabel: string
  apiSlug: string
}

const CONFIGS: Record<string, ResourceConfig> = {
  canal: {
    title: 'Kanály',
    createLabel: 'Nový kanál',
    emptyLabel: 'Žiadne kanály.',
    apiSlug: 'canals',
  },
  venue: {
    title: 'Miesta',
    createLabel: 'Nové miesto',
    emptyLabel: 'Žiadne miesta.',
    apiSlug: 'venues',
  },
  event: {
    title: 'Eventy',
    createLabel: 'Nový event',
    emptyLabel: 'Žiadne eventy.',
    apiSlug: 'events',
  },
}

const cfg = computed(() => CONFIGS[props.resource])

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
  venueName?: string | null
  startLabel?: string | null
  endLabel?: string | null
  [key: string]: unknown
}

function fmtDate(d: Date) {
  return d.toLocaleDateString('sk-SK', { day: 'numeric', month: 'short', year: 'numeric' })
}
function fmtTime(d: Date) {
  return d.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })
}
function fmtDateTime(d: Date) {
  return `${fmtDate(d)}, ${fmtTime(d)}`
}

function mapItem(raw: Record<string, unknown>): ResourceItem {
  const primaryImage = raw['primary_image'] as Record<string, string> | null
  const imageUrl =
    (raw['image_url'] as string) ??
    primaryImage?.['thumb'] ??
    (raw['thumb_image'] as string) ??
    null

  // meta: only for non-event resources (venue address, etc.)
  let meta: string | null = null
  let startLabel: string | null = null
  let endLabel: string | null = null

  if (raw['start_at']) {
    const start = new Date(raw['start_at'] as string)
    startLabel = fmtDateTime(start)
    if (raw['end_at']) {
      const end = new Date(raw['end_at'] as string)
      const sameDay = start.toDateString() === end.toDateString()
      endLabel = sameDay ? fmtTime(end) : fmtDateTime(end)
    }
  } else if (raw['street']) {
    meta = [raw['street'], raw['postcode']].filter(Boolean).join(', ')
  }

  const canalRaw = raw['canal'] as { id?: number; name: string } | null
  const venueRaw = raw['venue'] as { name: string } | null
  const canalId = (canalRaw?.id ?? (raw['canal_id'] as number)) ?? null
  const canalName = canalRaw?.name ?? (raw['canal_name'] as string) ?? null
  const venueName = venueRaw?.name ?? null

  const createdAtRaw = raw['created_at'] as string | null
  const createdAt = createdAtRaw ? fmtDate(new Date(createdAtRaw)) : null

  const { meta: _rawMeta, ...restRaw } = raw
  return {
    id: raw['id'] as number,
    name: (raw['name'] as string) ?? '',
    status: (raw['status'] as string) ?? '',
    statusLabel: (raw['status_label'] as string) ?? null,
    imageUrl,
    meta,
    startLabel,
    endLabel,
    publishedAt: (raw['published_at'] as string) ?? null,
    deletedAt: (raw['deleted_at'] as string) ?? null,
    createdAt,
    permissions: (raw['permissions'] as Record<string, boolean>) ?? {},
    canalId,
    canalName,
    venueName,
    ...restRaw,
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
const canalFilter = ref<{ id: number; name: string } | null>(null)
const apiStatusOptions = ref<{ value: string; label: string }[]>([])
let searchTimer: ReturnType<typeof setTimeout>

watch(() => route.query.municipality, () => load(1))

// ── API calls (generic — no per-resource imports needed) ────────────────────

const apiBase = computed(() => `/${scope.value}/${cfg.value.apiSlug}`)

async function load(p = 1) {
  loading.value = true
  error.value = null
  try {
    const params: Record<string, unknown> = { page: p }
    if (search.value) params['search'] = search.value
    if (statusFilter.value) params['status'] = statusFilter.value
    if (canalFilter.value) params['canal_id'] = canalFilter.value.id
    if (route.query.municipality) params['municipality'] = route.query.municipality
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
    error.value = `Nepodarilo sa načítať ${cfg.value.title.toLowerCase()}.`
  } finally {
    loading.value = false
  }
}

function setCanalFilter(item: ResourceItem) {
  if (!item.canalId || !item.canalName) return
  canalFilter.value = { id: item.canalId, name: item.canalName }
  load(1)
}

function clearCanalFilter() {
  canalFilter.value = null
  load(1)
}

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 400)
}

async function togglePublish(item: ResourceItem) {
  try {
    await http.post(`${apiBase.value}/${item.id}/publish`, { published: !item.publishedAt })
    toast.success(item.publishedAt ? 'Zrušené publikovanie.' : 'Publikované.')
    load(page.value)
  } catch { toast.error('Akcia zlyhala.') }
}

async function remove(id: number) {
  if (!confirm('Naozaj zmazať?')) return
  try {
    await http.delete(`${apiBase.value}/${id}`)
    toast.success('Zmazané.')
    load(page.value)
  } catch { toast.error('Mazanie zlyhalo.') }
}

async function restore(id: number) {
  try {
    await http.post(`${apiBase.value}/${id}/restore`)
    toast.success('Obnovené.')
    load(page.value)
  } catch { toast.error('Obnova zlyhala.') }
}

// Reload when resource prop changes (router reuse)
watch(() => props.resource, () => { search.value = ''; statusFilter.value = ''; canalFilter.value = null; apiStatusOptions.value = []; load(1) })

onMounted(() => load())
</script>
