/**
 * Lazy loader pre Google Identity Services. Skript sa pridá do stránky až pri
 * prvom volaní (na /login a /register), nie pri štarte SPA — na väčšine
 * stránok portálu ho netreba.
 *
 * Backend čaká ID token z tohto flow (`AuthController::googleAuth`), nie
 * server-side OAuth redirect.
 */

const GSI_SRC = 'https://accounts.google.com/gsi/client'

let loader: Promise<GoogleIdApi> | null = null

export function googleClientId(): string | undefined {
  const id = import.meta.env.VITE_GOOGLE_CLIENT_ID
  return typeof id === 'string' && id !== '' ? id : undefined
}

export function loadGoogleIdentity(): Promise<GoogleIdApi> {
  if (loader) return loader

  loader = new Promise<GoogleIdApi>((resolve, reject) => {
    if (window.google?.accounts?.id) {
      resolve(window.google.accounts.id)
      return
    }

    const existing = document.querySelector<HTMLScriptElement>(`script[src="${GSI_SRC}"]`)
    const script = existing ?? document.createElement('script')

    script.addEventListener('load', () => {
      if (window.google?.accounts?.id) resolve(window.google.accounts.id)
      else {
        loader = null
        reject(new Error('Google Identity Services sa načítalo, ale API chýba'))
      }
    })
    script.addEventListener('error', () => {
      loader = null
      reject(new Error('Google Identity Services sa nepodarilo načítať'))
    })

    if (!existing) {
      script.src = GSI_SRC
      script.async = true
      document.head.appendChild(script)
    }
  })

  return loader
}
