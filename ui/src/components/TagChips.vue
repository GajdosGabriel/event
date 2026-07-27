<template>
  <!-- Verejný filter podľa obsahových štítkov.
       Stav drží URL (?tags=koncert,folklor), nie komponent — rovnako ako obecný
       facet v MunicipalityAside. Odkaz sa tak dá zdieľať aj založiť. -->
  <div v-if="groups.length" class="rounded-xl border border-slate-200 bg-white p-3">
    <div class="flex flex-wrap items-center gap-2">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-sm text-slate-600 transition-colors hover:bg-slate-50"
        @click="expanded = !expanded"
      >
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M3 4h18M6 12h12M10 20h4" stroke-linecap="round" />
        </svg>
        Štítky
        <span
          v-if="active.length"
          class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-slate-900 px-1 text-[0.65rem] font-medium text-white"
        >{{ active.length }}</span>
        <svg
          class="h-3 w-3 shrink-0 transition-transform"
          :class="{ 'rotate-180': expanded }"
          fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
        >
          <path d="M6 9l6 6 6-6" stroke-linecap="round" />
        </svg>
      </button>

      <!-- Zvolené štítky sú vidno aj keď je panel zbalený, inak by používateľ
           nevedel, prečo je výsledkov málo. -->
      <RouterLink
        v-for="tag in activeTags"
        :key="tag.slug"
        :to="linkFor(tag.slug)"
        class="inline-flex items-center gap-1 rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white no-underline transition-opacity hover:opacity-80"
      >
        <span v-if="tag.emoji">{{ tag.emoji }}</span>
        {{ tag.name }}
        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
          <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
        </svg>
      </RouterLink>

      <RouterLink
        v-if="active.length"
        :to="basePath"
        class="text-xs text-slate-500 no-underline hover:text-slate-800 hover:underline"
      >Zrušiť všetky</RouterLink>
    </div>

    <div v-if="expanded" class="mt-3 space-y-3 border-t border-slate-100 pt-3">
      <div v-for="group in groups" :key="group.group">
        <p class="mb-1.5 text-xs font-medium text-slate-400 uppercase">{{ group.label }}</p>
        <div class="flex flex-wrap gap-1.5">
          <RouterLink
            v-for="tag in group.tags"
            :key="tag.slug"
            :to="linkFor(tag.slug)"
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs no-underline transition-colors"
            :class="isActive(tag.slug)
              ? 'bg-slate-900 font-medium text-white'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          >
            <span v-if="tag.emoji">{{ tag.emoji }}</span>
            {{ tag.name }}
            <span :class="isActive(tag.slug) ? 'text-slate-300' : 'text-slate-400'">{{ tag.eventsCount }}</span>
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, type LocationQueryRaw } from 'vue-router'
import { indexTags } from '@/api/tags'
import type { TagGroupItem } from '@/types'

const route = useRoute()
const basePath = '/'

const groups = ref<TagGroupItem[]>([])
const expanded = ref(false)

/** Aktívne slugy z URL. */
const active = computed<string[]>(() => {
  const raw = route.query.tags
  if (!raw) return []
  return String(raw).split(',').map((s) => s.trim()).filter(Boolean)
})

const activeTags = computed(() =>
  groups.value
    .flatMap((group) => group.tags)
    .filter((tag) => active.value.includes(tag.slug)),
)

function isActive(slug: string) {
  return active.value.includes(slug)
}

/**
 * Klik na štítok ho pridá alebo odoberie. Ostatné parametre (obec) zostávajú —
 * filtre sa kombinujú, nie prepisujú.
 */
function linkFor(slug: string) {
  const next = isActive(slug)
    ? active.value.filter((s) => s !== slug)
    : [...active.value, slug]

  const query: LocationQueryRaw = { ...route.query }
  delete query.page

  if (next.length) {
    query.tags = next.join(',')
  } else {
    delete query.tags
  }

  return { path: basePath, query }
}

onMounted(async () => {
  try {
    // Štítky bez podujatí by len zavádzali — filter by vrátil prázdno.
    groups.value = (await indexTags({ onlyUsed: true })).filter((group) => group.tags.length > 0)
  } catch {
    groups.value = []
  }

  // Keď už filter beží z URL, panel otvor — používateľ vidí, kde je.
  expanded.value = active.value.length > 0
})
</script>
