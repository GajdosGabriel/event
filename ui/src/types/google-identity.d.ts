/**
 * Minimálne typy pre Google Identity Services (accounts.google.com/gsi/client).
 * Len to, čo naozaj používame v useGoogleIdentity + GoogleSignInButton.
 * Plná definícia: https://developers.google.com/identity/gsi/web/reference/js-reference
 */

interface GoogleCredentialResponse {
  /** JWT ID token — posiela sa na /api/login/google ako `id_token`. */
  credential: string
  select_by?: string
}

interface GoogleIdConfiguration {
  client_id: string
  callback: (response: GoogleCredentialResponse) => void
  auto_select?: boolean
  cancel_on_tap_outside?: boolean
  ux_mode?: 'popup' | 'redirect'
  context?: 'signin' | 'signup' | 'use'
}

interface GoogleButtonConfiguration {
  type?: 'standard' | 'icon'
  theme?: 'outline' | 'filled_blue' | 'filled_black'
  size?: 'large' | 'medium' | 'small'
  text?: 'signin_with' | 'signup_with' | 'continue_with' | 'signin'
  shape?: 'rectangular' | 'pill' | 'circle' | 'square'
  logo_alignment?: 'left' | 'center'
  width?: number
  locale?: string
}

interface GoogleIdApi {
  initialize(config: GoogleIdConfiguration): void
  renderButton(parent: HTMLElement, options: GoogleButtonConfiguration): void
  prompt(): void
  cancel(): void
  disableAutoSelect(): void
}

interface Window {
  google?: {
    accounts: {
      id: GoogleIdApi
    }
  }
}
