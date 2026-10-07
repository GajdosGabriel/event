import { useHead } from '@vueuse/head'

/** Titulok a `noindex` pre stránky, ktoré nemajú byť vo vyhľadávačoch (prihlásenie, reset hesla...). */
export function usePrivatePageHead(title: () => string) {
  useHead({
    title: () => `${title()} | Event`,
    meta: [{ name: 'robots', content: 'noindex, nofollow' }],
  })
}
