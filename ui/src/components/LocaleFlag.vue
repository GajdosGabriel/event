<template>
  <svg
    viewBox="0 0 640 480"
    class="h-[15px] w-5 shrink-0 rounded-[2px] ring-1 ring-slate-900/20"
    aria-hidden="true"
    focusable="false"
  >
    <path v-for="(shape, index) in shapes" :key="index" :fill="shape.fill" :d="shape.d" />
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Locale } from '@/i18n'

const props = defineProps<{ code: Locale }>()

type Shape = { fill: string; d: string }

// Zjednodušené vlajky v spoločnom viewBoxe 640x480 – v hlavičke sa kreslia na
// 20 px, kde by sa heraldické detaily aj tak stratili. Inline SVG, nie emoji:
// vlajkové emoji Windows nevykresľuje. Angličtinu zastupuje britská vlajka.
const FLAGS: Record<Locale, Shape[]> = {
  sk: [
    { fill: '#ffffff', d: 'M0 0h640v160H0z' },
    { fill: '#0b4ea2', d: 'M0 160h640v160H0z' },
    { fill: '#ee1c25', d: 'M0 320h640v160H0z' },
    { fill: '#ffffff', d: 'M120 100h160v130c0 75-55 118-80 133-25-15-80-58-80-133z' },
    { fill: '#ee1c25', d: 'M133 113h134v117c0 66-47 103-67 116-20-13-67-50-67-116z' },
    { fill: '#0b4ea2', d: 'M200 402c-19-12-42-31-51-58 11-9 27-9 51 12 24-21 40-21 51-12-9 27-32 46-51 58z' },
    { fill: '#ffffff', d: 'M188 138h24v170h-24z' },
    { fill: '#ffffff', d: 'M166 176h68v20h-68z' },
    { fill: '#ffffff', d: 'M152 216h96v20h-96z' },
  ],
  cs: [
    { fill: '#ffffff', d: 'M0 0h640v240H0z' },
    { fill: '#d7141a', d: 'M0 240h640v240H0z' },
    { fill: '#11457e', d: 'M0 0l320 240L0 480z' },
  ],
  de: [
    { fill: '#000000', d: 'M0 0h640v160H0z' },
    { fill: '#dd0000', d: 'M0 160h640v160H0z' },
    { fill: '#ffce00', d: 'M0 320h640v160H0z' },
  ],
  en: [
    { fill: '#012169', d: 'M0 0h640v480H0z' },
    { fill: '#ffffff', d: 'm75 0 244 181L562 0h78v62L400 241l240 178v61h-80L320 301 81 480H0v-60l239-178L0 64V0z' },
    { fill: '#c8102e', d: 'm424 281 216 159v40L369 281zm-184 20 6 35L54 480H0zM640 0v3L391 191l2-44L590 0zM0 0l239 176h-60L0 42z' },
    { fill: '#ffffff', d: 'M241 0v480h160V0zM0 160v160h640V160z' },
    { fill: '#c8102e', d: 'M0 193v96h640v-96zM273 0v480h96V0z' },
  ],
}

const shapes = computed<Shape[]>(() => FLAGS[props.code] ?? [])
</script>
