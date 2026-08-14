<template>
  <div v-if="links" ref="rootEl" class="relative">
    <button
      type="button"
      class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 transition-colors hover:text-blue-700"
      aria-haspopup="menu"
      :aria-expanded="open"
      @click="open = !open"
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <rect x="3" y="4" width="18" height="18" rx="2" /><path stroke-linecap="round" d="M16 2v4M8 2v4M3 10h18M12 13v5m0 0l-2-2m2 2l2-2" />
      </svg>
      Pridať do kalendára
      <svg
        class="h-3.5 w-3.5 transition-transform duration-150"
        :class="{ 'rotate-180': open }"
        fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="scale-95 opacity-0"
      enter-to-class="scale-100 opacity-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="scale-100 opacity-100"
      leave-to-class="scale-95 opacity-0"
    >
      <div
        v-if="open"
        role="menu"
        class="absolute left-0 top-full z-30 mt-1.5 w-60 origin-top-left overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
      >
        <!-- Webové kalendáre otvárame v novej karte — návštevník sa po uložení
             termínu vráti na podujatie tam, kde skončil. -->
        <a
          v-for="item in items"
          :key="item.label"
          :href="item.href"
          :target="item.external ? '_blank' : undefined"
          :rel="item.external ? 'noopener noreferrer' : undefined"
          role="menuitem"
          class="flex items-start gap-2.5 px-4 py-2 text-sm text-slate-700 no-underline transition hover:bg-slate-50"
          @click="open = false"
        >
          <component :is="item.icon" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
          <span>
            <span class="block font-medium text-slate-900">{{ item.label }}</span>
            <span class="block text-xs text-slate-400">{{ item.hint }}</span>
          </span>
        </a>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, defineComponent, h } from 'vue'
import { useWindowKeydown } from '@/composables/useWindowKeydown'
import type { CalendarLinks } from '@/types'

const props = defineProps<{
  /** Odkazy skladá API (IcsGenerator). Bez termínu prídu null a tlačidlo vypadne. */
  links: CalendarLinks | null
}>()

const open = ref(false)
const rootEl = ref<HTMLElement | null>(null)

// Samotný `.ics` súbor pridá termín do kalendára len tam, kde ho má systém komu
// odovzdať. Kto má kalendár v prehliadači (Google, Outlook Web), po stiahnutí
// súboru neurobí nič — preto tie dve cesty vedú rovno do webového kalendára.
const items = computed(() => {
  const links = props.links
  if (!links) return []

  return [
    { label: 'Google Kalendár', hint: 'Otvorí sa v prehliadači', href: links.google, external: true, icon: IconGoogle },
    { label: 'Outlook', hint: 'Otvorí sa v prehliadači', href: links.outlook, external: true, icon: IconOutlook },
    { label: 'Apple Kalendár a ostatné', hint: 'Súbor .ics', href: links.download, external: false, icon: IconFile },
  ]
})

function onClickOutside(e: MouseEvent) {
  if (rootEl.value && !rootEl.value.contains(e.target as Node)) open.value = false
}

useWindowKeydown((e) => {
  if (e.key === 'Escape') open.value = false
})

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))

const IconGoogle = defineComponent({ render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [h('rect', { x: '3', y: '4', width: '18', height: '18', rx: '2' }), h('path', { 'stroke-linecap': 'round', d: 'M16 2v4M8 2v4M3 10h18' })]) })
const IconOutlook = defineComponent({ render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [h('rect', { x: '3', y: '5', width: '18', height: '14', rx: '2' }), h('path', { 'stroke-linecap': 'round', d: 'M3 7l9 6 9-6' })]) })
const IconFile = defineComponent({ render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '2', viewBox: '0 0 24 24' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M12 3v12m0 0l-3.5-3.5M12 15l3.5-3.5M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2' })]) })
</script>
