<template>
  <!-- Malý odznak „Vyplnenie profilu 4/6". Čo chýba, ukáže až po kliknutí. -->
  <div v-if="readiness.loaded.value && readiness.total.value > 0" ref="root" class="relative">
      <button type="button" :aria-expanded="open" aria-haspopup="true"
        class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-xs"
        :class="readiness.ready.value ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
        @click="open = !open">
        <span class="hidden font-semibold sm:inline">{{ t('ai.readiness.title') }}</span>
        <span class="h-1.5 w-12 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
          <span class="block h-full rounded-full bg-emerald-500 transition-all" :style="{ width: readiness.percent.value + '%' }" />
        </span>
        <span class="tabular-nums">{{ readiness.satisfied.value }}/{{ readiness.total.value }}</span>
      </button>

      <div v-if="open" class="absolute right-0 top-full z-50 mt-2 w-72 rounded-xl border border-slate-200 bg-white p-3 text-left text-sm shadow-lg">
        <p class="font-semibold text-slate-700">{{ t('ai.readiness.title') }}</p>
        <p v-if="readiness.ready.value" class="mt-1 text-slate-600">{{ t('ai.readiness.ready') }}</p>
        <template v-else>
          <p class="mt-1 text-slate-600">{{ t('ai.readiness.missing') }}</p>
          <ul class="mt-1 list-disc pl-5 text-slate-800">
            <li v-for="label in missingLabels" :key="label">{{ label }}</li>
          </ul>
        </template>
        <p class="mt-2 text-xs text-slate-500">{{ t('ai.readiness.hint') }}</p>
      </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { t, type MessageKey } from '@/i18n'
import { usePublishReadiness } from '@/composables/usePublishReadiness'
import type { AiKind, Scope } from '@/api/ai'

const props = defineProps<{
  kind: AiKind
  scope: Scope
  values: Record<string, unknown>
}>()

const readiness = usePublishReadiness(props.scope, props.kind, () => props.values)

const missingLabels = computed(() =>
  readiness.missing.value.map(key => t(`ai.readiness.keys.${key}` as MessageKey)),
)

const open = ref(false)
const root = ref<HTMLElement | null>(null)

function onDocClick(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) open.value = false
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>
