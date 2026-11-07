/**
 * Lazy loader pre Cloudflare Turnstile (náhrada captchy). Skript sa pridá až
 * na stránkach s formulárom, ktorý posiela e-mail na zadanú adresu.
 *
 * Bez `VITE_TURNSTILE_SITE_KEY` sa widget nevykreslí a backend (bez
 * `TURNSTILE_SECRET_KEY`) token nepýta. Zapínajú sa spolu.
 */

const SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'

let loader: Promise<TurnstileApi> | null = null

export function turnstileSiteKey(): string | undefined {
  const key = import.meta.env.VITE_TURNSTILE_SITE_KEY
  return typeof key === 'string' && key !== '' ? key : undefined
}

export function loadTurnstile(): Promise<TurnstileApi> {
  if (loader) return loader

  loader = new Promise<TurnstileApi>((resolve, reject) => {
    if (window.turnstile) {
      resolve(window.turnstile)
      return
    }

    const existing = document.querySelector<HTMLScriptElement>(`script[src="${SRC}"]`)
    const script = existing ?? document.createElement('script')

    script.addEventListener('load', () => {
      if (window.turnstile) resolve(window.turnstile)
      else {
        loader = null
        reject(new Error('Turnstile sa načítal, ale API chýba'))
      }
    })
    script.addEventListener('error', () => {
      loader = null
      reject(new Error('Turnstile sa nepodarilo načítať'))
    })

    if (!existing) {
      script.src = SRC
      script.async = true
      document.head.appendChild(script)
    }
  })

  return loader
}
