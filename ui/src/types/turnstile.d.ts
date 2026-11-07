/** Cloudflare Turnstile — len časť API, ktorú používa BotGuard.vue. */
interface TurnstileRenderOptions {
  sitekey: string
  callback?: (token: string) => void
  'expired-callback'?: () => void
  'error-callback'?: () => void
  language?: string
  appearance?: 'always' | 'execute' | 'interaction-only'
}

interface TurnstileApi {
  render(container: HTMLElement, options: TurnstileRenderOptions): string
  reset(widgetId: string): void
  remove(widgetId: string): void
}

interface Window {
  turnstile?: TurnstileApi
}
