<template>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div ref="mapEl" class="h-[32rem] w-full" />
    <p v-if="withoutCoordinates" class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
      {{ t('public.list.mapMissing', { n: withoutCoordinates }) }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import { useTemplateRef } from 'vue'
import type { EventItem } from '@/types'
import { t } from '@/i18n'
import { publicEventPath } from '@/utils/publicUrl'

delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)['_getIconUrl']
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
})

const props = defineProps<{ events: EventItem[] }>()

const mapEl = useTemplateRef<HTMLElement>('mapEl')

let map: L.Map | null = null
let markers: L.LayerGroup | null = null

/** Podujatia s miestom, ktoré má súradnice — ostatné na mape byť nemôžu. */
const located = computed(() => props.events.filter((event) => {
  const lat = Number(event.venue?.latitude)
  const lng = Number(event.venue?.longitude)
  return Number.isFinite(lat) && Number.isFinite(lng)
}))

/**
 * Koľko podujatí sa na mapu nedostalo. Ticho ich zahodiť by znamenalo, že
 * mapa ukazuje menej než zoznam a nikto nevie prečo — pri importovanom
 * katalógu je miesto bez súradníc bežné.
 */
const withoutCoordinates = computed(() => props.events.length - located.value.length)

onMounted(() => {
  if (!mapEl.value) return

  map = L.map(mapEl.value, { scrollWheelZoom: false })
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 18,
  }).addTo(map)

  markers = L.layerGroup().addTo(map)
  render()
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
})

watch(located, render)

function render() {
  if (!map || !markers) return

  markers.clearLayers()

  const points: L.LatLngExpression[] = []

  for (const event of located.value) {
    const lat = Number(event.venue?.latitude)
    const lng = Number(event.venue?.longitude)

    points.push([lat, lng])

    L.marker([lat, lng])
      .bindPopup(
        // Odkaz je obyčajný `<a>`, nie RouterLink: bublinu vykresľuje Leaflet
        // mimo Vue stromu, takže komponenty v nej nefungujú.
        `<a href="${publicEventPath(event)}" class="font-semibold">${escapeHtml(event.name)}</a>`
        + (event.dateRangeLabel ? `<br><span>${escapeHtml(event.dateRangeLabel)}</span>` : '')
        + (event.venue?.name ? `<br><span>${escapeHtml(event.venue.name)}</span>` : ''),
      )
      .addTo(markers)
  }

  if (points.length) {
    map.fitBounds(L.latLngBounds(points), { padding: [32, 32], maxZoom: 14 })
  } else {
    // Prázdna mapa musí niekde stáť — stred Slovenska je najmenej prekvapivá
    // voľba pre portál, ktorý je celý o slovenských podujatiach.
    map.setView([48.7, 19.5], 7)
  }
}

function escapeHtml(value: string): string {
  const element = document.createElement('span')
  element.textContent = value
  return element.innerHTML
}
</script>
