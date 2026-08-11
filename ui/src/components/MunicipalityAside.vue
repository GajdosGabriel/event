<template>
  <div class="panel-card p-0 overflow-hidden">
    <div class="header">
      <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9a2 2 0 110-4 2 2 0 010 4z"/>
      </svg>
      {{ t('filters.regions.title') }}
    </div>

    <ul class="list">
      <li class="item-all">
        <RouterLink :to="basePath" class="link-all">
          <svg class="h-3 w-3 shrink-0 text-blue-500" viewBox="0 0 12 12" fill="currentColor">
            <path d="M10 3L5 8.5 2 5.5l-1 1 4 4 6-7z"/>
          </svg>
          {{ t('filters.regions.all') }}
        </RouterLink>
      </li>

      <template v-if="loading">
        <li v-for="n in 6" :key="n" class="item px-4 py-2">
          <span class="skeleton" style="width: 60%" />
          <span class="skeleton ml-2" style="width: 1.5rem" />
        </li>
      </template>

      <li v-for="group in groups" v-else :key="group.regionId" class="item">
        <button
          type="button"
          class="group-header"
          :class="{ 'group-header-open': isOpen(group.regionId) }"
          :aria-expanded="isOpen(group.regionId)"
          :aria-controls="`region-${group.regionId}`"
          @click="toggle(group.regionId)"
        >
          <svg
            class="chevron"
            :class="{ 'chevron-open': isOpen(group.regionId) }"
            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
          <span class="group-name">{{ group.regionName }}</span>
          <span class="count">{{ group.totalCount }}</span>
        </button>

        <ul v-show="isOpen(group.regionId)" :id="`region-${group.regionId}`" class="sublist">
          <li
            v-for="item in group.municipalities"
            :key="item.municipalityId"
            :class="{ 'item-active': active === item.municipalityId }"
          >
            <RouterLink :to="linkFor(item)" class="item-row">
              <span class="link">{{ item.municipalityName }}</span>
              <span class="count">{{ item.eventsCount }}</span>
            </RouterLink>
          </li>
        </ul>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import http from '@/api/index'
import { PUBLIC_EVENTS, publicMunicipalityPath } from '@/utils/publicUrl'
import { useI18n } from '@/i18n'

const { t } = useI18n()

const props = defineProps<{
  scope: 'dashboard' | 'admin' | 'public'
  resource: string
}>()

interface MunItem {
  municipalityId: number
  municipalityName: string
  /** Slug pre verejnú landing adresu; dashboard/admin ho nepoužívajú. */
  municipalitySlug: string | null
  eventsCount: number
  regionId: number
  regionName: string
}

interface RegionGroup {
  regionId: number
  regionName: string
  municipalities: MunItem[]
  totalCount: number
}

// Pseudo-kraj pre celoslovenské podujatia — drží sa navrchu zoznamu.
const NATIONWIDE_REGION_ID = 9

const route = useRoute()
const items = ref<MunItem[]>([])
const loading = ref(false)
const openRegions = ref<Set<number>>(new Set())

const basePath = computed(() =>
  props.scope === 'public' ? PUBLIC_EVENTS : `/${props.scope}/${props.resource}`
)

/**
 * Vo verejnom rozsahu je zvolená obec segmentom cesty
 * (`/podujatia/mesto/{slug}`), nie query parametrom — filter tak má vlastnú
 * indexovateľnú adresu. Dashboard a admin ostávajú na `?municipality={id}`,
 * ich zoznamy do vyhľadávača nepatria.
 */
const activeSlug = computed(() => (route.params.slug as string | undefined) ?? null)

const active = computed(() => {
  if (props.scope === 'public') {
    const bySlug = items.value.find((item) => item.municipalitySlug === activeSlug.value)
    return bySlug?.municipalityId ?? null
  }

  return route.query.municipality ? Number(route.query.municipality) : null
})

const groups = computed<RegionGroup[]>(() => {
  const byRegion = new Map<number, RegionGroup>()

  for (const item of items.value) {
    let group = byRegion.get(item.regionId)
    if (!group) {
      group = { regionId: item.regionId, regionName: item.regionName, municipalities: [], totalCount: 0 }
      byRegion.set(item.regionId, group)
    }
    group.municipalities.push(item)
    group.totalCount += item.eventsCount
  }

  return [...byRegion.values()].sort((a, b) => {
    if (a.regionId === NATIONWIDE_REGION_ID) return -1
    if (b.regionId === NATIONWIDE_REGION_ID) return 1
    return a.regionName.localeCompare(b.regionName, 'sk')
  })
})

function isOpen(regionId: number) {
  return openRegions.value.has(regionId)
}

function toggle(regionId: number) {
  const next = new Set(openRegions.value)
  if (next.has(regionId)) next.delete(regionId)
  else next.add(regionId)
  openRegions.value = next
}

/** Rozbalí kraj, v ktorom je práve filtrovaná obec — ručne otvorené necháva otvorené. */
function openActiveRegion() {
  if (active.value === null) return
  const group = groups.value.find(g => g.municipalities.some(m => m.municipalityId === active.value))
  if (group && !openRegions.value.has(group.regionId)) {
    openRegions.value = new Set(openRegions.value).add(group.regionId)
  }
}

function linkFor(item: MunItem) {
  if (active.value === item.municipalityId) return basePath.value

  if (props.scope === 'public' && item.municipalitySlug) {
    return publicMunicipalityPath(item.municipalitySlug)
  }

  return { path: basePath.value, query: { municipality: item.municipalityId } }
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
      municipalitySlug: (r['municipality_slug'] as string) ?? null,
      eventsCount: r['events_count'] as number,
      regionId: Number(r['region_id'] ?? 0),
      regionName: (r['region_name'] as string) ?? 'Ostatné',
    }))
    openActiveRegion()
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

watch(active, openActiveRegion)
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

.group-header {
  @apply flex w-full cursor-pointer items-center gap-1.5 border-0 bg-transparent px-4 py-2 text-left hover:bg-slate-50;
}
.group-header-open { @apply bg-slate-50/60; }
.group-name { @apply min-w-0 flex-1 truncate text-sm font-medium text-slate-700; }
.group-header-open .group-name { @apply text-slate-900; }

.chevron { @apply h-3 w-3 shrink-0 text-slate-400 transition-transform duration-150; }
.chevron-open { @apply rotate-90 text-slate-600; }

.sublist { @apply m-0 list-none border-t border-dashed border-slate-100 p-0; }

.item-row {
  @apply flex w-full items-center justify-between py-1.5 pr-4 pl-9 no-underline hover:bg-slate-50;
}
.item-active .item-row { @apply hover:bg-blue-50; }

.link { @apply min-w-0 truncate text-sm text-slate-700; }
.count { @apply ml-2 shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-500; }

.skeleton { @apply inline-block h-3 animate-pulse rounded-md bg-slate-100; }
</style>
