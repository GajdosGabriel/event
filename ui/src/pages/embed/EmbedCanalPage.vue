<template>
  <div>
    <p v-if="loading" class="embed-note">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="embed-note">{{ t('embed.loadFailed') }}</p>

    <template v-else>
      <h1 v-if="showTitle && canalName" class="mb-2 text-base font-semibold text-slate-900">{{ canalName }}</h1>

      <p v-if="!events.length" class="embed-note">{{ t('embed.empty') }}</p>

      <ul v-else class="grid gap-2">
        <li v-for="event in events" :key="event.id">
          <!-- Odkaz musí otvoriť novú kartu: v iframe by sa portál načítal do
               widgetu širokého 300 px na cudzom webe. -->
          <a
            :href="absoluteUrl(publicEventPath(event))"
            target="_blank"
            rel="noopener"
            class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-2 no-underline transition-colors hover:border-slate-300"
          >
            <img
              v-if="showImages && event.imageUrl"
              :src="event.imageUrl"
              :alt="event.name"
              loading="lazy"
              decoding="async"
              class="h-14 w-14 shrink-0 rounded-md object-cover"
            />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium text-slate-900">{{ event.name }}</span>
              <span v-if="dateLabel(event)" class="block text-xs text-slate-500">{{ dateLabel(event) }}</span>
            </span>
          </a>
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { listCanalEvents, showCanalPublic, type CanalEventItem } from '@/api/canals'
import { t } from '@/i18n'
import { absoluteUrl, idFromRouteParam, publicEventPath } from '@/utils/publicUrl'
import { fmtRowDateRange } from '@/utils/dateFormat'

/**
 * Program organizátora na jeho vlastnom webe.
 *
 * Zámerne nad **verejným** API — widget beží bez prihlásenia a bez tokenu.
 * Ukazuje presne to, čo je aj tak verejné na portáli; nič, čo by sa dalo cez
 * iframe vytiahnuť navyše.
 */
const route = useRoute()

const canalId = Number(idFromRouteParam(route.params.slugId as string))

/** `?limit=5` — koľko najbližších termínov ukázať. */
const limit = computed(() => {
  const raw = Number(route.query.limit)
  return Number.isFinite(raw) && raw > 0 ? Math.min(raw, 20) : 5
})

/** `?title=0` skryje názov organizátora — jeho web ho už väčšinou má v nadpise. */
const showTitle = computed(() => route.query.title !== '0')
const showImages = computed(() => route.query.images !== '0')

const canalName = ref('')
const events = ref<CanalEventItem[]>([])
const loading = ref(true)
const error = ref(false)

function dateLabel(event: CanalEventItem): string | null {
  return fmtRowDateRange(event.startAt, event.endAt)
}

async function load() {
  try {
    const [canal, list] = await Promise.all([
      showCanalPublic(canalId).catch(() => null),
      listCanalEvents('public', canalId),
    ])

    canalName.value = canal?.name ?? ''
    // Verejný endpoint vracia len publikované podujatia; tu ich len skrátime
    // na počet, ktorý si organizátor vyžiadal v adrese.
    events.value = list.slice(0, limit.value)
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

load()
</script>
