<template>
  <div class="grid gap-4">
    <div class="index-head">
      <div class="head-actions">
        <h1 class="text-2xl font-semibold text-slate-900">{{ cfg.title }}</h1>
        <RouterLink :to="`${prefix}/create`" class="btn btn-primary">+ {{ cfg.createLabel }}</RouterLink>
      </div>
      <div class="flex flex-wrap gap-2">
        <input v-model="search" type="search" placeholder="Hľadať…" class="form-input max-w-xs" @input="onSearch" />
        <select v-if="cfg.statusOptions" v-model="statusFilter" class="form-input w-auto" @change="load(1)">
          <option value="">Všetky stavy</option>
          <option v-for="opt in cfg.statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
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
          :status="item.status"
          :show-link="`${prefix}/${item.id}`"
        >
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
  statusOptions?: { value: string; label: string }[]
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
    statusOptions: [
      { value: 'published', label: 'Publikované' },
      { value: 'draft', label: 'Návrh' },
      { value: 'archived', label: 'Archivované' },
    ],
  },
}

const cfg = computed(() => CONFIGS[props.resource])

// ── Generic item shape (maps what backend returns) ──────────────────────────

interface ResourceItem {
  id: number
  name: string
  status: string
  imageUrl?: string | null
  meta?: string | null
  publishedAt?: string | null
  deletedAt?: string | null
  permissions?: Record<string, boolean>
  [key: string]: unknown
}

function mapItem(raw: Record<string, unknown>): ResourceItem {
  const primaryImage = raw['primary_image'] as Record<string, string> | null
  const imageUrl =
    (raw['image_url'] as string) ??
    primaryImage?.['thumb'] ??
    (raw['thumb_image'] as string) ??
    null

  // meta: date range for events, address for venues
  let meta: string | null = null
  if (raw['start_at']) {
    const start = new Date(raw['start_at'] as string)
    const fmt = (d: Date) => d.toLocaleDateString('sk-SK', { day: 'numeric', month: 'short', year: 'numeric' })
    meta = raw['end_at'] ? `${fmt(start)} – ${fmt(new Date(raw['end_at'] as string))}` : fmt(start)
  } else if (raw['street']) {
    meta = [raw['street'], raw['postcode']].filter(Boolean).join(', ')
  }

  return {
    id: raw['id'] as number,
    name: (raw['name'] as string) ?? '',
    status: (raw['status'] as string) ?? '',
    imageUrl,
    meta,
    publishedAt: (raw['published_at'] as string) ?? null,
    deletedAt: (raw['deleted_at'] as string) ?? null,
    permissions: (raw['permissions'] as Record<string, boolean>) ?? {},
    ...raw,
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
    if (route.query.municipality) params['municipality'] = route.query.municipality
    const { data } = await http.get(apiBase.value, { params })
    const list: Record<string, unknown>[] = data.data ?? data
    items.value = list.map(mapItem)
    page.value = data.meta?.current_page ?? 1
    lastPage.value = data.meta?.last_page ?? 1
  } catch {
    error.value = `Nepodarilo sa načítať ${cfg.value.title.toLowerCase()}.`
  } finally {
    loading.value = false
  }
}

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 400)
}

async function togglePublish(item: ResourceItem) {
  try {
    await http.put(`${apiBase.value}/${item.id}`, { published: !item.publishedAt })
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
watch(() => props.resource, () => { search.value = ''; statusFilter.value = ''; load(1) })

onMounted(() => load())
</script>
