<template>
  <div class="panel-card p-0 overflow-hidden">
    <div class="header">
      <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/>
      </svg>
      Okresy Slovenska
    </div>

    <ul class="list">
      <li class="item-all">
        <RouterLink :to="basePath" class="link-all">
          <svg class="h-3 w-3 shrink-0 text-blue-500" viewBox="0 0 12 12" fill="currentColor">
            <path d="M10 3L5 8.5 2 5.5l-1 1 4 4 6-7z"/>
          </svg>
          Všetky regióny
        </RouterLink>
      </li>

      <template v-if="loading">
        <li v-for="n in 6" :key="n" class="item">
          <span class="skeleton" style="width: 70%" />
          <span class="skeleton" style="width: 1.5rem" />
        </li>
      </template>

      <li
        v-for="item in items"
        :key="item.municipalityId"
        class="item"
        :class="{ 'item-active': active === item.municipalityId }"
      >
        <RouterLink :to="linkFor(item.municipalityId)" class="item-row">
          <span class="link">{{ item.municipalityName }}</span>
          <span class="count">{{ item.eventsCount }}</span>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import http from '@/api/index'

const props = defineProps<{
  scope: 'dashboard' | 'admin' | 'public'
  resource: string
}>()

interface MunItem {
  municipalityId: number
  municipalityName: string
  eventsCount: number
}

const route = useRoute()
const items = ref<MunItem[]>([])
const loading = ref(false)

const basePath = computed(() =>
  props.scope === 'public' ? '/' : `/${props.scope}/${props.resource}`
)
const active = computed(() => route.query.municipality ? Number(route.query.municipality) : null)

function linkFor(id: number) {
  if (active.value === id) return basePath.value
  return { path: basePath.value, query: { municipality: id } }
}

async function load() {
  loading.value = true
  try {
    const apiPath = props.scope === 'public'
      ? `/${props.resource}/municipalities-overview`
      : `/${props.scope}/${props.resource}/municipalities-overview`
    const { data } = await http.get(apiPath)
    items.value = ((data.data ?? data) as Record<string, unknown>[]).map(r => ({
      municipalityId: r['municipality_id'] as number,
      municipalityName: (r['municipality_name'] ?? r['municipality_shortname']) as string,
      eventsCount: r['events_count'] as number,
    }))
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

watch(() => [props.scope, props.resource], load)
onMounted(load)
</script>

<style scoped>
@reference "tailwindcss";

.header {
  @apply flex items-center gap-2 border-b border-slate-100 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500;
}

.list { @apply m-0 list-none divide-y divide-dashed divide-slate-100 p-0; }

.item-all { @apply px-4 py-2; }
.link-all {
  @apply flex items-center gap-1.5 text-sm font-medium text-blue-600 no-underline hover:text-blue-800;
}

.item { }
.item-active { @apply bg-blue-50; }
.item-active .link { @apply font-semibold text-blue-700; }
.item-active .count { @apply text-blue-500; }

.item-row {
  @apply flex w-full items-center justify-between px-4 py-1.5 no-underline hover:bg-slate-50;
}
.item-active .item-row { @apply hover:bg-blue-50; }

.link { @apply min-w-0 truncate text-sm text-slate-700; }
.count { @apply ml-2 shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-500; }

.skeleton { @apply inline-block h-3 animate-pulse rounded-md bg-slate-100; }
</style>
