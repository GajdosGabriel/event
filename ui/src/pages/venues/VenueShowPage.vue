<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <p v-if="loading" class="text-slate-600">Načítavam…</p>
    <div v-else-if="error" class="show-not-found"><h1>Miesto nenájdené</h1><RouterLink :to="indexRoute">← Späť</RouterLink></div>
    <template v-else-if="venue">
      <div class="mb-4 flex flex-wrap gap-2">
        <RouterLink :to="indexRoute" class="inline-flex rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 no-underline hover:bg-slate-50">← Späť</RouterLink>
        <RouterLink v-if="venue.permissions.update" :to="editRoute" class="inline-flex rounded-md border border-blue-200 px-3 py-1.5 text-sm text-blue-700 no-underline hover:bg-blue-50">Upraviť</RouterLink>
      </div>
      <div class="show-shell">
        <div class="show-card">
          <h1 class="mb-1 text-3xl text-slate-900">{{ venue.name }}</h1>
          <p class="mb-3 font-semibold text-slate-600">{{ venue.status }}</p>
          <div v-if="venue.body" class="text-slate-700" v-html="venue.body" />
        </div>
        <aside>
          <dl class="show-card grid gap-3">
            <div v-if="venue.street" class="detail-card"><dt>Adresa</dt><dd>{{ venue.street }}, {{ venue.postcode }}</dd></div>
            <div v-if="venue.email" class="detail-card"><dt>Email</dt><dd>{{ venue.email }}</dd></div>
            <div v-if="venue.phone" class="detail-card"><dt>Telefón</dt><dd>{{ venue.phone }}</dd></div>
            <div v-if="venue.website" class="detail-card"><dt>Web</dt><dd><a :href="venue.website" target="_blank" class="text-blue-700">{{ venue.website }}</a></dd></div>
            <div v-if="venue.capacity" class="detail-card"><dt>Kapacita</dt><dd>{{ venue.capacity }}</dd></div>
          </dl>
        </aside>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { showVenue } from '@/api/venues'
import type { VenueItem } from '@/types'

const props = defineProps<{ scope?: 'dashboard' | 'admin' }>()
const route = useRoute()
const scope = computed(() => props.scope ?? (route.path.startsWith('/admin') ? 'admin' : 'dashboard'))
const prefix = computed(() => scope.value === 'admin' ? '/admin' : '/dashboard')
const indexRoute = computed(() => `${prefix.value}/venues`)
const editRoute = computed(() => `${prefix.value}/venues/${route.params.id}/edit`)

const venue = ref<VenueItem | null>(null)
const loading = ref(false)
const error = ref(false)

onMounted(async () => {
  loading.value = true
  try { venue.value = await showVenue(scope.value, Number(route.params.id)) }
  catch { error.value = true }
  finally { loading.value = false }
})
</script>
