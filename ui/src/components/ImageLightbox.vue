<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="current" class="fixed inset-0 z-9999 bg-black/85">
        <!-- Plocha obrázka: v priblíženom stave sa z nej stáva scrollovateľné okno -->
        <div
          ref="viewportEl"
          class="flex h-full w-full"
          :class="zoomed ? 'overflow-auto p-0' : 'overflow-hidden p-4'"
          @click="onSurfaceClick"
          @pointerdown="onPointerDown"
        >
          <img
            ref="imgEl"
            :src="displaySrc"
            :alt="current.alt || ''"
            draggable="false"
            class="m-auto select-none"
            :class="zoomed
              ? 'max-w-none cursor-zoom-out'
              : 'max-h-[90vh] max-w-[90vw] rounded-xl object-contain shadow-2xl cursor-zoom-in'"
            :style="zoomed ? { width: `${zoomWidth}px` } : undefined"
            @click.stop="onImageClick"
          />
        </div>

        <button
          class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
          title="Zavrieť (Esc)"
          @click="close"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>

        <template v-if="!zoomed">
          <button
            v-if="idx > 0"
            class="absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"
            @click.stop="prev"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
          </button>
          <button
            v-if="idx < images.length - 1"
            class="absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"
            @click.stop="next"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
          </button>
        </template>

        <div
          v-if="(images.length > 1 && !zoomed) || zoomLoading"
          class="pointer-events-none absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-xs text-white"
        >
          {{ zoomLoading ? 'Načítavam…' : `${idx + 1} / ${images.length}` }}
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { useWindowKeydown } from '@/composables/useWindowKeydown'

export interface LightboxImage {
  /** Zmenšenina zobrazená na celý obrázok (typicky `large` derivát). */
  src: string
  /** Voliteľný originál načítaný až pri priblížení — kvôli čitateľnosti textu. */
  zoomSrc?: string
  alt?: string
}

const props = defineProps<{
  images: LightboxImage[]
  /** Index zobrazeného obrázka, `null` = lightbox je zatvorený (v-model:index). */
  index: number | null
}>()

const emit = defineEmits<{ 'update:index': [number | null] }>()

/** Koľkonásobne sa priblíži obrázok, ktorý sa v okne zobrazuje takmer 1:1. */
const MIN_ZOOM = 1.8
/** Strop priblíženia, aby sa z malého obrázka nestala rozmazaná plocha. */
const MAX_ZOOM = 4

const viewportEl = ref<HTMLElement | null>(null)
const imgEl = ref<HTMLImageElement | null>(null)
const zoomed = ref(false)
const zoomWidth = ref(0)
const zoomLoading = ref(false)
/** Predčítaný originál aktuálneho obrázka — `null`, kým ho netreba. */
const hiResSrc = ref<string | null>(null)

const idx = computed(() => props.index ?? 0)
const current = computed(() => (props.index === null ? null : props.images[props.index] ?? null))
const displaySrc = computed(() => (zoomed.value && hiResSrc.value) || current.value?.src || '')

// Prepnutie obrázka vždy vracia pohľad do základného (zmenšeného) stavu.
watch(() => props.index, () => {
  zoomed.value = false
  zoomLoading.value = false
  hiResSrc.value = null
})

function close() { emit('update:index', null) }
function prev() { if (props.index !== null && props.index > 0) emit('update:index', props.index - 1) }
function next() { if (props.index !== null && props.index < props.images.length - 1) emit('update:index', props.index + 1) }

useWindowKeydown((e) => {
  if (props.index === null) return
  // Esc najprv zruší priblíženie, až potom zatvára — inak by sa nedalo vrátiť späť na celý obrázok.
  if (e.key === 'Escape') {
    if (zoomed.value) zoomed.value = false
    else close()
  }
  else if (e.key === 'ArrowLeft') prev()
  else if (e.key === 'ArrowRight') next()
})

/**
 * Druhý klik na obrázok priblíži na rozlíšenie originálu (najviac MAX_ZOOM),
 * aby sa dal prečítať text na plagátoch. Bod, na ktorý používateľ klikol,
 * ostáva v strede pohľadu — rovnako ako to robia bežné prehliadače fotiek.
 * Ďalší klik vráti obrázok späť na celý.
 */
async function onImageClick(event: MouseEvent) {
  if (panMoved) { panMoved = false; return }

  if (zoomed.value) {
    zoomed.value = false
    return
  }

  const img = imgEl.value
  if (!img) return

  const rect = img.getBoundingClientRect()
  if (!rect.width) return

  const relX = (event.clientX - rect.left) / rect.width
  const relY = (event.clientY - rect.top) / rect.height

  // Originál sa sťahuje až teraz — v zmenšenom náhľade by bol zbytočný.
  let natural = img.naturalWidth || rect.width
  const zoomSrc = current.value?.zoomSrc
  if (zoomSrc && zoomSrc !== current.value?.src && !hiResSrc.value) {
    zoomLoading.value = true
    const wanted = props.index
    const loaded = await preload(zoomSrc)
    zoomLoading.value = false
    if (props.index !== wanted) return
    if (loaded) {
      hiResSrc.value = zoomSrc
      natural = Math.max(natural, loaded.naturalWidth)
    }
  } else if (hiResSrc.value) {
    natural = Math.max(natural, img.naturalWidth)
  }

  const factor = Math.min(Math.max(natural / rect.width, MIN_ZOOM), MAX_ZOOM)
  zoomWidth.value = Math.round(rect.width * factor)
  zoomed.value = true

  await nextTick()
  const viewport = viewportEl.value
  if (!viewport) return
  viewport.scrollLeft = relX * viewport.scrollWidth - viewport.clientWidth / 2
  viewport.scrollTop = relY * viewport.scrollHeight - viewport.clientHeight / 2
}

/** Načíta obrázok do cache prehliadača; `null` keď sa nepodarí. */
function preload(src: string): Promise<HTMLImageElement | null> {
  return new Promise((resolve) => {
    const image = new Image()
    image.onload = () => resolve(image)
    image.onerror = () => resolve(null)
    image.src = src
  })
}

/** Klik mimo obrázka zatvára — ale nie vtedy, keď to bol koniec ťahania. */
function onSurfaceClick(event: MouseEvent) {
  if (panMoved) { panMoved = false; return }
  if (event.target === event.currentTarget) close()
}

// Posúvanie myšou v priblíženom stave. Dotykové zariadenia scrollujú natívne.
let panStart: { x: number; y: number; left: number; top: number } | null = null
let panMoved = false

function onPointerDown(event: PointerEvent) {
  if (!zoomed.value || event.pointerType !== 'mouse' || event.button !== 0) return
  const viewport = viewportEl.value
  if (!viewport) return

  panStart = { x: event.clientX, y: event.clientY, left: viewport.scrollLeft, top: viewport.scrollTop }
  panMoved = false
  event.preventDefault()
  window.addEventListener('pointermove', onPointerMove)
  window.addEventListener('pointerup', onPointerUp)
}

function onPointerMove(event: PointerEvent) {
  const viewport = viewportEl.value
  if (!panStart || !viewport) return

  const dx = event.clientX - panStart.x
  const dy = event.clientY - panStart.y
  if (Math.abs(dx) > 4 || Math.abs(dy) > 4) panMoved = true
  viewport.scrollLeft = panStart.left - dx
  viewport.scrollTop = panStart.top - dy
}

function onPointerUp() {
  panStart = null
  window.removeEventListener('pointermove', onPointerMove)
  window.removeEventListener('pointerup', onPointerUp)
}
</script>
