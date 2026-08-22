<template>
  <div class="grid gap-4">
    <p v-if="loading" class="index-status">{{ t('stats.loading') }}</p>
    <p v-else-if="error" class="index-status index-status-error">{{ error }}</p>

    <template v-else-if="stats">
      <!-- Aktuálny stav: čo práve beží, nie čo pribudlo. -->
      <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile
          :label="t('stats.now.activeEvents')"
          :value="totals.events.active"
          :to="link('events', { status: 'published', phase: 'active', sort: 'upcoming' })"
        />
        <StatTile
          :label="t('stats.now.running')"
          :value="totals.events.running"
          :to="link('events', { status: 'published', phase: 'running', sort: 'upcoming' })"
        />
        <StatTile
          :label="t('stats.now.today')"
          :value="totals.events.today"
          :to="link('events', { phase: 'today', sort: 'upcoming' })"
        />
        <StatTile
          :label="t('stats.now.next7d')"
          :value="totals.events.next7d"
          :to="link('events', { phase: 'next7d', sort: 'upcoming' })"
        />
      </section>

      <!-- Vyžaduje pozornosť -->
      <section v-if="stats.attention.length" class="panel-card">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ t('stats.attention.title') }}</h2>
        <ul class="index-list">
          <li v-for="item in stats.attention" :key="item.key" class="attention-row">
            <span class="attention-dot" :style="{ backgroundColor: SEVERITY_COLORS[item.severity] }" aria-hidden="true" />
            <span class="min-w-0">
              <component
                :is="item.link ? 'RouterLink' : 'span'"
                :to="item.link ? link(item.link) : undefined"
                class="block font-semibold text-slate-900 no-underline hover:underline"
              >{{ item.label }}</component>
              <span class="block text-xs text-slate-500">{{ item.hint }}</span>
            </span>
            <span class="attention-count" :style="{ color: SEVERITY_COLORS[item.severity] }">
              {{ fmtCount(item.count) }}
            </span>
            <span class="attention-tag">{{ t(`stats.attention.${item.severity}`) }}</span>
          </li>
        </ul>
      </section>

      <!-- Prírastky za obdobie -->
      <section class="grid gap-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ t('stats.periods.title') }}</h2>
            <p class="text-sm text-slate-500">{{ periodSubtitle }}</p>
          </div>

          <div class="flex flex-wrap gap-1" role="group" :aria-label="t('stats.periods.group')">
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
        <!-- Návštevnosť verejných detailov -->
        <section class="panel-card grid gap-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ t('stats.views.title') }}</h2>
            <p class="text-sm text-slate-500">{{ t('stats.views.subtitle') }}</p>
          </div>

          <dl class="grid grid-cols-3 gap-2 text-sm">
            <div class="detail-card">
              <dt>{{ t('stats.views.events') }}</dt>
              <dd>{{ fmtCount(views.events) }}</dd>
            </div>
            <div class="detail-card">
              <dt>{{ t('stats.views.venues') }}</dt>
              <dd>{{ fmtCount(views.venues) }}</dd>
            </div>
            <div class="detail-card">
              <dt>{{ t('stats.views.canals') }}</dt>
              <dd>{{ fmtCount(views.canals) }}</dd>
            </div>
          </dl>

          <p class="text-sm text-slate-600">
            {{ t('stats.views.averagePrefix') }}
            <strong class="text-slate-900">{{ views.perPublishedEvent ?? '—' }}</strong>
            {{ t('stats.views.averageSuffix') }}
          </p>

          <!-- Koľko zo záujmu skončí registráciou — jediné číslo, ktoré spája
               návštevnosť s predajom. -->
          <MeterBar
            v-if="views.events > 0"
            :label="t('stats.views.conversion')"
            :part="ticketing.seats.valid"
            :whole="views.events"
            :rate="views.conversion"
            :unit="t('stats.views.conversionUnit')"
            :note="t('stats.views.conversionNote', { count: fmtCount(ticketing.seats.valid) })"
            color="#4a3aa7"
          />
          <p v-else class="text-sm text-slate-500">{{ t('stats.views.empty') }}</p>
        </section>

        <!-- Predaj a dochádzka -->
        <section class="panel-card grid gap-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ t('stats.tickets.title') }}</h2>
            <p class="text-sm text-slate-500">
              {{ t('stats.tickets.subtitle', { orders: fmtCount(ticketing.orders.total), seats: fmtCount(ticketing.seats.valid) }) }}
            </p>
          </div>

          <MeterBar
            :label="t('stats.tickets.occupancy')"
            :part="ticketing.capacity.sold"
            :whole="ticketing.capacity.seats"
            :rate="ticketing.capacity.rate"
            :unit="t('stats.tickets.occupancyUnit')"
            :note="ticketing.capacity.unlimitedTypes
              ? t('stats.tickets.unlimitedNote', { count: ticketing.capacity.unlimitedTypes })
              : null"
          />

          <MeterBar
            :label="t('stats.tickets.attendance')"
            :part="ticketing.attendance.arrived"
            :whole="ticketing.attendance.expected"
            :rate="ticketing.attendance.rate"
            :unit="t('stats.tickets.attendanceUnit')"
            color="#1baf7a"
          />

          <dl class="grid grid-cols-2 gap-2 text-sm">
            <div class="detail-card">
              <dt>{{ t('stats.tickets.paid') }}</dt>
              <dd>{{ fmtMoney(ticketing.orders.revenuePaid) }}</dd>
            </div>
            <div class="detail-card">
              <dt>{{ t('stats.tickets.awaitingPayment') }}</dt>
              <dd>{{ fmtMoney(ticketing.orders.revenueAwaiting) }}</dd>
            </div>
            <div class="detail-card">
              <dt>{{ t('stats.tickets.awaitingConfirmation') }}</dt>
              <dd>{{ fmtCount(ticketing.seats.awaitingConfirmation) }}</dd>
            </div>
            <div class="detail-card">
              <dt>{{ t('stats.tickets.cancelled') }}</dt>
              <dd>{{ fmtCount(ticketing.seats.cancelled) }}</dd>
            </div>
          </dl>
        </section>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Skladba obsahu -->
        <section class="panel-card grid gap-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ t('stats.composition.title') }}</h2>
            <p class="text-sm text-slate-500">
              {{ t('stats.composition.subtitle', {
                events: fmtCount(totals.events.total),
                venues: fmtCount(totals.venues.total),
                canals: fmtCount(totals.canals.total),
              }) }}
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
            :label="t('stats.composition.imported')"
            :part="stats.sources.imported"
            :whole="stats.sources.own + stats.sources.imported"
            :rate="stats.sources.importedRate"
            :unit="t('stats.composition.importedUnit')"
            :note="t('stats.composition.ownNote', { count: fmtCount(stats.sources.own) })"
            color="#eda100"
          />

          <div v-if="stats.topCanals.length">
            <p class="mb-1.5 text-sm font-semibold text-slate-700">{{ t('stats.composition.topCanals') }}</p>
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
            <p class="mt-1 text-xs text-slate-400">{{ t('stats.composition.topCanalsHint') }}</p>
          </div>
        </section>

        <!-- Najbližší program -->
        <section class="panel-card">
          <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ t('stats.upcoming.title') }}</h2>
          <ul v-if="stats.upcoming.length" class="index-list">
            <li v-for="event in stats.upcoming" :key="event.id" class="flex items-baseline justify-between gap-3 text-sm">
              <span class="min-w-0">
                <RouterLink :to="link(`events/${event.id}`)" class="block truncate font-medium text-slate-900 no-underline hover:underline">
                  {{ event.name }}
                </RouterLink>
                <span class="block truncate text-xs text-slate-500">{{ event.venue ?? t('stats.upcoming.noVenue') }}</span>
              </span>
              <span class="shrink-0 text-right text-xs">
                <span class="block whitespace-nowrap font-semibold text-slate-700">{{ startLabel(event.startAt) }}</span>
                <span v-if="event.seats" class="block text-slate-500">
                  {{ t('stats.upcoming.seats', { count: fmtCount(event.seats) }) }}
                </span>
              </span>
            </li>
          </ul>
          <p v-else class="text-sm text-slate-500">{{ t('stats.upcoming.empty') }}</p>
        </section>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Najviac zobrazené -->
        <section class="panel-card">
          <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ t('stats.mostViewed.title') }}</h2>
          <ul v-if="views.top.length" class="index-list">
            <li v-for="event in views.top" :key="event.id" class="flex items-baseline justify-between gap-3 text-sm">
              <RouterLink :to="link(`events/${event.id}`)" class="truncate font-medium text-slate-900 no-underline hover:underline">
                {{ event.name }}
              </RouterLink>
              <span class="shrink-0 text-right text-xs">
                <strong class="block tabular-nums text-slate-900">{{ fmtCount(event.views) }}×</strong>
                <span v-if="event.seats" class="block text-slate-500">
                  {{ t('stats.upcoming.seats', { count: fmtCount(event.seats) }) }}
                </span>
              </span>
            </li>
          </ul>
          <p v-else class="text-sm text-slate-500">{{ t('stats.mostViewed.empty') }}</p>
        </section>

        <!-- Najväčší záujem -->
        <section class="panel-card">
          <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ t('stats.mostInterest.title') }}</h2>
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
          <p v-else class="text-sm text-slate-500">{{ t('stats.mostInterest.empty') }}</p>
        </section>
      </div>

      <!-- Používatelia (len admin) -->
      <section v-if="stats.users" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatTile :label="t('stats.users.total')" :value="stats.users.total" :to="link('users')" />
        <StatTile :label="t('stats.users.verified')" :value="stats.users.verified" :to="link('users')" />
        <StatTile :label="t('stats.users.active30d')" :value="stats.users.active30d" :to="link('users')" />
        <StatTile :label="t('stats.users.blocked')" :value="stats.users.blocked" :to="link('users')" invert />
      </section>

      <p class="text-xs text-slate-400">
        {{ t('stats.updated', { time: generatedLabel }) }} ·
        <button type="button" class="underline hover:text-slate-600" @click="load">{{ t('stats.refresh') }}</button>
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import ActivityChart from './ActivityChart.vue'
import MeterBar from './MeterBar.vue'
import StatTile from './StatTile.vue'
import { fetchOverviewStats } from '@/api/stats'
import { useI18n } from '@/i18n'
import type { AttentionSeverity, ModelStatus, StatsOverview, StatsPeriodKey, StatsScope } from '@/types'
import { fmtCount, fmtDayMonth, fmtMoney, fmtTime } from '@/utils/statsFormat'

