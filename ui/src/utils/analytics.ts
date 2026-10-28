import { ref } from 'vue'

/**
 * Google Analytics 4 — vlastníctvo „Tixio", stream tixio.eu.
 *
 * Skript sa načíta až po súhlase návštevníka (ochrana osobných údajov, čl. 9
 * to sľubuje) a len na produkčnej doméne — lokálny vývoj ani staging nesmú
 * miešať návštevnosť do reportov. SPA prechody GA4 zachytí sám cez
 * rozšírené meranie (zmeny histórie prehliadača), routerovi netreba nič.
 */
const MEASUREMENT_ID = 'G-C35MFVP0QD'
const PRODUCTION_HOST = 'tixio.eu'
const STORAGE_KEY = 'tixio.analytics-consent'

export type AnalyticsConsent = 'granted' | 'denied'

function readConsent(): AnalyticsConsent | null {
  try {
    const value = localStorage.getItem(STORAGE_KEY)
    return value === 'granted' || value === 'denied' ? value : null
  } catch {
    return null
  }
}

/** Uložená voľba; `null` = ešte sa nerozhodol, lišta sa má ukázať. */
export const analyticsConsent = ref<AnalyticsConsent | null>(readConsent())

/** Lištu otvorí aj odkaz v pätičke — súhlas sa musí dať kedykoľvek odvolať. */
export const consentBannerOpen = ref(analyticsConsent.value === null)

export const analyticsAvailable = typeof window !== 'undefined' && window.location.hostname === PRODUCTION_HOST

let loaded = false

type Gtag = (...args: unknown[]) => void
declare global {
  interface Window {
    dataLayer?: unknown[]
    gtag?: Gtag
  }
}

function loadAnalytics(): void {
  if (loaded || !analyticsAvailable) return
  loaded = true

  window.dataLayer = window.dataLayer || []
  window.gtag = function gtag() {
    // gtag.js číta práve objekt `arguments`, nie pole — tak to chce Google.
    // eslint-disable-next-line prefer-rest-params
    window.dataLayer!.push(arguments)
  }
  window.gtag('consent', 'default', {
    analytics_storage: 'granted',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
  })
  window.gtag('js', new Date())
  window.gtag('config', MEASUREMENT_ID)

  const script = document.createElement('script')
  script.async = true
  script.src = `https://www.googletagmanager.com/gtag/js?id=${MEASUREMENT_ID}`
  document.head.appendChild(script)
}

/** Odvolanie: GA prestane merať hneď a jeho cookies sa zmažú. */
function disableAnalytics(): void {
  if (loaded && window.gtag) {
    window.gtag('consent', 'update', { analytics_storage: 'denied' })
  }
  const domain = window.location.hostname
  document.cookie.split(';').map((c) => c.split('=')[0].trim()).filter((name) => name.startsWith('_ga')).forEach((name) => {
    document.cookie = `${name}=; Max-Age=0; path=/`
    document.cookie = `${name}=; Max-Age=0; path=/; domain=.${domain}`
  })
}

export function setAnalyticsConsent(value: AnalyticsConsent): void {
  analyticsConsent.value = value
  consentBannerOpen.value = false
  try {
    localStorage.setItem(STORAGE_KEY, value)
  } catch {
    // Bez úložiska sa lišta ukáže znova pri ďalšej návšteve — nič horšie.
  }
  if (value === 'granted') loadAnalytics()
  else disableAnalytics()
}

/** Volá sa raz pri štarte aplikácie. */
export function initAnalytics(): void {
  if (analyticsConsent.value === 'granted') loadAnalytics()
}
