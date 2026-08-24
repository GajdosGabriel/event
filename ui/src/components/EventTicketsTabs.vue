<template>
  <div class="mb-4 flex flex-wrap items-center gap-2">
    <!-- Späť ukazujeme len na prvej karte (Nastavenia) — medzi kartami sa
         chodí navigáciou nižšie, nie tlačidlom späť. Vracia tam, odkiaľ
         používateľ do sekcie lístkov prišiel; ak to nevieme, na event. -->
    <button v-if="showBack" type="button" class="action-btn" @click="goBack">{{ t('common.back') }}</button>
    <nav class="nav-tabs" :class="showBack ? 'ml-auto' : ''">
      <RouterLink
        v-for="tab in tabs"
        :key="tab.name"
        :to="tab.to"
        class="nav-tab"
        :class="{ active: isActive(tab.name) }"
      >
        <AppIcon :name="tab.icon" class="h-4 w-4" />
        {{ tab.label }}
      </RouterLink>
    </nav>

    <!-- Kontrola „ako to vidí návštevník" — na dosah zo všetkých kariet
         podujatia, nie schovaná o dve obrazovky vyššie. -->
    <PublicPreviewLink :to="publicPath" :class="showBack ? '' : 'ml-auto'" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon, { type IconName } from '@/components/AppIcon.vue'
import PublicPreviewLink from '@/components/PublicPreviewLink.vue'
import { publicEventPath } from '@/utils/publicUrl'
import { useI18n } from '@/i18n'

const props = withDefaults(defineProps<{ eventId: number; showBack?: boolean }>(), { showBack: false })
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const eventPath = computed(() => `/dashboard/events/${props.eventId}`)

// Bez slugu — routuje sa aj tak len id za poslednou pomlčkou a karty lístkov
// názov podujatia nenačítavajú. Verejný detail si kanonickú adresu doplní sám.
const publicPath = computed(() => publicEventPath({ id: props.eventId }))

// Ikony sa neberú odtiaľto, ale z registra v AppIcon — tá istá ikona je na
// karte aj na tlačidle na detaile podujatia a mení sa na jednom mieste.
const tabs = computed<{ name: string; label: string; to: string; icon: IconName }[]>(() => [
  { name: 'settings', label: t('tickets.tabs.settings'), to: `${eventPath.value}/tickets`, icon: 'ticket' },
  { name: 'attendees', label: t('tickets.tabs.attendees'), to: `${eventPath.value}/attendees`, icon: 'users' },
  { name: 'checkin', label: t('tickets.tabs.checkin'), to: `${eventPath.value}/checkin`, icon: 'checkin' },
  { name: 'questions', label: t('questions.dashboard.tab'), to: `${eventPath.value}/otazky`, icon: 'question' },
])

function isActive(name: string): boolean {
  if (name === 'settings') return String(route.name ?? '').startsWith('dashboard-events-tickets')
  if (name === 'attendees') return route.name === 'dashboard-events-attendees'
  if (name === 'checkin') return route.name === 'dashboard-events-checkin'
  if (name === 'questions') return route.name === 'dashboard-events-questions'
  return false
}

function goBack() {
  // Predošlá adresa z histórie routera. Vraciame sa len na vlastnú aplikáciu a
  // nikdy späť do sekcie lístkov — inak by tlačidlo krúžilo medzi kartami.
  const previous = (router.options.history.state as { back?: unknown } | null)?.back
  const path = typeof previous === 'string' ? previous : ''
  if (path.startsWith('/') && !path.startsWith('//') && !path.startsWith(`${eventPath.value}/`)) {
    router.push(path)
    return
  }
  router.push(eventPath.value)
}
</script>
