import { useRouter } from 'vue-router'

/**
 * Po uložení formulára späť tam, odkiaľ ho človek otvoril (zoznam, detail,
 * dashboard…). vue-router si predchádzajúcu adresu drží v `history.state.back`;
 * keď chýba — formulár otvorený priamo z adresy alebo v novej karte — ide sa
 * na `fallback` (spravidla detail záznamu).
 */
export function useReturnAfterSave() {
  const router = useRouter()

  return (fallback: string) => {
    const back = (window.history.state as { back?: unknown } | null)?.back
    const usable = typeof back === 'string'
      && back !== router.currentRoute.value.fullPath
      && !/^\/(login|register)\b/.test(back)

    if (usable) router.back()
    else router.push(fallback)
  }
}
