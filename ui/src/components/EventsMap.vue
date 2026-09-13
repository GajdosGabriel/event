<template>
  <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <!-- Časové okno filtruje už načítané podujatia priamo na fronte — mapa
         dostáva celý výsledok filtra naraz, ďalší dotaz na API by nič nepridal. -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-slate-100 px-3 py-2">
      <button
        v-for="option in windows"
        :key="option"
        type="button"
        class="chip"
        :class="{ active: when === option }"
        :aria-pressed="when === option"
        @click="when = option"
      >{{ t(`public.list.mapWhen.${option}`) }}</button>
      <span class="ml-auto text-xs text-slate-500">{{ t('public.list.mapShown', { n: shownCount }) }}</span>
    </div>
    <div ref="mapEl" class="h-[32rem] w-full" />
    <p v-if="withoutCoordinates" class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
      {{ t('public.list.mapMissing', { n: withoutCoordinates }) }}
    </p>
    <p v-if="truncated" class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">
      {{ t('public.list.mapTruncated', { n: truncated }) }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import { useTemplateRef } from 'vue'
import type { EventMapPoint } from '@/api/events'
import { t } from '@/i18n'
import { publicEventPath } from '@/utils/publicUrl'
import { pointOf, type Point } from '@/utils/geo'

delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)['_getIconUrl']
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
})

const props = withDefaults(defineProps<{
  events: EventMapPoint[]
  /** Koľko podujatí filtra sa do mapy nenačítalo (strop počtu). */
  truncated?: number
}>(), { truncated: 0 })

/** Koľko podujatí bublina vypíše, kým ostatné zhrnie do „a ďalšie". */
const POPUP_LIMIT = 8

const windows = ['all', 'today', 'week', 'month'] as const
type MapWindow = typeof windows[number]

const when = ref<MapWindow>('all')

const mapEl = useTemplateRef<HTMLElement>('mapEl')

let map: L.Map | null = null
let markers: L.LayerGroup | null = null

/** Podujatia s miestom, ktoré má súradnice — ostatné na mape byť nemôžu. */
const located = computed(() => props.events.flatMap((event) => {
  const point = pointOf(event.venue)
  return point ? [{ event, point }] : []
}))

/**
 * Koľko podujatí sa na mapu nedostalo. Ticho ich zahodiť by znamenalo, že
 * mapa ukazuje menej než zoznam a nikto nevie prečo — pri importovanom
 * katalógu je miesto bez súradníc bežné.
 */
const withoutCoordinates = computed(() => props.events.length - located.value.length)

/** Podujatia, ktoré zasahujú do zvoleného časového okna. */
const inWindow = computed(() => {
  if (when.value === 'all') return located.value

  const from = new Date()
  from.setHours(0, 0, 0, 0)
  const to = new Date(from)
  to.setDate(to.getDate() + (when.value === 'today' ? 1 : when.value === 'week' ? 7 : 31))

  return located.value.filter(({ event }) => {
    if (!event.startAt) return false
    const start = new Date(event.startAt)
    const end = event.endAt ? new Date(event.endAt) : start
    // Prekryv intervalov — viacdňový festival, ktorý už beží, do „dnes" patrí.
    return start < to && end >= from
  })
})

const shownCount = computed(() => inWindow.value.length)

/**
 * Podujatia na tom istom mieste v jednom špendlíku. Samostatné značky by
 * ležali presne na sebe a vidno by bolo len vrchnú — z pätnástich podujatí
 * v troch kultúrnych domoch by mapa ukázala tri.
 */
const groups = computed(() => {
  const byPlace = new Map<string, { point: Point; events: EventMapPoint[] }>()

  for (const { event, point } of inWindow.value) {
    const key = `${point.latitude.toFixed(5)},${point.longitude.toFixed(5)}`
    const group = byPlace.get(key) ?? { point, events: [] }
    group.events.push(event)
    byPlace.set(key, group)
  }

  for (const group of byPlace.values()) {
    group.events.sort((a, b) => (a.startAt ?? '').localeCompare(b.startAt ?? ''))
  }

  return [...byPlace.values()]
})

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

watch(groups, render)

function render() {
  if (!map || !markers) return

  markers.clearLayers()

  const points: L.LatLngExpression[] = []

  for (const { point, events } of groups.value) {
    const latLng: L.LatLngExpression = [point.latitude, point.longitude]
    points.push(latLng)

    const marker = events.length > 1
      ? L.marker(latLng, { icon: countIcon(events.length), title: String(events.length) })
      : L.marker(latLng)

    marker.bindPopup(popupHtml(events), { maxWidth: 280 }).addTo(markers)
  }

  if (points.length) {
    map.fitBounds(L.latLngBounds(points), { padding: [32, 32], maxZoom: 14 })
  } else {
    // Prázdna mapa musí niekde stáť — stred Slovenska je najmenej prekvapivá
    // voľba pre portál, ktorý je celý o slovenských podujatiach.
    map.setView([48.7, 19.5], 7)
  }
}

function countIcon(count: number): L.DivIcon {
  const size = count >= 100 ? 40 : count >= 10 ? 34 : 30
  return L.divIcon({
    className: '',
    html: `<span style="display:flex;align-items:center;justify-content:center;width:${size}px;height:${size}px;`
      + 'border-radius:9999px;background:#2563eb;color:#fff;font-weight:600;font-size:13px;'
      + `border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)">${count}</span>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    popupAnchor: [0, -size / 2],
  })
}

// Odkazy sú obyčajné `<a>`, nie RouterLink: bublinu vykresľuje Leaflet mimo
// Vue stromu, takže komponenty v nej nefungujú.
function popupHtml(events: EventMapPoint[]): string {
  const venueName = events[0]?.venue?.name
  const items = events.slice(0, POPUP_LIMIT).map((event) =>
    `<a href="${publicEventPath(event)}" class="font-semibold">${escapeHtml(event.name)}</a>`
    + (event.dateRangeLabel ? `<br><span>${escapeHtml(event.dateRangeLabel)}</span>` : ''),
  )

  if (events.length === 1) {
    return items[0] + (venueName ? `<br><span>${escapeHtml(venueName)}</span>` : '')
  }

  const rest = events.length - POPUP_LIMIT

  return (venueName ? `<strong>${escapeHtml(venueName)}</strong><br>` : '')
    + `<div style="max-height:16rem;overflow-y:auto;margin-top:4px">`
    + items.map((item) => `<div style="margin:6px 0">${item}</div>`).join('')
    + (rest > 0 ? `<div style="color:#64748b">${escapeHtml(t('public.list.mapMore', { n: rest }))}</div>` : '')
    + '</div>'
}

function escapeHtml(value: string): string {
  const element = document.createElement('span')
  element.textContent = value
  return element.innerHTML
}
</script>
