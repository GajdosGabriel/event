<template>
  <div class="grid gap-4">
    <div class="index-head">
      <div class="head-actions">
        <h1 class="text-2xl font-semibold text-slate-900">Miesta</h1>
        <RouterLink :to="createRoute" class="btn btn-primary">+ Nové miesto</RouterLink>
      </div>
      <input v-model="search" type="search" placeholder="Hľadať…" class="form-input max-w-xs" @input="onSearch" />
    </div>

    <p v-if="loading" class="index-status">Načítavam…</p>
    <p v-else-if="error" class="index-status-error">{{ error }}</p>

    <ul v-else class="index-list">
      <li v-for="venue in venues" :key="venue.id" class="index-list-entry">
        <IndexRow :title="venue.name" :image-url="venue.imageUrl" :status="venue.status" :show-link="showRoute(venue.id)">
          <template #actions>
            <RouterLink :to="showRoute(venue.id)" class="action-btn">Zobraziť</RouterLink>
            <RouterLink :to="editRoute(venue.id)" class="action-btn">Upraviť</RouterLink>
            <button v-if="venue.permissions.delete && !venue.deletedAt" class="action-btn action-btn-danger" @click="remove(venue.id)">Zmazať</button>
            <button v-if="venue.permissions.restore && venue.deletedAt" class="action-btn" @click="restore(venue.id)">Obnoviť</button>
          </template>
        </IndexRow>
      </li>
      <li v-if="venues.length === 0" class="p-4 text-slate-500">Žiadne miesta.</li>
    </ul>

    <AppPaginator :current-page="page" :last-page="lastPage" @change="load" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { indexVenues, deleteVenue, restoreVenue } from '@/api/venues'
import type { VenueItem } from '@/types'
import IndexRow from '@/components/IndexRow.vue'
import AppPaginator from '@/components/AppPaginator.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const toast = useToast()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const createRoute = computed(() => `${prefix.value}/venues/create`)
const showRoute = (id: number) => `${prefix.value}/venues/${id}`
const editRoute = (id: number) => `${prefix.value}/venues/${id}/edit`

const venues = ref<VenueItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
let searchTimer: ReturnType<typeof setTimeout>

async function load(p = 1) {
  loading.value = true; error.value = null
  try {
    const res = await indexVenues(scope.value, { page: p, search: search.value || undefined })
    venues.value = res.data; page.value = res.meta.current_page; lastPage.value = res.meta.last_page
  } catch { error.value = 'Nepodarilo sa načítať miesta.' }
  finally { loading.value = false }
}

function onSearch() { clearTimeout(searchTimer); searchTimer = setTimeout(() => load(1), 400) }

async function remove(id: number) {
  if (!confirm('Naozaj zmazať?')) return
  try { await deleteVenue(id); toast.success('Miesto zmazané.'); load(page.value) } catch { toast.error('Mazanie zlyhalo.') }
}

async function restore(id: number) {
  try { await restoreVenue(id); toast.success('Miesto obnovené.'); load(page.value) } catch { toast.error('Obnova zlyhala.') }
}

onMounted(() => load())
</script>
