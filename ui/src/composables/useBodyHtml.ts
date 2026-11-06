import { computed, type ComputedRef } from 'vue'
import { useI18n } from '@/i18n'
import { enhanceBodyLinks } from '@/utils/bodyLinks'

/** Popis z editora pripravený na `v-html`: holé URL ako krátke odkazy podľa typu. */
export function useBodyHtml(source: () => string | null | undefined): ComputedRef<string> {
  const { t } = useI18n()
  return computed(() => enhanceBodyLinks(source(), kind => t(`public.bodyLinks.${kind}`)))
}
