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
  // Admin — bez nich mala každá karta prehliadača titulok „Admin | Tixio".
  prevzatia: 'nav.canalClaims',
  vstupenky: 'nav.tickets',
  otazky: 'nav.questions',
  oznamy: 'nav.announcements',
  podpora: 'nav.support',
  users: 'nav.users',
  'ai-spotreba': 'nav.aiUsage',
  dennik: 'nav.systemLog',
  tools: 'nav.tools',
  settings: 'nav.settings',
}

/** Titulok „Sekcia · Oblasť | Tixio" a `noindex` pre prihlásené časti (dashboard, admin). */
export function useAreaHead(area: 'dashboard' | 'admin') {
  const route = useRoute()

  useHead({
    title: computed(() => {
      const areaLabel = area === 'admin' ? t('nav.admin') : t('nav.dashboard')
      const key = SECTION_KEYS[route.path.split('/').filter(Boolean)[1] ?? '']
      return `${key ? `${t(key)} · ` : ''}${areaLabel} | Tixio`
    }),
    meta: [{ name: 'robots', content: 'noindex, nofollow' }],
  })
}
