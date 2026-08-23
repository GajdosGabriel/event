<template>
  <div>
    <h2 class="mb-2 text-lg font-semibold text-slate-800">{{ title ?? t('address.map') }}</h2>
    <p v-if="hint !== false" class="mb-2 text-xs text-slate-500">{{ t('address.mapHint') }}</p>
    <MapPicker
      :lat="model.latitude"
      :lng="model.longitude"
      :source="model.coordinatesSource"
      @update:lat="patch({ latitude: $event })"
      @update:lng="patch({ longitude: $event })"
      @update:source="patch({ coordinatesSource: $event })"
    />
    <div class="mt-2 grid grid-cols-2 gap-2">
      <FormField v-model="latitude" type="number" :label="t('address.lat')" step="any" class="text-xs" />
      <FormField v-model="longitude" type="number" :label="t('address.lng')" step="any" class="text-xs" />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * Mapa a súradnice tej istej adresy, ktorú zbiera AddressFieldset. Sedí v inom
 * stĺpci rozloženia, preto je to samostatný komponent nad rovnakým `v-model`.
 *
 * Ručne prepísaná šírka či dĺžka prebíja automatický zdroj — inak by značka po
 * zásahu operátora ďalej tvrdila, že ju našiel geokóder.
 */
import { computed } from 'vue'
import FormField from '@/components/FormField.vue'
import MapPicker from '@/components/MapPicker.vue'
import { t } from '@/i18n'
import type { AddressModel } from '@/types'

defineProps<{ title?: string; hint?: boolean }>()

const model = defineModel<AddressModel>({ required: true })

function patch(next: Partial<AddressModel>) {
  model.value = { ...model.value, ...next }
}

const latitude = computed<number | null>({
  get: () => model.value.latitude,
  set: (value) => patch({ latitude: value, coordinatesSource: value == null ? null : 'manual' }),
})

const longitude = computed<number | null>({
  get: () => model.value.longitude,
  set: (value) => patch({ longitude: value, coordinatesSource: value == null ? null : 'manual' }),
})
</script>
