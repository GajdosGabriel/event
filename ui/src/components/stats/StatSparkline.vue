<template>
  <svg
    class="block w-full"
    :height="height"
    :viewBox="`0 0 ${WIDTH} ${height}`"
    preserveAspectRatio="none"
    role="presentation"
    aria-hidden="true"
  >
    <!-- Plocha pod krivkou nesie ten istý odtieň zjemnený, aby trend čítal
         ako objem a nie ako druhá séria. -->
    <path v-if="areaPath" :d="areaPath" :fill="color" fill-opacity="0.12" />
    <path
      v-if="linePath"
      :d="linePath"
      fill="none"
      :stroke="color"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      vector-effect="non-scaling-stroke"
    />
    <circle v-if="lastPoint" :cx="lastPoint[0]" :cy="lastPoint[1]" r="2.5" :fill="color" />
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const WIDTH = 100

const props = withDefaults(defineProps<{
  values: number[]
  color?: string
  height?: number
}>(), {
  color: '#2a78d6',
  height: 32,
})

/**
 * Body krivky v jednotkách viewBoxu. Pri konštantnom rade (vrátane samých núl)
 * by delenie rozsahom padlo na nulu — vtedy krivka leží v strede.
 */
const points = computed<[number, number][]>(() => {
  const values = props.values
  if (values.length < 2) return []

  const max = Math.max(...values)
  const min = Math.min(...values)
  const span = max - min
  const top = 3
  const bottom = props.height - 3

  return values.map((value, index) => [
    (index / (values.length - 1)) * WIDTH,
    span === 0 ? (top + bottom) / 2 : bottom - ((value - min) / span) * (bottom - top),
  ])
})

const linePath = computed(() =>
  points.value.length ? points.value.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`).join(' ') : '',
)

const areaPath = computed(() =>
  linePath.value ? `${linePath.value} L${WIDTH},${props.height} L0,${props.height} Z` : '',
)

const lastPoint = computed(() => points.value[points.value.length - 1] ?? null)
</script>
