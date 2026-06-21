<template>
  <div class="grid gap-4">
    <div class="index-head">
      <div class="head-actions">
        <h1 class="text-2xl font-semibold text-slate-900">Kanály</h1>
        <RouterLink :to="createRoute" class="btn btn-primary">+ Nový kanál</RouterLink>
      </div>
      <input v-model="search" type="search" placeholder="Hľadať…" class="form-input max-w-xs" @input="onSearch" />
    </div>

    <p v-if="loading" class="index-status">Načítavam…</p>
    <p v-else-if="error" class="index-status-error">{{ error }}</p>

    <ul v-else class="index-list">
      <li v-for="canal in canals" :key="canal.id" class="index-list-entry">
        <IndexRow
          :title="canal.name"
          :image-url="canal.imageUrl"
          :status="canal.status"
          :show-link="showRoute(canal.id)"
        >
          <template #actions>
            <RowActions>
              <RouterLink :to="showRoute(canal.id)" class="row-menu-item">Zobraziť</RouterLink>
              <RouterLink :to="editRoute(canal.id)" class="row-menu-item">Upraviť</RouterLink>
              <button v-if="canal.permissions.delete && !canal.deletedAt" class="row-menu-item row-menu-item-danger" @click="remove(canal.id)">Zmazať</button>
              <button v-if="canal.permissions.restore && canal.deletedAt" class="row-menu-item" @click="restore(canal.id)">Obnoviť</button>
            </RowActions>
          </template>
        </IndexRow>
      </li>
      <li v-if="canals.length === 0" class="p-4 text-slate-500">Žiadne kanály.</li>
    </ul>

    <AppPaginator :current-page="page" :last-page="lastPage" @change="load" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { indexCanals, deleteCanal, restoreCanal } from '@/api/canals'
import type { CanalItem } from '@/types'
import IndexRow from '@/components/IndexRow.vue'
import RowActions from '@/components/RowActions.vue'
import AppPaginator from '@/components/AppPaginator.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const toast = useToast()

const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const createRoute = computed(() => `${prefix.value}/canals/create`)
const showRoute = (id: number) => `${prefix.value}/canals/${id}`
const editRoute = (id: number) => `${prefix.value}/canals/${id}/edit`

const canals = ref<CanalItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
let searchTimer: ReturnType<typeof setTimeout>

async function load(p = 1) {
  loading.value = true
  error.value = null
  try {
    const res = await indexCanals(scope.value, { page: p, search: search.value || undefined })
    canals.value = res.data
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
  } catch { error.value = 'Nepodarilo sa načítať kanály.' }
  finally { loading.value = false }
}

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 400)
}

async function remove(id: number) {
  if (!confirm('Naozaj zmazať?')) return
  try { await deleteCanal(id); toast.success('Kanál zmazaný.'); load(page.value) }
  catch { toast.error('Mazanie zlyhalo.') }
}

async function restore(id: number) {
  try { await restoreCanal(id); toast.success('Kanál obnovený.'); load(page.value) }
  catch { toast.error('Obnova zlyhala.') }
}

onMounted(() => load())
</script>
