<template>
  <!-- Natívny <details> zámerne: rozbalenie funguje klávesnicou aj bez JS
       a prehliadač sám rieši aria-expanded. -->
  <details class="form-section" :open="open" @toggle="sync">
    <summary class="form-section-head">
      <span class="min-w-0">
        <span class="field-legend mb-0 block">{{ title }}</span>
        <span v-if="note || $slots.note" class="mt-0.5 block truncate text-sm font-normal text-slate-500">
          <slot name="note">{{ note }}</slot>
        </span>
      </span>
      <span class="form-section-chevron" aria-hidden="true">▾</span>
    </summary>

    <div class="form-section-body">
      <slot />
    </div>
  </details>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  title: string
  /** Zhrnutie v hlavičke — čo je vnútri, kým je sekcia zabalená. */
  note?: string
  defaultOpen?: boolean
  /** Chyba v zabalenej sekcii by ostala neviditeľná — preto ju otvoríme za človeka. */
  forceOpen?: boolean
}>(), { note: '', defaultOpen: false, forceOpen: false })

const open = ref(props.defaultOpen || props.forceOpen)

watch(() => props.forceOpen, (v) => { if (v) open.value = true })

function sync(e: Event) {
  open.value = (e.target as HTMLDetailsElement).open
}
</script>
