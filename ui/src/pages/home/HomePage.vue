<template>
  <div class="mx-auto w-full max-w-[1320px] px-4 pt-6 pb-8">
    <div class="mb-4">
      <h1 class="mb-1 text-2xl text-slate-900">Eventy</h1>
      <p class="text-slate-500">Prehľad verejných podujatí.</p>
    </div>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
      <div class="min-w-0">
        <p v-if="loading" class="text-slate-600">Načítavam…</p>
        <p v-else-if="error" class="text-red-600">{{ error }}</p>
        <div v-else class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-3 md:grid-cols-3">
          <EventCard v-for="event in events" :key="event.id" :event="event" />
          <p v-if="events.length === 0" class="text-slate-500 col-span-full p-2">Žiadne eventy.</p>
        </div>
        <AppPaginator :current-page="page" :last-page="lastPage" @change="loadPage" />
      </div>

      <aside class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="mb-2 text-base text-slate-900">O portáli</h2>
        <p class="text-slate-600">Portál eventov a podujatí pre vaše mesto.</p>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { indexEvents } from '@/api/events'
import type { EventItem } from '@/types'
import EventCard from '@/components/EventCard.vue'
import AppPaginator from '@/components/AppPaginator.vue'

const events = ref<EventItem[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const lastPage = ref(1)

async function loadPage(p = 1) {
  loading.value = true
  error.value = null
  try {
    const res = await indexEvents('public', { page: p })
    events.value = res.data
    page.value = res.meta.current_page
    lastPage.value = res.meta.last_page
  } catch {
    error.value = 'Nepodarilo sa načítať eventy.'
  } finally {
    loading.value = false
  }
}

onMounted(() => loadPage())
</script>
