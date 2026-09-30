import { computed, ref, watch, type Ref } from 'vue'

/**
 * Stránkovanie zoznamu, ktorý už je celý v pamäti (filtrovaný na klientovi).
 * Vracia výrez pre aktuálnu stránku; `AppPaginator` sa sám skryje, keď je
 * stránka len jedna.
 */
export function useClientPagination<T>(source: Ref<T[]>, perPage = 20) {
  const page = ref(1)
  const lastPage = computed(() => Math.max(1, Math.ceil(source.value.length / perPage)))
  const items = computed(() => source.value.slice((page.value - 1) * perPage, page.value * perPage))

  // Po zmene filtra by sa aktuálna stránka mohla ocitnúť za koncom zoznamu.
  watch(lastPage, last => { if (page.value > last) page.value = last })

  function setPage(next: number) {
    page.value = Math.min(Math.max(1, next), lastPage.value)
  }

  return { page, lastPage, items, setPage }
}
