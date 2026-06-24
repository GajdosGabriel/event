<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <p v-if="loading" class="text-slate-600">Načítavam…</p>
    <div v-else-if="error" class="show-not-found">
      <h1>Kanál nenájdený</h1>
      <RouterLink :to="indexRoute">← Späť</RouterLink>
    </div>

    <template v-else-if="canal">
      <!-- Breadcrumb + akcie -->
      <div class="mb-4 flex flex-wrap items-center gap-2">
        <RouterLink :to="indexRoute" class="action-btn">← Späť</RouterLink>
        <RouterLink v-if="canal.permissions.update" :to="editRoute" class="action-btn">Upraviť</RouterLink>
        <span class="ml-auto rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
          :class="statusClass(canal.status)">{{ canal.status }}</span>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <!-- Ľavý stĺpec -->
        <div class="grid gap-4">
          <!-- Hlavné info -->
          <div class="show-card">
            <div class="flex flex-wrap items-start gap-4">
              <img v-if="canal.imageUrl" :src="canal.imageUrl" :alt="canal.name"
                class="h-20 w-20 shrink-0 rounded-xl object-cover ring-1 ring-slate-200" />
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <h1 class="text-3xl font-bold text-slate-900">{{ canal.name }}</h1>
                  <span class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700">
                    {{ identityModeLabel(canal.identityMode) }}
                  </span>
                </div>
                <p v-if="canal.titlePrefix || canal.titleSuffix" class="mt-1 text-sm text-slate-500">
                  <span v-if="canal.titlePrefix">{{ canal.titlePrefix }} </span>
                  {{ canal.name }}
                  <span v-if="canal.titleSuffix"> {{ canal.titleSuffix }}</span>
                </p>
              </div>
            </div>
            <div v-if="canal.body" class="prose prose-slate mt-4 max-w-none text-slate-700" v-html="canal.body" />
          </div>

          <!-- Galéria -->
          <div v-if="files.length" class="show-card">
            <h2 class="mb-3 text-base font-semibold text-slate-800">Galéria</h2>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
              <a v-for="f in files" :key="f.id" :href="f.url" target="_blank"
                class="block aspect-square overflow-hidden rounded-lg border border-slate-200">
                <img :src="f.thumbUrl ?? f.url" :alt="f.name"
                  class="h-full w-full object-cover transition-transform hover:scale-105" />
              </a>
            </div>
          </div>

          <!-- Miesta (venues) -->
          <div v-if="canal.venuesList.length" class="show-card">
            <h2 class="mb-3 text-base font-semibold text-slate-800">Miesta</h2>
            <ul class="grid gap-1.5">
              <li v-for="v in canal.venuesList" :key="v.id"
                class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                <span v-if="v.isOwner" class="shrink-0 text-xs font-semibold text-teal-600">[vlastník]</span>
                <RouterLink :to="`${prefix}/venues/${v.id}`"
                  class="flex-1 truncate text-sm font-medium text-slate-900 no-underline hover:text-blue-700">
                  {{ v.name }}
                </RouterLink>
                <RouterLink :to="`${prefix}/venues/${v.id}`" class="action-btn shrink-0">Detail</RouterLink>
              </li>
            </ul>
          </div>

          <!-- Eventy kanálu -->
          <div class="show-card">
            <div class="mb-3 flex items-center justify-between gap-2">
              <h2 class="text-base font-semibold text-slate-800">Eventy kanálu</h2>
              <RouterLink :to="`${prefix}/events`" class="text-xs text-blue-600 hover:underline">Všetky eventy →</RouterLink>
            </div>
            <p v-if="eventsLoading" class="text-sm text-slate-500">Načítavam…</p>
            <p v-else-if="!events.length" class="text-sm text-slate-400">Žiadne eventy.</p>
            <ul v-else class="grid gap-1.5">
              <li v-for="ev in events" :key="ev.id"
                class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                <span class="h-2 w-2 shrink-0 rounded-full"
                  :class="ev.status === 'published' ? 'bg-green-500' : ev.status === 'archived' ? 'bg-slate-400' : 'bg-amber-400'" />
                <RouterLink :to="`${prefix}/events/${ev.id}`"
                  class="flex-1 min-w-0 truncate text-sm font-medium text-slate-900 no-underline hover:text-blue-700">
                  {{ ev.name }}
                </RouterLink>
                <span v-if="ev.startAt" class="shrink-0 text-xs text-slate-500">{{ formatDate(ev.startAt) }}</span>
                <RouterLink :to="`${prefix}/events/${ev.id}/edit`" class="action-btn shrink-0">Upraviť</RouterLink>
              </li>
            </ul>
          </div>
        </div>

        <!-- Pravý stĺpec -->
        <aside class="grid gap-4 self-start">
          <dl class="show-card grid gap-3">
            <!-- Obec -->
            <div v-if="canal.municipality" class="detail-card">
              <dt>Obec</dt>
              <dd>{{ canal.municipality.name }}</dd>
            </div>

            <!-- Kontakt -->
            <div v-if="canal.phone" class="detail-card">
              <dt>Telefón</dt>
              <dd><a :href="`tel:${canal.phone}`" class="text-blue-700">{{ canal.phone }}</a></dd>
            </div>
            <div v-if="canal.email" class="detail-card">
              <dt>Email</dt>
              <dd><a :href="`mailto:${canal.email}`" class="text-blue-700">{{ canal.email }}</a></dd>
            </div>
            <div v-if="canal.website" class="detail-card">
              <dt>Web</dt>
              <dd><a :href="canal.website" target="_blank" class="break-all text-blue-700">{{ canal.website }}</a></dd>
            </div>

            <!-- Členovia -->
            <div v-if="canal.membersList.length" class="detail-card">
              <dt>Členovia</dt>
              <dd class="mt-1 grid gap-1">
                <span v-for="m in canal.membersList" :key="m.id"
                  class="flex items-center gap-1.5 text-sm text-slate-700">
                  <span v-if="m.isOwner" class="text-xs font-semibold text-teal-600">[vlastník]</span>
                  {{ m.name }}
                </span>
              </dd>
            </div>

            <!-- Meta -->
            <div class="detail-card">
              <dt>Publikované</dt>
              <dd>{{ canal.publishedAt ? formatDate(canal.publishedAt) : '—' }}</dd>
            </div>
            <div class="detail-card">
              <dt>Vytvorené</dt>
              <dd>{{ formatDate(canal.createdAt) }}</dd>
            </div>
            <div class="detail-card">
              <dt>Upravené</dt>
              <dd>{{ formatDate(canal.updatedAt) }}</dd>
            </div>
            <div v-if="canal.deletedAt" class="detail-card bg-red-50">
              <dt class="text-red-600">Zmazané</dt>
              <dd>{{ formatDate(canal.deletedAt) }}</dd>
            </div>
          </dl>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showCanal, listCanalEvents, type CanalEventItem } from '@/api/canals'
