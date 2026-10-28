import { useHead } from '@vueuse/head'

/** Titulok a `noindex` pre stránky, ktoré nemajú byť vo vyhľadávačoch (prihlásenie, reset hesla...). */
export function usePrivatePageHead(title: () => string) {
  useHead({
    title: () => `${title()} | Tixio`,
    meta: [{ name: 'robots', content: 'noindex, nofollow' }],
  })
}
