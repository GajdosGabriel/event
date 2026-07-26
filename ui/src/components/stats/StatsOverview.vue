<template>
  <div class="grid gap-4">
    <p v-if="loading" class="index-status">Načítavam štatistiku…</p>
    <p v-else-if="error" class="index-status index-status-error">{{ error }}</p>

    <template v-else-if="stats">
      <!-- Aktuálny stav: čo práve beží, nie čo pribudlo. -->
      <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile label="Aktívne podujatia" :value="totals.events.active" :to="link('events')" />
        <StatTile label="Práve prebieha" :value="totals.events.running" :to="link('events')" />
        <StatTile label="Dnes v programe" :value="totals.events.today" :to="link('events')" />
        <StatTile label="Najbližších 7 dní" :value="totals.events.next7d" :to="link('events')" />
      </section>

      <!-- Vyžaduje pozornosť -->
      <section v-if="stats.attention.length" class="panel-card">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">Vyžaduje pozornosť</h2>
        <ul class="index-list">
          <li v-for="item in stats.attention" :key="item.key" class="attention-row">
            <span class="attention-dot" :style="{ backgroundColor: SEVERITY[item.severity].color }" aria-hidden="true" />
            <span class="min-w-0">
              <component
                :is="item.link ? 'RouterLink' : 'span'"
                :to="item.link ? link(item.link) : undefined"
                class="block font-semibold text-slate-900 no-underline hover:underline"
              >{{ item.label }}</component>
              <span class="block text-xs text-slate-500">{{ item.hint }}</span>
            </span>
            <span class="attention-count" :style="{ color: SEVERITY[item.severity].color }">
              {{ fmtCount(item.count) }}
            </span>
            <span class="attention-tag">{{ SEVERITY[item.severity].label }}</span>
          </li>
        </ul>
      </section>

      <!-- Prírastky za obdobie -->
      <section class="grid gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Prírastky</h2>
            <p class="text-sm text-slate-500">{{ periodSubtitle }}</p>
          </div>

          <div class="flex flex-wrap gap-1" role="group" aria-label="Obdobie">
            <button
              v-for="period in stats.periods"
              :key="period.key"
              type="button"
              class="chart-tab"
              :class="{ 'chart-tab-active': period.key === periodKey }"
              :aria-pressed="period.key === periodKey"
              @click="periodKey = period.key"
            >
              {{ period.label }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <StatTile
            v-for="(metric, key) in activePeriod.metrics"
            :key="key"
            :label="metric.label"
            :value="metric.value"
            :format="metric.format"
            :previous="metric.previous"
            :change="metric.change"
            :spark="sparkFor(key)"
          />
        </div>
      </section>

      <ActivityChart :trend="stats.trend" />

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Predaj a dochádzka -->
        <section class="panel-card grid gap-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Vstupenky</h2>
            <p class="text-sm text-slate-500">
              {{ fmtCount(ticketing.orders.total) }} objednávok · {{ fmtCount(ticketing.seats.valid) }} platných vstupeniek
            </p>
          </div>

          <MeterBar
            label="Obsadenosť nadchádzajúcich podujatí"
            :part="ticketing.capacity.sold"
            :whole="ticketing.capacity.seats"
            :rate="ticketing.capacity.rate"
            unit="miest"
            :note="ticketing.capacity.unlimitedTypes ? `${ticketing.capacity.unlimitedTypes} typov bez limitu sa neráta` : null"
          />

          <MeterBar
            label="Príchody na už prebehnuté podujatia"
            :part="ticketing.attendance.arrived"
            :whole="ticketing.attendance.expected"
            :rate="ticketing.attendance.rate"
            unit="vstupeniek"
            color="#1baf7a"
          />

          <dl class="grid grid-cols-2 gap-2 text-sm">
            <div class="detail-card">
              <dt>Zaplatené</dt>
              <dd>{{ fmtMoney(ticketing.orders.revenuePaid) }}</dd>
            </div>
            <div class="detail-card">
              <dt>Čaká na platbu</dt>
              <dd>{{ fmtMoney(ticketing.orders.revenueAwaiting) }}</dd>
            </div>
            <div class="detail-card">
              <dt>Čaká na potvrdenie</dt>
              <dd>{{ fmtCount(ticketing.seats.awaitingConfirmation) }}</dd>
            </div>
            <div class="detail-card">
              <dt>Zrušené vstupenky</dt>
              <dd>{{ fmtCount(ticketing.seats.cancelled) }}</dd>
            </div>
          </dl>
        </section>

        <!-- Skladba obsahu -->
        <section class="panel-card grid gap-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Skladba podujatí</h2>
            <p class="text-sm text-slate-500">
              {{ fmtCount(totals.events.total) }} celkovo · {{ fmtCount(totals.venues.total) }} miest · {{ fmtCount(totals.canals.total) }} kanálov
            </p>
          </div>

          <div v-if="statusSegments.length">
            <div class="flex h-3 gap-0.5 overflow-hidden rounded-full">
              <span
                v-for="segment in statusSegments"
                :key="segment.key"
                :style="{ width: `${segment.share}%`, backgroundColor: segment.color }"
                :title="`${segment.label}: ${segment.count}`"
              />
            </div>
            <!-- Každý segment je priamo popísaný — farba sama by pri nízkom
                 kontraste voči bielej nestačila. -->
            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
              <li v-for="segment in statusSegments" :key="segment.key" class="flex items-center gap-1.5">
                <span class="size-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: segment.color }" aria-hidden="true" />
                <span class="text-slate-600">{{ segment.label }}</span>
                <strong class="tabular-nums text-slate-900">{{ fmtCount(segment.count) }}</strong>
              </li>
            </ul>
          </div>

          <MeterBar
            v-if="stats.sources.own + stats.sources.imported > 0"
            label="Podiel importovaného obsahu"
            :part="stats.sources.imported"
            :whole="stats.sources.own + stats.sources.imported"
            :rate="stats.sources.importedRate"
            unit="podujatí"
            :note="`${fmtCount(stats.sources.own)} vlastných`"
            color="#eda100"
          />

          <div v-if="stats.topCanals.length">
            <p class="mb-1.5 text-sm font-semibold text-slate-700">Najaktívnejšie kanály</p>
            <ul class="index-list">
              <li v-for="canal in stats.topCanals" :key="canal.id" class="flex items-baseline justify-between gap-2 text-sm">
                <RouterLink :to="link(`canals/${canal.id}`)" class="truncate text-slate-800 no-underline hover:underline">
                  {{ canal.name }}
                </RouterLink>
                <span class="shrink-0 tabular-nums text-slate-500">
                  <strong class="text-slate-900">{{ fmtCount(canal.eventsRecent) }}</strong> / {{ fmtCount(canal.eventsTotal) }}
                </span>
              </li>
            </ul>
            <p class="mt-1 text-xs text-slate-400">za 30 dní / celkovo</p>
          </div>
        </section>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Najbližší program -->
        <section class="panel-card">
          <h2 class="mb-3 text-lg font-semibold text-slate-900">Najbližší program</h2>
          <ul v-if="stats.upcoming.length" class="index-list">
            <li v-for="event in stats.upcoming" :key="event.id" class="flex items-baseline justify-between gap-3 text-sm">
              <span class="min-w-0">
                <RouterLink :to="link(`events/${event.id}`)" class="block truncate font-medium text-slate-900 no-underline hover:underline">
                  {{ event.name }}
                </RouterLink>
                <span class="block truncate text-xs text-slate-500">{{ event.venue ?? 'bez miesta' }}</span>
              </span>
              <span class="shrink-0 text-right text-xs">
                <span class="block whitespace-nowrap font-semibold text-slate-700">{{ startLabel(event.startAt) }}</span>
                <span v-if="event.seats" class="block text-slate-500">{{ fmtCount(event.seats) }} vstupeniek</span>
              </span>
            </li>
          </ul>
          <p v-else class="text-sm text-slate-500">Žiadne naplánované podujatia.</p>
        </section>

        <!-- Najväčší záujem -->
        <section class="panel-card">
          <h2 class="mb-3 text-lg font-semibold text-slate-900">Najväčší záujem</h2>
          <ul v-if="stats.topEvents.length" class="index-list">
            <li v-for="event in stats.topEvents" :key="event.id" class="text-sm">
              <div class="flex items-baseline justify-between gap-3">
                <RouterLink :to="link(`events/${event.id}`)" class="truncate font-medium text-slate-900 no-underline hover:underline">
                  {{ event.name }}
                </RouterLink>
                <span class="shrink-0 tabular-nums text-slate-700">
                  {{ fmtCount(event.seats) }}<template v-if="event.capacity"> / {{ fmtCount(event.capacity) }}</template>
                </span>
              </div>
              <div v-if="event.rate !== null" class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-[#2a78d6]" :style="{ width: `${Math.min(100, event.rate)}%` }" />
              </div>
            </li>
          </ul>
          <p v-else class="text-sm text-slate-500">Zatiaľ nikto nie je prihlásený na nadchádzajúce podujatia.</p>
        </section>
      </div>

      <!-- Používatelia (len admin) -->
      <section v-if="stats.users" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile label="Používatelia" :value="stats.users.total" :to="link('users')" />
        <StatTile label="Overené účty" :value="stats.users.verified" :to="link('users')" />
        <StatTile label="Aktívni za 30 dní" :value="stats.users.active30d" :to="link('users')" />
        <StatTile label="Blokované účty" :value="stats.users.blocked" :to="link('users')" invert />
      </section>

      <p class="text-xs text-slate-400">
        Aktualizované {{ generatedLabel }} ·
        <button type="button" class="underline hover:text-slate-600" @click="load">obnoviť</button>
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import ActivityChart from './ActivityChart.vue'
import MeterBar from './MeterBar.vue'
import StatTile from './StatTile.vue'
import { fetchOverviewStats } from '@/api/stats'
import type { AttentionSeverity, ModelStatus, StatsOverview, StatsPeriodKey, StatsScope } from '@/types'
import { fmtCount, fmtMoney } from '@/utils/statsFormat'

const props = defineProps<{ scope: StatsScope }>()

/** Stavy majú význam, preto nesú sémantickú farbu — nie poradový odtieň. */
const STATUS_COLORS: Record<ModelStatus, string> = {
  published: '#1baf7a',
  scheduled: '#2a78d6',
  draft: '#eda100',
  pending_review: '#4a3aa7',
  rejected: '#e34948',
  blocked: '#e34948',
  archived: '#c3c2b7',
}

const SEVERITY: Record<AttentionSeverity, { color: string; label: string }> = {
  info: { color: '#2a78d6', label: 'info' },
  warning: { color: '#fab219', label: 'sledovať' },
  serious: { color: '#ec835a', label: 'doriešiť' },
  critical: { color: '#d03b3b', label: 'súrne' },
}

/** Ktorá séria trendu patrí ku ktorej metrike prehľadu. */
const SPARK_SOURCE: Record<string, keyof StatsOverview['trend'][number]> = {
  events: 'events',
  tickets: 'tickets',
  admissions: 'admissions',
  checkins: 'checkins',
}

const stats = ref<StatsOverview | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const periodKey = ref<StatsPeriodKey>('week')

onMounted(load)

async function load() {
  loading.value = true
  error.value = null
  try {
    stats.value = await fetchOverviewStats(props.scope)
  } catch {
    error.value = 'Štatistiku sa nepodarilo načítať.'
  } finally {
    loading.value = false
  }
}

const totals = computed(() => stats.value!.totals)
const ticketing = computed(() => stats.value!.ticketing)

const activePeriod = computed(() =>
  stats.value!.periods.find(p => p.key === periodKey.value) ?? stats.value!.periods[0],
)

const periodSubtitle = computed(() =>
  activePeriod.value.from ? 'Porovnanie s rovnako dlhým predchádzajúcim obdobím.' : 'Súhrn od začiatku.',
)

/**
 * Mikro-krivku dopĺňame len tam, kde ju trend pokrýva. Pri období „Celkovo"
 * by 30-dňová krivka pod celkovým číslom klamala, preto ju vynechávame.
 */
function sparkFor(key: string): number[] {
  const source = SPARK_SOURCE[key]
  if (!source || !stats.value || periodKey.value === 'all') return []
  return stats.value.trend.map(day => day[source] as number)
}

const statusSegments = computed(() => {
  const rows = stats.value?.statuses ?? []
  const total = rows.reduce((sum, row) => sum + row.count, 0)
  if (total === 0) return []

  return rows.map(row => ({
    ...row,
    share: (row.count / total) * 100,
    color: STATUS_COLORS[row.key] ?? '#898781',
  }))
})

const generatedLabel = computed(() => {
  const at = stats.value?.generatedAt
  return at ? new Date(at).toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' }) : '—'
})

/** Rovnaké stránky žijú pod /dashboard aj /admin — cieľ sa líši len prefixom. */
function link(path: string): string {
  return `/${props.scope}/${path}`
}

function startLabel(startAt: string | null): string {
  if (!startAt) return '—'
  const date = new Date(startAt)
  const today = new Date()
  const sameDay = date.toDateString() === today.toDateString()

  return sameDay
    ? `dnes ${date.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })}`
    : date.toLocaleDateString('sk-SK', { day: 'numeric', month: 'numeric' })
      + ` ${date.toLocaleTimeString('sk-SK', { hour: '2-digit', minute: '2-digit' })}`
}
</script>

<style scoped>
@reference "tailwindcss";

.chart-tab {
  @apply cursor-pointer rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:bg-slate-50;
}
.chart-tab-active { @apply border-slate-900 bg-slate-900 text-white hover:bg-slate-900; }

.attention-row { @apply flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2; }
.attention-dot { @apply size-2.5 shrink-0 rounded-full; }
.attention-count { @apply ml-auto shrink-0 text-lg font-bold tabular-nums; }
.attention-tag { @apply hidden shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[0.68rem] font-semibold uppercase tracking-wide text-slate-500 sm:inline; }
</style>
