<template>
  <!-- Verejný filter podľa obsahových štítkov.
       Stav drží URL (?tags=koncert,folklor), nie komponent — rovnako ako obecný
       facet v MunicipalityAside. Odkaz sa tak dá zdieľať aj založiť. -->
  <div v-if="groups.length || count" class="rounded-xl border border-slate-200 bg-white p-3">
    <div v-if="count || active.length" class="flex flex-wrap items-center justify-between gap-2">
      <!-- Počet výsledkov stál pod kartou vo vlastnom riadku; vedľa štítkov
           povie to isté a ušetrí výšku, ktorej je na telefóne najmenej. -->
      <p v-if="count" class="text-sm text-slate-500" role="status" aria-live="polite">{{ count }}</p>
      <span v-else></span>

      <!-- Prepínač je posledný, aby ikona so slovom „Štítky" stála úplne
           vpravo; zvolené štítky sa vypisujú pred ním. -->
      <div class="flex min-w-0 flex-wrap items-center justify-end gap-2">
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
        >{{ t('filters.tags.clearAll') }}</RouterLink>
      </div>
    </div>

    <!-- Rovnaké usporiadanie ako bočný panel na hlascirkvi.sk: nadpis
         „Čo hľadáte" a pod ním skupiny, každá zbalená zvlášť. Otvorená ostáva
         len tá, v ktorej je aktívny filter — celý zoznam by zabral pol obrazovky. -->
    <template v-if="groups.length">
      <p
        class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
        :class="{ 'mt-3 border-t border-slate-100 pt-3': count || active.length }"
      >{{ t('filters.tags.lookingFor') }}</p>

      <div class="mt-1 divide-y divide-slate-100">
        <details
          v-for="group in groups"
          :key="group.group"
          class="group py-0.5"
          :open="groupIsActive(group)"
        >
          <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-md px-2 py-1.5 text-xs tracking-wider text-slate-500 uppercase transition-colors hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
            <span>{{ group.label }}</span>
            <svg
              class="h-3 w-3 shrink-0 text-slate-400 transition-transform group-open:rotate-180"
              fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
            >
              <path d="M6 9l6 6 6-6" stroke-linecap="round" />
            </svg>
          </summary>
          <div class="flex flex-wrap gap-1.5 px-2 pt-1.5 pb-2">
            <RouterLink
              v-for="tag in group.tags"
              :key="tag.slug"
              :to="linkFor(tag.slug)"
              class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs no-underline transition-colors"
              :class="isActive(tag.slug)
                ? 'border-slate-900 bg-slate-900 font-medium text-white'
                : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-400 hover:bg-white'"
            >
              <span v-if="tag.emoji">{{ tag.emoji }}</span>
              {{ tag.name }}
              <span :class="isActive(tag.slug) ? 'text-slate-300' : 'text-slate-400'">{{ tag.eventsCount }}</span>
            </RouterLink>
          </div>
        </details>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, type LocationQueryRaw } from 'vue-router'
import { indexTags } from '@/api/tags'
import type { TagGroupItem } from '@/types'
import { PUBLIC_EVENTS, publicTagPath } from '@/utils/publicUrl'
import { useI18n } from '@/i18n'

/** Popis počtu výsledkov zo zoznamu — karta ho len zobrazí, nepočíta ho. */
defineProps<{ count?: string | null }>()

const { t } = useI18n()
const route = useRoute()

/**
 * Kam vedie „bez štítkov". Na landing stránke obce zostávame na nej — štítok je
 * tam zúženie mesta, nie odchod z neho.
 */
const basePath = computed(() =>
  route.name === 'events-public-municipality' ? route.path : PUBLIC_EVENTS,
)

const groups = ref<TagGroupItem[]>([])

/**
 * Aktívne slugy z URL. Jeden štítok má vlastnú landing adresu
 * (`/akcie/tema/{slug}`), kombinácia viacerých ostáva v `?tags=` —
 * kartézsky súčin štítkov by boli tisíce takmer prázdnych stránok.
 */
const active = computed<string[]>(() => {
  if (route.name === 'events-public-tag') {
    return [String(route.params.slug)]
  }

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

function groupIsActive(group: TagGroupItem) {
  return group.tags.some((tag) => isActive(tag.slug))
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
  delete query.tags

  if (next.length === 0) {
    return { path: basePath.value, query }
  }

  // Vlastnú adresu dostane len samotný štítok na neobmedzenom výpise. Vnútri
  // obce a pri kombinácii viacerých štítkov ostáva filter v `?tags=` — inak by
  // vznikli tisíce takmer prázdnych priesečníkových stránok.
  if (next.length === 1 && route.name !== 'events-public-municipality') {
    return { path: publicTagPath(next[0]!), query }
  }

  return { path: basePath.value, query: { ...query, tags: next.join(',') } }
}

onMounted(async () => {
  try {
    // Štítky bez podujatí by len zavádzali — filter by vrátil prázdno.
    groups.value = (await indexTags({ onlyUsed: true })).filter((group) => group.tags.length > 0)
  } catch {
    groups.value = []
  }
})
</script>
