<template>
  <div class="mx-auto my-5 w-full max-w-[1320px] px-4">
    <p v-if="loading" class="text-slate-600">Načítavam…</p>

    <div v-else-if="error" class="show-not-found">
      <h1 class="mb-2 text-xl font-semibold">Event nenájdený</h1>
      <RouterLink to="/">← Späť</RouterLink>
    </div>

    <template v-else-if="event">
      <div class="show-shell">
        <div class="show-card">
          <RouterLink to="/" class="text-sm text-blue-700 no-underline">← Späť</RouterLink>
          <h1 class="mt-2 mb-1 text-3xl text-slate-900">{{ event.name }}</h1>
          <p class="mb-4 font-semibold text-slate-600">{{ event.dateRangeLabel }}</p>
          <img v-if="event.imageUrl" :src="event.imageUrl" :alt="event.name" class="mb-4 w-full rounded-xl object-cover max-h-72" />
          <div v-if="event.body" class="text-slate-700" v-html="event.body" />
        </div>

        <aside class="grid gap-3">
          <dl class="show-card grid gap-3">
            <div v-if="event.canalName" class="detail-card">
              <dt>Organizátor</dt>
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
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import http from '@/api/index'
import type { EventItem } from '@/types'

const route = useRoute()
const event = ref<EventItem | null>(null)
const loading = ref(false)
const error = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await http.get(`/events/${route.params.id}`)
    event.value = (data.data ?? data) as EventItem
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>
