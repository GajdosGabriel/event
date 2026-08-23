<template>
  <div>
    <!-- Presnosť polohy: značka môže sedieť na budove aj len na strede obce.
         Bez štítku to na mape nerozoznať, a približná poloha by sa ticho
         vydávala za overenú. -->
    <p v-if="sourceLabel" class="source-badge" :class="{ 'source-badge--warn': isApproximate }">
      <span>{{ sourceLabel }}</span>
      <span v-if="sourceHint" class="source-badge__hint">{{ sourceHint }}</span>
    </p>
    <div class="map-picker-wrapper">
      <div ref="mapEl" class="map-picker" />
      <p v-if="!hasCoords" class="map-hint">{{ t('address.coordinates.missingHint') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { t } from '@/i18n'
import type { CoordinatesSource } from '@/types'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Fix default marker icon (Vite/webpack asset issue)
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)['_getIconUrl']
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
})

const props = defineProps<{
  lat: number | null
  lng: number | null
  source?: CoordinatesSource | null
}>()

const emit = defineEmits<{
  (e: 'update:lat', val: number): void
  (e: 'update:lng', val: number): void
  (e: 'update:source', val: CoordinatesSource): void
}>()

const mapEl = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let marker: L.Marker | null = null

const DEFAULT_CENTER: L.LatLngExpression = [48.7, 19.5]
const DEFAULT_ZOOM = 7

const hasCoords = computed(() => props.lat != null && props.lng != null)

const sourceLabel = computed(() => {
  if (!hasCoords.value) return t('address.coordinates.missing')
  switch (props.source) {
    case 'venue': return t('address.coordinates.venue')
    case 'address': return t('address.coordinates.address')
    case 'ai': return t('address.coordinates.ai')
    case 'municipality': return t('address.coordinates.municipality')
    case 'manual': return t('address.coordinates.manual')
    // Miesta uložené pred zavedením presnosti zdroj nemajú — tvrdiť o nich
    // čokoľvek by bola domnienka, preto ostane štítok bez presnosti.
    default: return ''
  }
})

const isApproximate = computed(() => props.source === 'municipality' || props.source === 'ai')

const sourceHint = computed(() => {
  if (!hasCoords.value) return ''
  return isApproximate.value ? t('address.coordinates.approximateHint') : ''
})

/** Ručný zásah prebíja akýkoľvek automatický zdroj. */
function emitCoords(lat: number, lng: number) {
  emit('update:lat', lat)
  emit('update:lng', lng)
  emit('update:source', 'manual')
}

function setMarker(lat: number, lng: number) {
  if (!map) return
  if (marker) {
    marker.setLatLng([lat, lng])
  } else {
    marker = L.marker([lat, lng], { draggable: true }).addTo(map)
    marker.on('dragend', () => {
      const pos = marker!.getLatLng()
      emitCoords(Math.round(pos.lat * 1e6) / 1e6, Math.round(pos.lng * 1e6) / 1e6)
    })
  }
}

onMounted(() => {
  if (!mapEl.value) return

  const center: L.LatLngExpression =
    props.lat != null && props.lng != null ? [props.lat, props.lng] : DEFAULT_CENTER
  const zoom = hasCoords.value ? 14 : DEFAULT_ZOOM

  map = L.map(mapEl.value).setView(center, zoom)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(map)

  if (props.lat != null && props.lng != null) {
    setMarker(props.lat, props.lng)
  }

  map.on('click', (e: L.LeafletMouseEvent) => {
    const { lat, lng } = e.latlng
    const roundedLat = Math.round(lat * 1e6) / 1e6
    const roundedLng = Math.round(lng * 1e6) / 1e6
    setMarker(roundedLat, roundedLng)
    emitCoords(roundedLat, roundedLng)
  })
})

watch([() => props.lat, () => props.lng], ([lat, lng]) => {
  if (lat != null && lng != null) {
    setMarker(lat, lng)
    map?.setView([lat, lng], map.getZoom() < 12 ? 14 : map.getZoom())
  }
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
  marker = null
})
</script>

<style scoped>
.map-picker-wrapper {
  position: relative;
  border-radius: 0.75rem;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}
.map-picker {
  height: 320px;
  width: 100%;
}
.source-badge {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  align-items: baseline;
  margin-bottom: 0.375rem;
  font-size: 0.75rem;
  color: #475569;
}
.source-badge--warn {
  color: #b45309;
  font-weight: 600;
}
.source-badge__hint {
  font-weight: 400;
  color: #92400e;
}
.map-hint {
  position: absolute;
  bottom: 0.5rem;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(255,255,255,0.85);
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  color: #475569;
  pointer-events: none;
  white-space: nowrap;
}
</style>
