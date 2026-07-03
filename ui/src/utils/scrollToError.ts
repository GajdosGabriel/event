import { nextTick, type Ref } from 'vue'

// Waits for the (v-if-gated) error element to render, then scrolls it into view.
export async function scrollToError(elRef: Ref<HTMLElement | null | undefined>): Promise<void> {
  await nextTick()
  elRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}
