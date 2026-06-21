<template>
  <div class="grid gap-4">
    <div class="index-head">
      <div class="head-actions">
        <h1 class="text-2xl font-semibold text-slate-900">Eventy</h1>
        <RouterLink :to="createRoute" class="btn btn-primary">+ Nový event</RouterLink>
      </div>
      <div class="flex flex-wrap gap-2">
        <input v-model="search" type="search" placeholder="Hľadať…" class="form-input max-w-xs" @input="onSearch" />
        <select v-model="statusFilter" class="form-input w-auto" @change="load(1)">
          <option value="">Všetky stavy</option>
          <option value="published">Publikované</option>
          <option value="draft">Návrh</option>
          <option value="archived">Archivované</option>
        </select>
      </div>
    </div>

    <p v-if="loading" class="index-status">Načítavam…</p>
    <p v-else-if="error" class="index-status-error">{{ error }}</p>

    <ul v-else class="index-list">
      <li v-for="event in events" :key="event.id" class="index-list-entry">
        <IndexRow
          :title="event.name"
          :image-url="event.imageUrl"
          :meta="event.dateRangeLabel ?? undefined"
          :status="event.status"
          :show-link="showRoute(event.id)"
        >
          <template #actions>
            <RowActions>
              <RouterLink :to="showRoute(event.id)" class="row-menu-item">Zobraziť</RouterLink>
              <RouterLink :to="editRoute(event.id)" class="row-menu-item">Upraviť</RouterLink>
              <button v-if="event.permissions.publish" class="row-menu-item" @click="togglePublish(event)">
                {{ event.publishedAt ? 'Zrušiť publikovanie' : 'Publikovať' }}
              </button>
              <button v-if="event.permissions.delete && !event.deletedAt" class="row-menu-item row-menu-item-danger" @click="remove(event.id)">Zmazať</button>
              <button v-if="event.permissions.restore && event.deletedAt" class="row-menu-item" @click="restore(event.id)">Obnoviť</button>
            </RowActions>
          </template>
        </IndexRow>
      </li>
      <li v-if="events.length === 0" class="p-4 text-slate-500">Žiadne eventy.</li>
    </ul>

    <AppPaginator :current-page="page" :last-page="lastPage" @change="load" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { indexEvents, deleteEvent, restoreEvent, publishEvent } from '@/api/events'
import type { EventItem } from '@/types'
import IndexRow from '@/components/IndexRow.vue'
import RowActions from '@/components/RowActions.vue'
import AppPaginator from '@/components/AppPaginator.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const toast = useToast()

const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')

const createRoute = computed(() => `${prefix.value}/events/create`)
const showRoute = (id: number) => `${prefix.value}/events/${id}`
const editRoute = (id: number) => `${prefix.value}/events/${id}/edit`

const events = ref<EventItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const statusFilter = ref('')
let searchTimer: ReturnType<typeof setTimeout>

async function load(p = 1) {
  loading.value = true
  error.value = null
  try {
    const res = await indexEvents(scope.value, { page: p, search: search.value || undefined, status: statusFilter.value || undefined })
    events.value = res.data
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
  } catch {
    error.value = 'Nepodarilo sa načítať eventy.'
  } finally {
    loading.value = false
  }
}

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 400)
}

async function togglePublish(event: EventItem) {
  try {
    await publishEvent(event.id, !event.publishedAt)
    toast.success(event.publishedAt ? 'Zrušené publikovanie.' : 'Event publikovaný.')
    load(page.value)
  } catch { toast.error('Akcia zlyhala.') }
}

async function remove(id: number) {
  if (!confirm('Naozaj zmazať?')) return
  try {
    await deleteEvent(id)
    toast.success('Event zmazaný.')
    load(page.value)
  } catch { toast.error('Mazanie zlyhalo.') }
}

async function restore(id: number) {
  try {
    await restoreEvent(id)
    toast.success('Event obnovený.')
    load(page.value)
  } catch { toast.error('Obnova zlyhala.') }
}

onMounted(() => load())
</script>
