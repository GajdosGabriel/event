import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useHead } from '@vueuse/head'
import { t, type MessageKey } from '@/i18n'

// Segment URL → kľúč popisky bočného panela. Neznáme segmenty ostanú pri názve oblasti.
const SECTION_KEYS: Record<string, MessageKey> = {
  events: 'nav.events',
  canals: 'nav.canals',
  venues: 'nav.venues',
  organizations: 'nav.organizations',
  municipalities: 'nav.municipalities',
  files: 'nav.files',
  spravy: 'nav.messages',
  'navrhy-stitkov': 'nav.tagSuggestions',
}

/** Titulok „Sekcia · Oblasť | Event" a `noindex` pre prihlásené časti (dashboard, admin). */
export function useAreaHead(area: 'dashboard' | 'admin') {
  const route = useRoute()

  useHead({
    title: computed(() => {
      const areaLabel = area === 'admin' ? t('nav.admin') : t('nav.dashboard')
      const key = SECTION_KEYS[route.path.split('/').filter(Boolean)[1] ?? '']
      return `${key ? `${t(key)} · ` : ''}${areaLabel} | Event`
    }),
    meta: [{ name: 'robots', content: 'noindex, nofollow' }],
  })
}
