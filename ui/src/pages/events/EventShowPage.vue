<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <p v-if="loading" class="text-slate-600">Načítavam…</p>

    <div v-else-if="error" class="show-not-found">
      <h1 class="mb-2 text-xl font-semibold">Event nenájdený</h1>
      <RouterLink :to="indexRoute">← Späť na zoznam</RouterLink>
    </div>

    <template v-else-if="event">
      <div class="mb-4 grid gap-2">
        <div class="flex flex-wrap gap-2">
          <RouterLink :to="indexRoute" class="inline-flex rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 no-underline hover:bg-slate-50">← Späť</RouterLink>
          <RouterLink v-if="event.permissions.update" :to="editRoute" class="inline-flex rounded-md border border-blue-200 px-3 py-1.5 text-sm text-blue-700 no-underline hover:bg-blue-50">Upraviť</RouterLink>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">{{ event.name }}</h1>
        <p class="text-slate-600">{{ event.dateRangeLabel }}</p>
      </div>

      <div class="show-shell">
        <div class="space-y-4">
          <div class="show-card">
            <div v-if="event.body" class="prose text-slate-700" v-html="event.body" />
            <p v-else class="text-slate-400 text-sm">Bez popisu.</p>
          </div>

          <div class="show-card">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Fotografie</h2>
            <ImageGallery fileable-type="event" :fileable-id="Number(route.params.id)" />
          </div>
        </div>

        <aside class="grid gap-3">
          <dl class="show-card grid gap-3">
            <div class="detail-card">
              <dt>Stav</dt>
              <dd>{{ event.status }}</dd>
            </div>
            <div v-if="event.canalName" class="detail-card">
              <dt>Kanál</dt>
              <dd>{{ event.canalName }}</dd>
            </div>
            <div v-if="event.locationName" class="detail-card">
              <dt>Miesto</dt>
              <dd>{{ event.locationName }}</dd>
            </div>
            <div v-if="event.website" class="detail-card">
              <dt>Web</dt>
              <dd><a :href="event.website" target="_blank" class="text-blue-700">{{ event.website }}</a></dd>
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
import { showEvent } from '@/api/events'
import type { EventItem } from '@/types'
import ImageGallery from '@/components/ImageGallery.vue'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()

const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const indexRoute = computed(() => `${prefix.value}/events`)
const editRoute = computed(() => `${prefix.value}/events/${route.params.id}/edit`)

const event = ref<EventItem | null>(null)
const loading = ref(false)
const error = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    event.value = await showEvent(scope.value, Number(route.params.id))
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>