const props = defineProps<{ scope: StatsScope }>()

const { t, locale } = useI18n()

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

// Popisky závažnosti sú v slovníku (stats.attention.<severity>), tu ostáva
// len farba — tá sa jazykom nemení.
const SEVERITY_COLORS: Record<AttentionSeverity, string> = {
  info: '#2a78d6',
  warning: '#fab219',
  serious: '#ec835a',
  critical: '#d03b3b',
}

/** Ktorá séria trendu patrí ku ktorej metrike prehľadu. */
const SPARK_SOURCE: Record<string, keyof StatsOverview['trend'][number]> = {
  views: 'views',
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

// Popisky metrík a položiek „vyžaduje pozornosť" počíta server, takže po
// prepnutí jazyka by v starom jazyku ostali až do najbližšieho načítania.
watch(locale, load)

async function load() {
  loading.value = true
  error.value = null
  try {
    stats.value = await fetchOverviewStats(props.scope)
  } catch {
    error.value = t('stats.loadFailed')
  } finally {
    loading.value = false
  }
}

const totals = computed(() => stats.value!.totals)
const ticketing = computed(() => stats.value!.ticketing)
const views = computed(() => stats.value!.views)

const activePeriod = computed(() =>
  stats.value!.periods.find(p => p.key === periodKey.value) ?? stats.value!.periods[0],
)

const periodSubtitle = computed(() =>
  activePeriod.value.from ? t('stats.periods.comparison') : t('stats.periods.fromStart'),
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
  return at ? fmtTime(new Date(at)) : '—'
})

/**
 * Rovnaké stránky žijú pod /dashboard aj /admin — cieľ sa líši len prefixom.
 * Filtre sa pridávajú ako query, aby výpis po kliknutí ukázal presne tie
 * záznamy, ktoré sa do čísla rátali.
 */
function link(path: string, query: Record<string, string> = {}): string {
  const target = `/${props.scope}/${path}`
  const params = new URLSearchParams(query).toString()

  return params ? `${target}${target.includes('?') ? '&' : '?'}${params}` : target
}

function startLabel(startAt: string | null): string {
  if (!startAt) return '—'
  const date = new Date(startAt)
  const today = new Date()
  const sameDay = date.toDateString() === today.toDateString()

  return sameDay
    ? t('stats.upcoming.today', { time: fmtTime(date) })
    : `${fmtDayMonth(date)} ${fmtTime(date)}`
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
