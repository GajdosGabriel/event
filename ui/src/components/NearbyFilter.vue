<template>
  <div class="flex flex-wrap items-center gap-1.5">
    <button
      type="button"
      class="chip"
      :class="{ active: Boolean(position) }"
      :disabled="locating"
      :aria-pressed="Boolean(position)"
      @click="toggle"
    >
      <span aria-hidden="true">📍</span>
      {{ locating ? t('filters.events.nearbyLocating') : t('filters.events.nearby') }}
    </button>

    <!-- Okruh sa ukazuje až keď je poloha známa: bez nej nie je čo nastavovať
         a v riadku by len zaberal miesto. -->
    <label v-if="position" class="flex items-center gap-1 text-xs text-slate-500">
      <span class="sr-only">{{ t('filters.events.nearbyRadius') }}</span>
      <select
        v-model.number="radiusKm"
        class="h-7 rounded-lg border border-slate-200 bg-white px-2 text-xs text-slate-700 outline-none focus:border-blue-500"
        @change="emitChange"
      >
        <option v-for="option in NEARBY_RADIUS_OPTIONS" :key="option" :value="option">
          {{ t('filters.events.nearbyKm', { n: option }) }}
        </option>
      </select>
    </label>

    <p v-if="error" class="text-xs text-amber-700">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@/i18n'
import { useSettings, NEARBY_RADIUS_OPTIONS } from '@/composables/useSettings'
import type { Point } from '@/utils/geo'

export interface NearbySelection extends Point {
  radiusKm: number
}

const emit = defineEmits<{ change: [value: NearbySelection | null] }>()

const { settings, save } = useSettings()

const position = ref<Point | null>(null)
const radiusKm = ref(settings.value.nearbyRadiusKm)
const locating = ref(false)
const error = ref<string | null>(null)

/**
 * Poloha sa **neukladá** — ani do adresy, ani do localStorage. Zdieľaný odkaz
 * s vlastnými súradnicami je presne tá vec, ktorú človek nechce omylom poslať
 * ďalej, a po obnovení stránky je lacnejšie sa prehliadača spýtať znova
 * (povolenie si pamätá on) než ju držať u nás. Ukladá sa len okruh.
 */
function toggle() {
  if (position.value) {
    position.value = null
    error.value = null
    emit('change', null)
    return
  }

  locate()
}

function locate() {
  if (!('geolocation' in navigator)) {
    error.value = t('filters.events.nearbyUnsupported')
    return
  }

  locating.value = true
  error.value = null

  navigator.geolocation.getCurrentPosition(
    (result) => {
      position.value = {
        latitude: result.coords.latitude,
        longitude: result.coords.longitude,
      }
      locating.value = false
      emitChange()
    },
    () => {
      // Zamietnuté povolenie aj výpadok GPS končia rovnako: povieme, že to
      // nevyšlo, a necháme zoznam tak, ako bol. Rozlišovať dôvody by
      // návštevníkovi nepomohlo — obidva rieši on sám v prehliadači.
      locating.value = false
      error.value = t('filters.events.nearbyFailed')
    },
    { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
  )
}

function emitChange() {
  if (!position.value) return

  settings.value.nearbyRadiusKm = radiusKm.value
  save()

  emit('change', { ...position.value, radiusKm: radiusKm.value })
}
</script>
