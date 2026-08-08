<template>
  <div class="flex flex-wrap items-center gap-2">
    <!-- Systémové zdieľanie je na mobile jediná cesta do WhatsAppu a Messengeru;
         na desktope ho prehliadače nemajú, tam ostanú len konkrétne siete. -->
    <button
      v-if="canNativeShare"
      type="button"
      class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white transition-opacity hover:opacity-90"
      @click="shareNative"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.7 10.7a3 3 0 100 2.6m0-2.6l6.6-3.4m-6.6 6l6.6 3.4M18 8a3 3 0 100-6 3 3 0 000 6zm0 14a3 3 0 100-6 3 3 0 000 6z" />
      </svg>
      Zdieľať
    </button>

    <a
      :href="facebookUrl"
      target="_blank"
      rel="noopener noreferrer"
      aria-label="Zdieľať na Facebooku"
      class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 no-underline transition-colors hover:bg-slate-50 hover:text-slate-900"
    >
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M22 12a10 10 0 10-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0022 12z" />
      </svg>
    </a>

    <a
      :href="whatsappUrl"
      target="_blank"
      rel="noopener noreferrer"
      aria-label="Poslať cez WhatsApp"
      class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 no-underline transition-colors hover:bg-slate-50 hover:text-slate-900"
    >
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M12 2a10 10 0 00-8.6 15L2 22l5.2-1.4A10 10 0 1012 2zm5.8 14.1c-.2.7-1.4 1.3-2 1.4-.5.1-1.2.1-1.9-.1a12 12 0 01-6.9-6c-.5-.9-.8-1.8-.8-2.6 0-.9.5-1.6 1-1.9.2-.2.4-.2.6-.2h.5c.2 0 .4 0 .6.5l.8 1.9c0 .2 0 .3-.1.5l-.4.5c-.1.1-.3.3-.1.6.2.3.7 1.1 1.4 1.8.9.8 1.7 1.1 2 1.2.2.1.4.1.6-.1l.7-.8c.2-.2.3-.2.6-.1l1.8.9c.3.1.4.2.5.3v1.2z" />
      </svg>
    </a>

    <a
      :href="emailUrl"
      aria-label="Poslať e-mailom"
      class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 no-underline transition-colors hover:bg-slate-50 hover:text-slate-900"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <rect x="3" y="5" width="18" height="14" rx="2" />
        <path stroke-linecap="round" d="M3 7l9 6 9-6" />
      </svg>
    </a>

    <button
      type="button"
      class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-900"
      @click="copyLink"
    >
      <svg v-if="!copied" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <rect x="9" y="9" width="12" height="12" rx="2" />
        <path stroke-linecap="round" d="M5 15V5a2 2 0 012-2h8" />
      </svg>
      <svg v-else class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
      </svg>
      {{ copied ? 'Skopírované' : 'Kopírovať odkaz' }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, onBeforeUnmount } from 'vue'

const props = defineProps<{
  /** Absolútna adresa — relatívna by sa v cudzej aplikácii nedala otvoriť. */
  url: string
  title: string
  /** Krátky doplnok do textu správy, napr. termín a miesto. */
  text?: string | null
}>()

const copied = ref(false)
let copiedTimer: ReturnType<typeof setTimeout> | null = null

// `navigator.share` je len v zabezpečenom kontexte a hlavne na mobiloch;
// dopyt sa vyhodnotí raz, počas života stránky sa podpora nemení.
const canNativeShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function'

const message = computed(() => (props.text ? `${props.title} — ${props.text}` : props.title))
const facebookUrl = computed(() => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(props.url)}`)
const whatsappUrl = computed(() => `https://wa.me/?text=${encodeURIComponent(`${message.value} ${props.url}`)}`)
const emailUrl = computed(() => `mailto:?subject=${encodeURIComponent(props.title)}&body=${encodeURIComponent(`${message.value}\n\n${props.url}`)}`)

async function shareNative() {
  try {
    await navigator.share({ title: props.title, text: message.value, url: props.url })
  } catch {
    // Zavretie systémového dialógu hlási `AbortError` — nejde o chybu.
  }
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(props.url)
    copied.value = true
    if (copiedTimer) clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => { copied.value = false }, 2000)
  } catch {
    window.prompt('Skopíruj odkaz:', props.url)
  }
}

onBeforeUnmount(() => {
  if (copiedTimer) clearTimeout(copiedTimer)
})
</script>
