import { nextTick, type Ref } from 'vue'

// Waits for the (v-if-gated) error element to render, then scrolls it into view.
export async function scrollToError(elRef: Ref<HTMLElement | null | undefined>): Promise<void> {
  await nextTick()
  elRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

// Presunie fokus na prvé pole označené `aria-invalid`, aby klávesnica a čítačka
// obrazovky pokračovali pri chybe, nie na <body>. Nescrolluje — o to sa stará banner.
export async function focusFirstInvalid(): Promise<void> {
  await nextTick()
  document
    .querySelector<HTMLElement>('input[aria-invalid="true"], textarea[aria-invalid="true"], select[aria-invalid="true"]')
    ?.focus({ preventScroll: true })
}
