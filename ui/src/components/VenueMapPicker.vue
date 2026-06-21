<template>
  <div class="venue-map-wrapper">
    <div ref="mapEl" class="venue-map" />
    <p v-if="!hasCoords" class="map-hint">Klikni na mapu pre nastavenie polohy</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
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
}>()

const emit = defineEmits<{
  (e: 'update:lat', val: number): void
  (e: 'update:lng', val: number): void
}>()

const mapEl = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let marker: L.Marker | null = null

const DEFAULT_CENTER: L.LatLngExpression = [48.7, 19.5]
const DEFAULT_ZOOM = 7

const hasCoords = computed(() => props.lat != null && props.lng != null)

function setMarker(lat: number, lng: number) {
  if (!map) return
  if (marker) {
    marker.setLatLng([lat, lng])
  } else {
    marker = L.marker([lat, lng], { draggable: true }).addTo(map)
    marker.on('dragend', () => {
      const pos = marker!.getLatLng()
      emit('update:lat', Math.round(pos.lat * 1e6) / 1e6)
      emit('update:lng', Math.round(pos.lng * 1e6) / 1e6)
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
    emit('update:lat', roundedLat)
    emit('update:lng', roundedLng)
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
.venue-map-wrapper {
  position: relative;
  border-radius: 0.75rem;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}
.venue-map {
  height: 320px;
  width: 100%;
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