import { listFiles, type FileItem } from '@/api/files'
import type { CanalItem } from '@/types'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const indexRoute = computed(() => `${prefix.value}/canals`)
const editRoute = computed(() => `${prefix.value}/canals/${route.params.id}/edit`)

const canal = ref<CanalItem | null>(null)
const loading = ref(false)
const error = ref(false)
const files = ref<FileItem[]>([])
const events = ref<CanalEventItem[]>([])
const eventsLoading = ref(false)

function identityModeLabel(mode: string) {
  return { personal: 'Osobný', organization: 'Organizácia', pseudonymous: 'Pseudonymný' }[mode] ?? mode
}

function statusClass(status: string) {
  return {
    published: 'bg-green-100 text-green-800',
    draft: 'bg-amber-100 text-amber-800',
    archived: 'bg-slate-100 text-slate-600',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric', year: 'numeric' })
}

onMounted(async () => {
  const id = Number(route.params.id)
  loading.value = true
  try {
    canal.value = await showCanal(scope.value, id)
    document.title = canal.value.name

    eventsLoading.value = true
    const [filesRes, eventsRes] = await Promise.allSettled([
      listFiles({ fileable_type: 'canal', fileable_id: id }),
      listCanalEvents(scope.value, id),
    ])
    if (filesRes.status === 'fulfilled') files.value = filesRes.value.filter(f => !f.deletedAt)
    if (eventsRes.status === 'fulfilled') events.value = eventsRes.value
  } catch {
    error.value = true
  } finally {
    loading.value = false
    eventsLoading.value = false
  }
})
</script>
